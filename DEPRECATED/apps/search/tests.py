from io import BytesIO
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest.mock import MagicMock, patch
from zipfile import ZIP_DEFLATED, ZipFile

from django.contrib.auth import get_user_model
from django.core.files.uploadedfile import SimpleUploadedFile
from django.test import TestCase, override_settings
from django.urls import reverse

from apps.attachments.storage import save_attachment_revision
from apps.topics.storage import create_topic
from apps.webs.models import Web, WebPermission, WebPermissionSubject

from .client import configure_index
from .documents import build_topic_document
from .extractors import extract_attachment_text
from .services import search_topics


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


class SearchDocumentTests(TestCase):
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
            content=document("Topic Suchwort"),
            author=self.user,
            change_note="Start",
        )

    def test_topic_document_contains_topic_and_attachment_text(self):
        save_attachment_revision(
            topic=self.topic,
            uploaded_file=SimpleUploadedFile(
                "notizen.txt",
                b"Attachment Suchwort",
                content_type="text/plain",
            ),
            author=self.user,
        )

        search_document = build_topic_document(self.topic)

        self.assertEqual(search_document["wiki_path"], "technik/start")
        self.assertIn("Topic Suchwort", search_document["content_text"])
        self.assertIn("notizen.txt", search_document["attachment_names"])
        self.assertIn("Attachment Suchwort", search_document["attachment_text"])

    def test_html_attachment_extraction_ignores_scripts(self):
        html_path = Path(self.tmpdir.name) / "seite.html"
        html_path.write_text(
            "<html><body><h1>Sichtbar</h1><script>geheim()</script></body></html>",
            encoding="utf-8",
        )

        text = extract_attachment_text(html_path, "seite.html", "text/html")

        self.assertIn("Sichtbar", text)
        self.assertNotIn("geheim", text)

    @override_settings(WIKI_MAX_ARCHIVE_UNCOMPRESSED_SIZE=10)
    def test_extractor_rejects_large_expanded_archive(self):
        archive_data = BytesIO()
        with ZipFile(archive_data, "w", ZIP_DEFLATED) as archive:
            archive.writestr("[Content_Types].xml", b"x")
            archive.writestr("word/document.xml", b"mehr als zehn bytes")
        path = Path(self.tmpdir.name) / "gross.docx"
        path.write_bytes(archive_data.getvalue())

        self.assertEqual(extract_attachment_text(path, path.name), "")


class SearchPermissionTests(TestCase):
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
        self.allowed_web = Web.objects.create(slug="technik", title="Technik")
        self.private_web = Web.objects.create(slug="privat", title="Privat")
        WebPermission.objects.create(
            web=self.allowed_web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
        )
        self.allowed_topic = create_topic(
            web=self.allowed_web,
            slug="start",
            title="Erlaubt",
            content=document("Erlaubter Text"),
            author=self.user,
            change_note="Start",
        )
        self.private_topic = create_topic(
            web=self.private_web,
            slug="geheim",
            title="Geheim",
            content=document("Privater Text"),
            author=self.user,
            change_note="Start",
        )

    @patch("apps.search.services.get_index")
    def test_search_filters_hits_without_view_right(self, get_index):
        index = MagicMock()
        get_index.return_value = index
        index.search.return_value = {
            "hits": [
                {
                    "topic_id": self.private_topic.pk,
                    "content_text": "Privater Text",
                    "is_deleted": False,
                },
                {
                    "topic_id": self.allowed_topic.pk,
                    "content_text": "Erlaubter Text",
                    "is_deleted": False,
                },
            ]
        }

        results = search_topics("Text", self.user)

        self.assertEqual([result.topic.pk for result in results], [self.allowed_topic.pk])

    @patch("apps.search.services.get_index")
    def test_search_view_does_not_render_forbidden_hit(self, get_index):
        index = MagicMock()
        get_index.return_value = index
        index.search.return_value = {
            "hits": [
                {
                    "topic_id": self.private_topic.pk,
                    "content_text": "Privater Text",
                    "is_deleted": False,
                },
                {
                    "topic_id": self.allowed_topic.pk,
                    "content_text": "Erlaubter Text",
                    "is_deleted": False,
                },
            ]
        }
        self.client.login(username="alice", password="test-password")

        response = self.client.get(reverse("search"), {"q": "Text"})

        self.assertContains(response, "Erlaubt")
        self.assertContains(response, "Erlaubter Text")
        self.assertNotContains(response, "Privater Text")
        self.assertNotContains(response, "Geheim")

    @patch("apps.search.services.get_index")
    def test_search_view_handles_unavailable_search_service(self, get_index):
        index = MagicMock()
        get_index.return_value = index
        index.search.side_effect = RuntimeError("offline")
        self.client.login(username="alice", password="test-password")

        response = self.client.get(reverse("search"), {"q": "Text"})

        self.assertContains(response, "Die Suche ist gerade nicht erreichbar.")

    def test_configure_index_updates_meilisearch_settings(self):
        index = MagicMock()

        configure_index(index)

        index.update_searchable_attributes.assert_called_once()
        index.update_filterable_attributes.assert_called_once()
        index.update_sortable_attributes.assert_called_once()
