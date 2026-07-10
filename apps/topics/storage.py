import hashlib
import json
import os
from pathlib import Path

from django.conf import settings
from django.core.exceptions import SuspiciousFileOperation
from django.db import transaction
from django.utils import timezone

from .content import validate_prosemirror_document
from .models import Topic


def create_topic(
    *,
    web,
    slug: str,
    title: str,
    content: dict,
    author=None,
    change_note: str = "",
) -> Topic:
    with transaction.atomic():
        topic = Topic.objects.create(
            web=web,
            slug=slug,
            title=title,
            created_by=author if _is_saved_user(author) else None,
        )
        save_topic_revision(
            topic=topic,
            content=content,
            author=author,
            change_note=change_note,
        )
        return topic


def save_topic_revision(
    *,
    topic: Topic,
    content: dict,
    author=None,
    change_note: str = "",
) -> dict:
    validate_prosemirror_document(content)

    revision = topic.current_revision + 1
    timestamp = timezone.now()
    digest = content_hash(content)
    envelope = _revision_envelope(
        topic=topic,
        content=content,
        author=author,
        revision=revision,
        timestamp=timestamp,
        change_note=change_note,
        digest=digest,
    )

    revision_path = topic_revision_path(topic, revision)
    current_path = topic_current_path(topic)
    _write_json(revision_path, envelope)
    _write_json(current_path, envelope)

    topic.current_revision = revision
    topic.current_hash = digest
    topic.change_note = change_note
    topic.last_edited_by = author if _is_saved_user(author) else None
    topic.last_edited_at = timestamp
    topic.save(
        update_fields=[
            "current_revision",
            "current_hash",
            "change_note",
            "last_edited_by",
            "last_edited_at",
            "updated_at",
        ]
    )
    return envelope


def load_current_topic(topic: Topic) -> dict:
    return _read_json(topic_current_path(topic))


def load_topic_revision(topic: Topic, revision: int) -> dict:
    return _read_json(topic_revision_path(topic, revision))


def list_topic_revisions(topic: Topic) -> list[dict]:
    revisions: list[dict] = []
    for revision in range(1, topic.current_revision + 1):
        envelope = load_topic_revision(topic, revision)
        revisions.append(
            {
                "revision": envelope["revision"],
                "title": envelope["title"],
                "created_at": envelope["created_at"],
                "author_username": envelope["author_username"],
                "change_note": envelope["change_note"],
                "content_hash": envelope["content_hash"],
            }
        )
    return list(reversed(revisions))


def restore_topic_revision(
    *,
    topic: Topic,
    revision: int,
    author=None,
    change_note: str = "",
) -> dict:
    old_revision = load_topic_revision(topic, revision)
    note = change_note or f"Revision {revision} wiederhergestellt"
    return save_topic_revision(
        topic=topic,
        content=old_revision["content"],
        author=author,
        change_note=note,
    )


def content_hash(content: dict) -> str:
    canonical = json.dumps(content, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


def topic_directory(topic: Topic) -> Path:
    return _safe_storage_path("webs", topic.web.slug, "topics", topic.slug)


def topic_current_path(topic: Topic) -> Path:
    return topic_directory(topic) / "current.json"


def topic_revision_path(topic: Topic, revision: int) -> Path:
    if revision < 1:
        raise ValueError("Revisionen beginnen bei 1.")
    return topic_directory(topic) / "revisions" / f"{revision:06d}.json"


def _revision_envelope(
    *,
    topic: Topic,
    content: dict,
    author,
    revision: int,
    timestamp,
    change_note: str,
    digest: str,
) -> dict:
    return {
        "web": topic.web.slug,
        "topic": topic.slug,
        "title": topic.title,
        "revision": revision,
        "created_at": timestamp.isoformat(),
        "author_id": author.pk if _is_saved_user(author) else None,
        "author_username": getattr(author, "username", ""),
        "change_note": change_note,
        "content_hash": digest,
        "content": content,
    }


def _safe_storage_path(*parts: str) -> Path:
    for part in parts:
        if part in {".", ".."} or "/" in part or "\\" in part:
            raise SuspiciousFileOperation("Ungueltiger Storage-Pfadbestandteil.")

    root = Path(settings.WIKI_STORAGE_ROOT).resolve()
    path = root.joinpath(*parts).resolve(strict=False)
    try:
        path.relative_to(root)
    except ValueError as exc:
        raise SuspiciousFileOperation("Storage-Pfad liegt ausserhalb des Storage-Roots.") from exc
    return path


def _write_json(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_name(f".{path.name}.tmp")
    with tmp_path.open("w", encoding="utf-8", newline="\n") as handle:
        json.dump(payload, handle, ensure_ascii=False, sort_keys=True, indent=2)
        handle.write("\n")
    os.replace(tmp_path, path)


def _read_json(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))
