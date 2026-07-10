from apps.attachments.storage import attachment_current_path
from apps.topics.content import extract_text
from apps.topics.models import Topic
from apps.topics.storage import load_current_topic

from .extractors import extract_attachment_text


def build_topic_document(topic: Topic) -> dict:
    envelope = load_current_topic(topic)
    attachments = list(topic.attachments.filter(is_deleted=False))
    attachment_names = [attachment.original_filename for attachment in attachments]
    attachment_text_parts = [
        extract_attachment_text(
            attachment_current_path(attachment),
            attachment.storage_name,
            attachment.content_type,
        )
        for attachment in attachments
    ]

    return {
        "id": str(topic.pk),
        "topic_id": topic.pk,
        "web_id": topic.web_id,
        "web_slug": topic.web.slug,
        "web_title": topic.web.title,
        "topic_slug": topic.slug,
        "topic_title": topic.title,
        "wiki_path": topic.wiki_path,
        "content_text": extract_text(envelope["content"]),
        "attachment_names": "\n".join(attachment_names),
        "attachment_text": "\n".join(part for part in attachment_text_parts if part),
        "last_edited_at": topic.last_edited_at.isoformat() if topic.last_edited_at else "",
        "last_editor_username": topic.last_edited_by.get_username() if topic.last_edited_by else "",
        "current_revision": topic.current_revision,
        "change_note": topic.change_note,
        "is_deleted": topic.is_deleted,
    }
