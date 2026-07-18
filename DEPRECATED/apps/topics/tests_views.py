from tempfile import TemporaryDirectory

from django.contrib.auth import get_user_model
from django.test import TestCase, override_settings
from django.urls import reverse

from apps.attachments.models import Attachment
from apps.audit.models import AuditAction, AuditLog
from apps.webs.models import Web, WebPermission, WebPermissionSubject, WebVisibility

from .forms import document_to_json
from .models import Topic
from .storage import create_topic, load_current_topic, save_topic_revision


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


class PhaseTwoViewTests(TestCase):
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
        self.admin = user_model.objects.create_user(
            username="admin",
            password="test-password",
            is_staff=True,
        )
        self.web = Web.objects.create(
            slug="technik",
            title="Technik",
            visibility=WebVisibility.PRIVATE,
        )
        WebPermission.objects.create(
            web=self.web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
            can_create=True,
            can_edit=True,
            can_delete=True,
        )

    def test_login_and_logout_views_work(self):
        response = self.client.get(reverse("login"))
        self.assertEqual(response.status_code, 200)

        response = self.client.post(
            reverse("login"),
            {"username": "alice", "password": "test-password"},
        )
        self.assertEqual(response.status_code, 302)

        response = self.client.post(reverse("logout"))
        self.assertEqual(response.status_code, 302)

    def test_web_list_shows_only_visible_webs(self):
        Web.objects.create(slug="public", title="Public", visibility=WebVisibility.PUBLIC)
        Web.objects.create(slug="private", title="Private", visibility=WebVisibility.PRIVATE)

        response = self.client.get(reverse("home"))

        self.assertContains(response, "Public")
        self.assertNotContains(response, "Private")

    def test_private_web_detail_requires_view_right(self):
        response = self.client.get(reverse("web_detail", kwargs={"web_slug": self.web.slug}))

        self.assertEqual(response.status_code, 403)

    def test_topic_detail_renders_controlled_escaped_html(self):
        topic = create_topic(
            web=self.web,
            slug="start",
            title="Start",
            content=document("<script>alert(1)</script>"),
            author=self.user,
            change_note="Start",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "topic_detail",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            )
        )

        self.assertEqual(response.status_code, 200)
        self.assertNotContains(response, "<script>")
        self.assertContains(response, "&lt;script&gt;alert(1)&lt;/script&gt;")

    def test_topic_create_requires_create_right(self):
        view_only_web = Web.objects.create(slug="view-only", title="View Only")
        WebPermission.objects.create(
            web=view_only_web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse("topic_create", kwargs={"web_slug": view_only_web.slug}),
            {
                "slug": "neu",
                "title": "Neu",
                "content_json": document_to_json(document("Neu")),
                "change_note": "Start",
            },
        )

        self.assertEqual(response.status_code, 403)
        self.assertFalse(Topic.objects.filter(web=view_only_web, slug="neu").exists())

    def test_topic_create_uses_editor_bundle(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.get(reverse("topic_create", kwargs={"web_slug": self.web.slug}))

        self.assertContains(response, "data-wiki-editor")
        self.assertContains(response, "editor/wiki-editor.js")

    def test_topic_edit_offers_existing_attachments_in_editor(self):
        topic = create_topic(
            web=self.web,
            slug="editor-attachment",
            title="Editor Attachment",
            content=document("Inhalt"),
            author=self.user,
            change_note="Start",
        )
        attachment = Attachment.objects.create(
            topic=topic,
            original_filename="handbuch.pdf",
            storage_name="handbuch.pdf",
            content_type="application/pdf",
            size=42,
            current_revision=1,
            uploaded_by=self.user,
            updated_by=self.user,
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "topic_edit",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            )
        )

        self.assertContains(response, "handbuch.pdf")
        self.assertContains(
            response,
            reverse(
                "attachment_download",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": topic.slug,
                    "attachment_id": attachment.pk,
                },
            ),
        )

    def test_topic_create_writes_revision_and_auditlog(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse("topic_create", kwargs={"web_slug": self.web.slug}),
            {
                "slug": "neu",
                "title": "Neu",
                "content_json": document_to_json(document("Neu")),
                "change_note": "Start",
            },
        )

        self.assertEqual(response.status_code, 302)
        topic = Topic.objects.get(web=self.web, slug="neu")
        self.assertEqual(topic.current_revision, 1)
        self.assertEqual(load_current_topic(topic)["content"], document("Neu"))
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.TOPIC_CREATED,
                topic=topic,
                new_revision=1,
            ).exists()
        )

    def test_topic_edit_writes_new_revision_and_auditlog(self):
        topic = create_topic(
            web=self.web,
            slug="start",
            title="Start",
            content=document("Alt"),
            author=self.user,
            change_note="Start",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "topic_edit",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            ),
            {
                "title": "Startseite",
                "content_json": document_to_json(document("Neu")),
                "change_note": "Aktualisiert",
            },
        )

        self.assertEqual(response.status_code, 302)
        topic.refresh_from_db()
        self.assertEqual(topic.title, "Startseite")
        self.assertEqual(topic.current_revision, 2)
        self.assertEqual(load_current_topic(topic)["content"], document("Neu"))
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.TOPIC_UPDATED,
                topic=topic,
                old_revision=1,
                new_revision=2,
            ).exists()
        )

    def test_revision_list_requires_view_right_and_lists_revisions(self):
        topic = create_topic(
            web=self.web,
            slug="history",
            title="History",
            content=document("Version eins"),
            author=self.user,
            change_note="Start",
        )
        save_topic_revision(
            topic=topic,
            content=document("Version zwei"),
            author=self.user,
            change_note="Aktualisiert",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "topic_revisions",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            )
        )

        self.assertContains(response, "Revision 2")
        self.assertContains(response, "Revision 1")
        self.assertContains(response, "Aktualisiert")

    def test_revision_detail_renders_old_content(self):
        topic = create_topic(
            web=self.web,
            slug="old",
            title="Old",
            content=document("Alter Inhalt"),
            author=self.user,
            change_note="Start",
        )
        save_topic_revision(
            topic=topic,
            content=document("Neuer Inhalt"),
            author=self.user,
            change_note="Neu",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "topic_revision_detail",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": topic.slug,
                    "revision": 1,
                },
            )
        )

        self.assertContains(response, "Alter Inhalt")
        self.assertNotContains(response, "Neuer Inhalt")

    def test_revision_restore_requires_edit_right(self):
        view_only_web = Web.objects.create(slug="history-view", title="History View")
        WebPermission.objects.create(
            web=view_only_web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
        )
        topic = create_topic(
            web=view_only_web,
            slug="start",
            title="Start",
            content=document("Alt"),
            author=self.user,
            change_note="Start",
        )
        save_topic_revision(
            topic=topic,
            content=document("Neu"),
            author=self.user,
            change_note="Neu",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "topic_revision_restore",
                kwargs={
                    "web_slug": view_only_web.slug,
                    "topic_slug": topic.slug,
                    "revision": 1,
                },
            )
        )

        self.assertEqual(response.status_code, 403)
        topic.refresh_from_db()
        self.assertEqual(topic.current_revision, 2)

    def test_revision_restore_creates_new_revision_and_auditlog(self):
        topic = create_topic(
            web=self.web,
            slug="restore-view",
            title="Restore",
            content=document("Alt"),
            author=self.user,
            change_note="Start",
        )
        save_topic_revision(
            topic=topic,
            content=document("Neu"),
            author=self.user,
            change_note="Neu",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "topic_revision_restore",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": topic.slug,
                    "revision": 1,
                },
            )
        )

        self.assertEqual(response.status_code, 302)
        topic.refresh_from_db()
        self.assertEqual(topic.current_revision, 3)
        self.assertEqual(load_current_topic(topic)["content"], document("Alt"))
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.REVISION_RESTORED,
                topic=topic,
                old_revision=2,
                new_revision=3,
                details__restored_revision=1,
            ).exists()
        )

    def test_topic_delete_requires_delete_right(self):
        view_only_web = Web.objects.create(slug="delete-view", title="Delete View")
        WebPermission.objects.create(
            web=view_only_web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
        )
        topic = create_topic(
            web=view_only_web,
            slug="start",
            title="Start",
            content=document("Bleibt"),
            author=self.user,
            change_note="Start",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "topic_delete",
                kwargs={"web_slug": view_only_web.slug, "topic_slug": topic.slug},
            )
        )

        self.assertEqual(response.status_code, 403)
        topic.refresh_from_db()
        self.assertFalse(topic.is_deleted)

    def test_topic_delete_marks_topic_deleted_and_writes_auditlog(self):
        topic = create_topic(
            web=self.web,
            slug="delete-me",
            title="Delete Me",
            content=document("Papierkorb"),
            author=self.user,
            change_note="Start",
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "topic_delete",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            )
        )

        self.assertEqual(response.status_code, 302)
        topic.refresh_from_db()
        self.assertTrue(topic.is_deleted)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.TOPIC_DELETED,
                topic=topic,
                old_revision=1,
            ).exists()
        )
        response = self.client.get(
            reverse(
                "topic_detail",
                kwargs={"web_slug": self.web.slug, "topic_slug": topic.slug},
            )
        )
        self.assertEqual(response.status_code, 404)

    def test_topic_trash_requires_staff(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.get(reverse("topic_trash"))

        self.assertEqual(response.status_code, 403)

    def test_topic_trash_lists_deleted_topics_for_staff(self):
        topic = create_topic(
            web=self.web,
            slug="trash-list",
            title="Trash List",
            content=document("Geloescht"),
            author=self.user,
            change_note="Start",
        )
        topic.is_deleted = True
        topic.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="admin", password="test-password")

        response = self.client.get(reverse("topic_trash"))

        self.assertContains(response, "Trash List")
        self.assertContains(response, "technik/trash-list")

    def test_topic_restore_requires_staff(self):
        topic = create_topic(
            web=self.web,
            slug="restore-denied",
            title="Restore Denied",
            content=document("Geloescht"),
            author=self.user,
            change_note="Start",
        )
        topic.is_deleted = True
        topic.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="alice", password="test-password")

        response = self.client.post(reverse("topic_restore", kwargs={"topic_id": topic.pk}))

        self.assertEqual(response.status_code, 403)
        topic.refresh_from_db()
        self.assertTrue(topic.is_deleted)

    def test_topic_restore_marks_topic_visible_and_writes_auditlog(self):
        topic = create_topic(
            web=self.web,
            slug="restore-topic",
            title="Restore Topic",
            content=document("Zurueck"),
            author=self.user,
            change_note="Start",
        )
        topic.is_deleted = True
        topic.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="admin", password="test-password")

        response = self.client.post(reverse("topic_restore", kwargs={"topic_id": topic.pk}))

        self.assertEqual(response.status_code, 302)
        topic.refresh_from_db()
        self.assertFalse(topic.is_deleted)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.TOPIC_UPDATED,
                topic=topic,
                details__source="topic_restored",
            ).exists()
        )
