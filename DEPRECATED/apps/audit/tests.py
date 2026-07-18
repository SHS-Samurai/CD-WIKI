from tempfile import TemporaryDirectory

from django.contrib.auth import get_user_model
from django.test import RequestFactory, TestCase, override_settings

from apps.topics.storage import create_topic, save_topic_revision
from apps.webs.models import Web

from .models import AuditAction, AuditLog
from .services import log_topic_revision, write_audit_log


def document(text: str) -> dict:
    return {
        "type": "doc",
        "content": [
            {
                "type": "paragraph",
                "content": [{"type": "text", "text": text}],
            }
        ],
    }


class AuditLogTests(TestCase):
    def setUp(self):
        self.tmpdir = TemporaryDirectory()
        self.override = override_settings(WIKI_STORAGE_ROOT=self.tmpdir.name)
        self.override.enable()
        self.addCleanup(self.override.disable)
        self.addCleanup(self.tmpdir.cleanup)

        user_model = get_user_model()
        self.user = user_model.objects.create_user(
            username="alice",
            password="test-password",
        )
        self.web = Web.objects.create(slug="technik", title="Technik")
        self.topic = create_topic(
            web=self.web,
            slug="start",
            title="Start",
            content=document("Start"),
            author=self.user,
            change_note="Start",
        )

    @override_settings(WIKI_TRUSTED_PROXY_IPS=["127.0.0.1", "10.0.0.0/8"])
    def test_write_audit_log_stores_required_context(self):
        request = RequestFactory().post(
            "/technik/start",
            HTTP_USER_AGENT="Test Browser",
            HTTP_X_FORWARDED_FOR="203.0.113.7, 10.0.0.2",
        )
        request.user = self.user

        log = write_audit_log(
            action=AuditAction.TOPIC_UPDATED,
            request=request,
            web=self.web,
            topic=self.topic,
            old_revision=1,
            new_revision=2,
            old_hash="a" * 64,
            new_hash="b" * 64,
            details={"note": "Test"},
        )

        self.assertEqual(log.user, self.user)
        self.assertEqual(log.user_id_snapshot, self.user.pk)
        self.assertEqual(log.username, "alice")
        self.assertEqual(log.ip_address, "203.0.113.7")
        self.assertEqual(log.user_agent, "Test Browser")
        self.assertEqual(log.web_slug, "technik")
        self.assertEqual(log.topic_slug, "start")
        self.assertEqual(log.old_revision, 1)
        self.assertEqual(log.new_revision, 2)
        self.assertEqual(log.details, {"note": "Test"})

    @override_settings(WIKI_TRUSTED_PROXY_IPS=["127.0.0.1"])
    def test_untrusted_remote_address_cannot_spoof_forwarded_ip(self):
        request = RequestFactory().post(
            "/technik/start",
            REMOTE_ADDR="192.0.2.20",
            HTTP_X_FORWARDED_FOR="203.0.113.7",
        )

        log = write_audit_log(action=AuditAction.LOGIN_FAILED, request=request)

        self.assertEqual(log.ip_address, "192.0.2.20")

    def test_log_topic_revision_uses_topic_context(self):
        old_revision = self.topic.current_revision
        old_hash = self.topic.current_hash
        save_topic_revision(
            topic=self.topic,
            content=document("Neu"),
            author=self.user,
            change_note="Neu",
        )
        self.topic.refresh_from_db()

        log = log_topic_revision(
            topic=self.topic,
            user=self.user,
            old_revision=old_revision,
            new_revision=self.topic.current_revision,
            old_hash=old_hash,
            new_hash=self.topic.current_hash,
            details={"change_note": "Neu"},
        )

        self.assertEqual(log.action, AuditAction.TOPIC_UPDATED)
        self.assertEqual(log.web, self.web)
        self.assertEqual(log.topic, self.topic)
        self.assertEqual(log.web_slug, "technik")
        self.assertEqual(log.topic_slug, "start")
        self.assertEqual(log.old_revision, 1)
        self.assertEqual(log.new_revision, 2)

    def test_snapshot_survives_deleted_user_reference(self):
        log = write_audit_log(
            action=AuditAction.TOPIC_CREATED,
            user=self.user,
            web=self.web,
            topic=self.topic,
        )
        user_id = self.user.pk
        self.user.delete()

        log.refresh_from_db()
        self.assertIsNone(log.user)
        self.assertEqual(log.user_id_snapshot, user_id)
        self.assertEqual(log.username, "alice")

    def test_logs_order_newest_first(self):
        first = write_audit_log(action=AuditAction.WEB_CREATED, web=self.web)
        second = write_audit_log(action=AuditAction.WEB_UPDATED, web=self.web)

        self.assertEqual(list(AuditLog.objects.all()), [second, first])
