from tempfile import TemporaryDirectory

from django.contrib.auth import get_user_model
from django.test import TestCase, override_settings
from django.urls import reverse

from apps.audit.models import AuditAction, AuditLog
from apps.topics.storage import create_topic
from apps.webs.models import Web, WebPermission, WebPermissionSubject

from .models import Comment


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


class CommentTests(TestCase):
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
        self.other_user = user_model.objects.create_user(
            username="bob",
            password="test-password",
        )
        self.admin = user_model.objects.create_user(
            username="admin",
            password="test-password",
            is_staff=True,
        )
        self.web = Web.objects.create(slug="technik", title="Technik")
        WebPermission.objects.create(
            web=self.web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
            can_comment=True,
        )
        WebPermission.objects.create(
            web=self.web,
            subject_type=WebPermissionSubject.USER,
            user=self.other_user,
            can_view=True,
        )
        self.topic = create_topic(
            web=self.web,
            slug="start",
            title="Start",
            content=document("Start"),
            author=self.user,
            change_note="Start",
        )

    def test_topic_detail_lists_comments_for_user_with_view_right(self):
        Comment.objects.create(topic=self.topic, author=self.user, body="Hallo Kommentar")
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "topic_detail",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            )
        )

        self.assertContains(response, "Hallo Kommentar")
        self.assertContains(response, "Kommentar speichern")

    def test_create_comment_requires_comment_right(self):
        self.client.login(username="bob", password="test-password")

        response = self.client.post(
            reverse(
                "comment_create",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            ),
            {"body": "Kein Recht"},
        )

        self.assertEqual(response.status_code, 403)
        self.assertFalse(Comment.objects.exists())

    def test_create_comment_writes_comment_and_auditlog(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "comment_create",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            ),
            {"body": "Guter Hinweis"},
        )

        self.assertEqual(response.status_code, 302)
        comment = Comment.objects.get(topic=self.topic)
        self.assertEqual(comment.body, "Guter Hinweis")
        self.assertEqual(comment.author_username, "alice")
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.COMMENT_CREATED,
                topic=self.topic,
                details__comment_id=comment.pk,
            ).exists()
        )

    def test_empty_comment_is_rejected(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "comment_create",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            ),
            {"body": "   "},
        )

        self.assertEqual(response.status_code, 400)
        self.assertFalse(Comment.objects.exists())

    def test_delete_comment_requires_staff_user(self):
        comment = Comment.objects.create(topic=self.topic, author=self.user, body="Bleibt")
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "comment_delete",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "comment_id": comment.pk,
                },
            )
        )

        self.assertEqual(response.status_code, 403)
        comment.refresh_from_db()
        self.assertFalse(comment.is_deleted)

    def test_staff_delete_marks_comment_deleted_and_logs_action(self):
        comment = Comment.objects.create(topic=self.topic, author=self.user, body="Weg")
        self.client.login(username="admin", password="test-password")

        response = self.client.post(
            reverse(
                "comment_delete",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "comment_id": comment.pk,
                },
            )
        )

        self.assertEqual(response.status_code, 302)
        comment.refresh_from_db()
        self.assertTrue(comment.is_deleted)
        self.assertEqual(comment.deleted_by, self.admin)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.COMMENT_DELETED,
                topic=self.topic,
                details__comment_id=comment.pk,
            ).exists()
        )
