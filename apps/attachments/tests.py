from tempfile import TemporaryDirectory

from django.contrib.auth import get_user_model
from django.core.exceptions import SuspiciousFileOperation, ValidationError
from django.core.files.uploadedfile import SimpleUploadedFile
from django.test import TestCase, override_settings
from django.urls import reverse

from apps.audit.models import AuditAction, AuditLog
from apps.topics.storage import create_topic
from apps.webs.models import Web, WebPermission, WebPermissionSubject

from .models import Attachment
from .storage import (
    attachment_current_path,
    attachment_meta_path,
    attachment_revision_path,
    clean_attachment_filename,
    save_attachment_revision,
)


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


class AttachmentTests(TestCase):
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
            can_upload=True,
            can_delete=True,
        )
        self.topic = create_topic(
            web=self.web,
            slug="start",
            title="Start",
            content=document("Start"),
            author=self.user,
            change_note="Start",
        )

    def test_save_attachment_revision_writes_current_revision_and_meta(self):
        uploaded_file = SimpleUploadedFile(
            "Handbuch.txt",
            b"Version eins",
            content_type="text/plain",
        )

        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=uploaded_file,
            author=self.user,
            change_note="Start",
        )

        self.assertEqual(attachment.original_filename, "Handbuch.txt")
        self.assertEqual(attachment.storage_name, "handbuch.txt")
        self.assertEqual(attachment.current_revision, 1)
        self.assertEqual(attachment.size, len(b"Version eins"))
        self.assertTrue(attachment_current_path(attachment).exists())
        self.assertTrue(attachment_revision_path(attachment, 1).exists())
        self.assertTrue(attachment_meta_path(attachment).exists())

    def test_second_upload_with_same_name_creates_new_revision(self):
        first = SimpleUploadedFile("handbuch.txt", b"eins", content_type="text/plain")
        second = SimpleUploadedFile("handbuch.txt", b"zwei", content_type="text/plain")

        attachment = save_attachment_revision(topic=self.topic, uploaded_file=first, author=self.user)
        attachment = save_attachment_revision(topic=self.topic, uploaded_file=second, author=self.user)

        self.assertEqual(attachment.current_revision, 2)
        self.assertEqual(attachment_revision_path(attachment, 1).read_bytes(), b"eins")
        self.assertEqual(attachment_revision_path(attachment, 2).read_bytes(), b"zwei")
        self.assertEqual(attachment_current_path(attachment).read_bytes(), b"zwei")

    def test_blocked_extension_is_rejected(self):
        uploaded_file = SimpleUploadedFile("shell.php", b"<?php", content_type="application/x-php")

        with self.assertRaises(ValidationError):
            save_attachment_revision(topic=self.topic, uploaded_file=uploaded_file, author=self.user)

    def test_unexpected_mime_type_is_rejected(self):
        uploaded_file = SimpleUploadedFile(
            "notizen.txt",
            b"Text",
            content_type="application/x-msdownload",
        )

        with self.assertRaises(ValidationError):
            save_attachment_revision(topic=self.topic, uploaded_file=uploaded_file, author=self.user)

    def test_path_manipulation_filename_is_rejected(self):
        with self.assertRaises(SuspiciousFileOperation):
            clean_attachment_filename("../safe.txt")

    def test_upload_view_requires_upload_right(self):
        self.client.login(username="bob", password="test-password")

        response = self.client.post(
            reverse(
                "attachment_upload",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            ),
            {
                "file": SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
                "change_note": "Upload",
            },
        )

        self.assertEqual(response.status_code, 403)
        self.assertFalse(Attachment.objects.exists())

    def test_upload_view_writes_attachment_and_auditlog(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "attachment_upload",
                kwargs={"web_slug": self.web.slug, "topic_slug": self.topic.slug},
            ),
            {
                "file": SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
                "change_note": "Upload",
            },
        )

        self.assertEqual(response.status_code, 302)
        attachment = Attachment.objects.get(topic=self.topic, storage_name="handbuch.txt")
        self.assertEqual(attachment.current_revision, 1)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.ATTACHMENT_UPLOADED,
                topic=self.topic,
                attachment_name="handbuch.txt",
            ).exists()
        )

    def test_download_requires_view_right(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        self.client.login(username="bob", password="test-password")

        response = self.client.get(
            reverse(
                "attachment_download",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "attachment_id": attachment.id,
                },
            )
        )

        self.assertEqual(response.status_code, 403)

    def test_download_streams_file_for_user_with_view_right(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "attachment_download",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "attachment_id": attachment.id,
                },
            )
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(b"".join(response.streaming_content), b"Text")
        self.assertIn("attachment", response["Content-Disposition"])

    def test_delete_requires_delete_right(self):
        WebPermission.objects.create(
            web=self.web,
            subject_type=WebPermissionSubject.USER,
            user=self.other_user,
            can_view=True,
        )
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        self.client.login(username="bob", password="test-password")

        response = self.client.post(
            reverse(
                "attachment_delete",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "attachment_id": attachment.id,
                },
            )
        )

        self.assertEqual(response.status_code, 403)
        attachment.refresh_from_db()
        self.assertFalse(attachment.is_deleted)

    def test_delete_marks_attachment_deleted_and_writes_auditlog(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        self.client.login(username="alice", password="test-password")

        response = self.client.post(
            reverse(
                "attachment_delete",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "attachment_id": attachment.id,
                },
            )
        )

        self.assertEqual(response.status_code, 302)
        attachment.refresh_from_db()
        self.assertTrue(attachment.is_deleted)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.ATTACHMENT_DELETED,
                topic=self.topic,
                attachment_name="handbuch.txt",
                old_revision=1,
            ).exists()
        )

    def test_deleted_attachment_cannot_be_downloaded(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        attachment.is_deleted = True
        attachment.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="alice", password="test-password")

        response = self.client.get(
            reverse(
                "attachment_download",
                kwargs={
                    "web_slug": self.web.slug,
                    "topic_slug": self.topic.slug,
                    "attachment_id": attachment.id,
                },
            )
        )

        self.assertEqual(response.status_code, 404)

    def test_attachment_trash_requires_staff(self):
        self.client.login(username="alice", password="test-password")

        response = self.client.get(reverse("attachment_trash"))

        self.assertEqual(response.status_code, 403)

    def test_attachment_trash_lists_deleted_attachments_for_staff(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        attachment.is_deleted = True
        attachment.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="admin", password="test-password")

        response = self.client.get(reverse("attachment_trash"))

        self.assertContains(response, "handbuch.txt")
        self.assertContains(response, "technik/start")

    def test_restore_requires_staff(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        attachment.is_deleted = True
        attachment.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="alice", password="test-password")

        response = self.client.post(reverse("attachment_restore", kwargs={"attachment_id": attachment.id}))

        self.assertEqual(response.status_code, 403)
        attachment.refresh_from_db()
        self.assertTrue(attachment.is_deleted)

    def test_restore_marks_attachment_visible_and_writes_auditlog(self):
        attachment = save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile("handbuch.txt", b"Text", content_type="text/plain"),
            author=self.user,
        )
        attachment.is_deleted = True
        attachment.save(update_fields=["is_deleted", "updated_at"])
        self.client.login(username="admin", password="test-password")

        response = self.client.post(reverse("attachment_restore", kwargs={"attachment_id": attachment.id}))

        self.assertEqual(response.status_code, 302)
        attachment.refresh_from_db()
        self.assertFalse(attachment.is_deleted)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.ATTACHMENT_UPDATED,
                topic=self.topic,
                attachment_name="handbuch.txt",
                details__source="attachment_restored",
            ).exists()
        )
