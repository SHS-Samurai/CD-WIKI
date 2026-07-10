from pathlib import Path
from tempfile import TemporaryDirectory

from django.contrib.auth import get_user_model
from django.core.exceptions import SuspiciousFileOperation, ValidationError
from django.test import TestCase, override_settings

from apps.webs.models import Web

from .content import extract_text, validate_prosemirror_document
from .models import Topic
from .storage import (
    content_hash,
    create_topic,
    load_current_topic,
    load_topic_revision,
    restore_topic_revision,
    save_topic_revision,
    topic_current_path,
    topic_revision_path,
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


class TopicStorageTests(TestCase):
    def setUp(self):
        self.tmpdir = TemporaryDirectory()
        self.override = override_settings(WIKI_STORAGE_ROOT=self.tmpdir.name)
        self.override.enable()
        self.addCleanup(self.override.disable)
        self.addCleanup(self.tmpdir.cleanup)

        user_model = get_user_model()
        self.author = user_model.objects.create_user(
            username="alice",
            password="test-password",
        )
        self.web = Web.objects.create(slug="technik", title="Technik")

    def test_create_topic_writes_current_and_first_revision(self):
        content = document("Hallo Wiki")

        topic = create_topic(
            web=self.web,
            slug="startseite",
            title="Startseite",
            content=content,
            author=self.author,
            change_note="Initiale Seite",
        )

        topic.refresh_from_db()
        self.assertEqual(topic.current_revision, 1)
        self.assertEqual(topic.current_hash, content_hash(content))
        self.assertEqual(topic.last_edited_by, self.author)
        self.assertEqual(topic.change_note, "Initiale Seite")
        self.assertTrue(topic_current_path(topic).exists())
        self.assertTrue(topic_revision_path(topic, 1).exists())
        self.assertEqual(load_current_topic(topic)["content"], content)
        self.assertEqual(load_topic_revision(topic, 1)["content_hash"], topic.current_hash)

    def test_second_save_creates_new_revision_without_overwriting_old_one(self):
        topic = create_topic(
            web=self.web,
            slug="server",
            title="Server",
            content=document("Version eins"),
            author=self.author,
            change_note="Start",
        )

        save_topic_revision(
            topic=topic,
            content=document("Version zwei"),
            author=self.author,
            change_note="Aktualisiert",
        )

        topic.refresh_from_db()
        self.assertEqual(topic.current_revision, 2)
        self.assertEqual(load_topic_revision(topic, 1)["content"], document("Version eins"))
        self.assertEqual(load_topic_revision(topic, 2)["content"], document("Version zwei"))
        self.assertEqual(load_current_topic(topic)["revision"], 2)

    def test_restore_old_revision_creates_new_revision(self):
        topic = create_topic(
            web=self.web,
            slug="restore",
            title="Restore",
            content=document("Alt"),
            author=self.author,
            change_note="Start",
        )
        save_topic_revision(
            topic=topic,
            content=document("Neu"),
            author=self.author,
            change_note="Neu geschrieben",
        )

        restored = restore_topic_revision(
            topic=topic,
            revision=1,
            author=self.author,
        )

        topic.refresh_from_db()
        self.assertEqual(topic.current_revision, 3)
        self.assertEqual(restored["content"], document("Alt"))
        self.assertEqual(load_current_topic(topic)["content"], document("Alt"))
        self.assertEqual(load_topic_revision(topic, 2)["content"], document("Neu"))

    def test_invalid_html_node_is_rejected(self):
        invalid = {"type": "doc", "content": [{"type": "html", "attrs": {"html": "<script>x</script>"}}]}

        with self.assertRaises(ValidationError):
            validate_prosemirror_document(invalid)

    def test_javascript_link_is_rejected(self):
        invalid = {
            "type": "doc",
            "content": [
                {
                    "type": "paragraph",
                    "content": [
                        {
                            "type": "text",
                            "text": "Link",
                            "marks": [
                                {
                                    "type": "link",
                                    "attrs": {"href": "javascript:alert(1)"},
                                }
                            ],
                        }
                    ],
                }
            ],
        }

        with self.assertRaises(ValidationError):
            validate_prosemirror_document(invalid)

    def test_extract_text_reads_plain_text_from_document(self):
        self.assertEqual(extract_text(document("Suchtext")), "Suchtext")

    def test_topic_slug_is_unique_per_web(self):
        Topic.objects.create(web=self.web, slug="same", title="Same")
        other_web = Web.objects.create(slug="projekte", title="Projekte")
        Topic.objects.create(web=other_web, slug="same", title="Same")

        with self.assertRaises(Exception):
            Topic.objects.create(web=self.web, slug="same", title="Same again")

    def test_storage_path_stays_inside_storage_root(self):
        topic = Topic.objects.create(web=self.web, slug="sicher", title="Sicher")
        path = topic_current_path(topic)

        Path(path).resolve().relative_to(Path(self.tmpdir.name).resolve())

    def test_storage_path_rejects_escaped_root(self):
        topic = Topic.objects.create(web=self.web, slug="sicher", title="Sicher")
        topic.slug = ".."

        with self.assertRaises(SuspiciousFileOperation):
            topic_current_path(topic)
