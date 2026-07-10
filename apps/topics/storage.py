import hashlib
import json
import os
from dataclasses import dataclass
from pathlib import Path

from django.conf import settings
from django.core.exceptions import SuspiciousFileOperation
from django.db import transaction
from django.utils import timezone

from apps.storage_security import ensure_private_parent, secure_private_file

from .content import validate_prosemirror_document
from .models import Topic


@dataclass(frozen=True)
class TopicRevisionResult:
    envelope: dict
    previous_revision: int
    previous_hash: str


def create_topic(
    *,
    web,
    slug: str,
    title: str,
    content: dict,
    author=None,
    change_note: str = "",
    after_save=None,
) -> Topic:
    snapshot: dict[Path, bytes | None] = {}
    try:
        with transaction.atomic():
            topic = Topic.objects.create(
                web=web,
                slug=slug,
                title=title,
                created_by=author if _is_saved_user(author) else None,
            )
            _, snapshot = _save_locked_topic_revision(
                topic=topic,
                content=content,
                author=author,
                change_note=change_note,
            )
            if after_save is not None:
                after_save(topic)
    except Exception:
        _restore_file_snapshot(snapshot)
        raise
    return topic


def save_topic_revision(
    *,
    topic: Topic,
    content: dict,
    author=None,
    change_note: str = "",
    title: str | None = None,
) -> dict:
    return save_topic_revision_result(
        topic=topic,
        content=content,
        author=author,
        change_note=change_note,
        title=title,
    ).envelope


def save_topic_revision_result(
    *,
    topic: Topic,
    content: dict,
    author=None,
    change_note: str = "",
    title: str | None = None,
    after_save=None,
) -> TopicRevisionResult:
    validate_prosemirror_document(content)
    snapshot: dict[Path, bytes | None] = {}
    locked_topic = None
    try:
        with transaction.atomic():
            locked_topic = Topic.objects.select_for_update().select_related("web").get(pk=topic.pk)
            result, snapshot = _save_locked_topic_revision(
                topic=locked_topic,
                content=content,
                author=author,
                change_note=change_note,
                title=title,
            )
            if after_save is not None:
                after_save(locked_topic, result)
    except Exception:
        _restore_file_snapshot(snapshot)
        raise

    _sync_topic_instance(topic, locked_topic)
    return result


def _save_locked_topic_revision(
    *,
    topic: Topic,
    content: dict,
    author=None,
    change_note: str = "",
    title: str | None = None,
) -> tuple[TopicRevisionResult, dict[Path, bytes | None]]:
    validate_prosemirror_document(content)
    previous_revision = topic.current_revision
    previous_hash = topic.current_hash
    revision = previous_revision + 1
    if title is not None:
        topic.title = title
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
    snapshot = _file_snapshot(revision_path, current_path)
    try:
        _write_json(revision_path, envelope)
        _write_json(current_path, envelope)

        topic.current_revision = revision
        topic.current_hash = digest
        topic.change_note = change_note
        topic.last_edited_by = author if _is_saved_user(author) else None
        topic.last_edited_at = timestamp
        update_fields = [
            "current_revision",
            "current_hash",
            "change_note",
            "last_edited_by",
            "last_edited_at",
            "updated_at",
        ]
        if title is not None:
            update_fields.append("title")
        topic.save(update_fields=update_fields)
    except Exception:
        _restore_file_snapshot(snapshot)
        raise
    return TopicRevisionResult(envelope, previous_revision, previous_hash), snapshot


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
    return restore_topic_revision_result(
        topic=topic,
        revision=revision,
        author=author,
        change_note=change_note,
    ).envelope


def restore_topic_revision_result(
    *,
    topic: Topic,
    revision: int,
    author=None,
    change_note: str = "",
    after_save=None,
) -> TopicRevisionResult:
    old_revision = load_topic_revision(topic, revision)
    note = change_note or f"Revision {revision} wiederhergestellt"
    return save_topic_revision_result(
        topic=topic,
        content=old_revision["content"],
        author=author,
        change_note=note,
        after_save=after_save,
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
    ensure_private_parent(path, settings.WIKI_STORAGE_ROOT)
    tmp_path = path.with_name(f".{path.name}.tmp")
    serialized = json.dumps(payload, ensure_ascii=False, sort_keys=True, indent=2) + "\n"
    with tmp_path.open("wb") as handle:
        handle.write(serialized.encode("utf-8"))
    secure_private_file(tmp_path)
    os.replace(tmp_path, path)


def _read_json(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))


def _file_snapshot(*paths: Path) -> dict[Path, bytes | None]:
    return {path: path.read_bytes() if path.exists() else None for path in paths}


def _restore_file_snapshot(snapshot: dict[Path, bytes | None]) -> None:
    for path, content in snapshot.items():
        if content is None:
            path.unlink(missing_ok=True)
            continue
        ensure_private_parent(path, settings.WIKI_STORAGE_ROOT)
        tmp_path = path.with_name(f".{path.name}.rollback.tmp")
        tmp_path.write_bytes(content)
        secure_private_file(tmp_path)
        os.replace(tmp_path, path)


def _sync_topic_instance(target: Topic, source: Topic) -> None:
    for field in (
        "title",
        "current_revision",
        "current_hash",
        "change_note",
        "last_edited_by",
        "last_edited_by_id",
        "last_edited_at",
        "updated_at",
    ):
        setattr(target, field, getattr(source, field))
