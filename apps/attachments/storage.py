import hashlib
import json
import mimetypes
import os
from pathlib import Path, PurePath

from django.conf import settings
from django.core.exceptions import SuspiciousFileOperation, ValidationError
from django.db import transaction
from django.utils import timezone
from django.utils.text import get_valid_filename

from .models import Attachment


ALLOWED_MIME_TYPES_BY_EXTENSION = {
    ".pdf": {"application/pdf", "application/octet-stream"},
    ".docx": {
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/octet-stream",
    },
    ".txt": {"text/plain", "application/octet-stream"},
    ".md": {"text/markdown", "text/plain", "application/octet-stream"},
    ".xlsx": {
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/octet-stream",
    },
    ".html": {"text/html", "application/xhtml+xml", "application/octet-stream"},
}


def save_attachment_revision(
    *,
    topic,
    uploaded_file,
    author=None,
    change_note: str = "",
) -> Attachment:
    original_filename = clean_attachment_filename(uploaded_file.name)
    storage_name = attachment_storage_name(uploaded_file.name)
    _validate_upload(uploaded_file, storage_name)

    content_bytes = _read_upload(uploaded_file)
    digest = hashlib.sha256(content_bytes).hexdigest()
    content_type = _content_type(uploaded_file, storage_name)

    with transaction.atomic():
        attachment, created = Attachment.objects.select_for_update().get_or_create(
            topic=topic,
            storage_name=storage_name,
            defaults={
                "original_filename": original_filename,
                "uploaded_by": author if _is_saved_user(author) else None,
            },
        )
        revision = attachment.current_revision + 1
        timestamp = timezone.now()

        _write_binary(attachment_revision_path(attachment, revision), content_bytes)
        _write_binary(attachment_current_path(attachment), content_bytes)

        attachment.original_filename = original_filename
        attachment.content_type = content_type
        attachment.size = len(content_bytes)
        attachment.current_revision = revision
        attachment.current_hash = digest
        attachment.change_note = change_note
        attachment.updated_by = author if _is_saved_user(author) else None
        if created and _is_saved_user(author):
            attachment.uploaded_by = author
        attachment.is_deleted = False
        attachment.save()

        _write_meta(attachment, timestamp)
        return attachment


def attachment_directory(attachment: Attachment) -> Path:
    return _safe_storage_path(
        "webs",
        attachment.topic.web.slug,
        "topics",
        attachment.topic.slug,
        "attachments",
        attachment.storage_name,
    )


def attachment_current_path(attachment: Attachment) -> Path:
    return attachment_directory(attachment) / "current.bin"


def attachment_meta_path(attachment: Attachment) -> Path:
    return attachment_directory(attachment) / "meta.json"


def attachment_revision_path(attachment: Attachment, revision: int) -> Path:
    if revision < 1:
        raise ValueError("Attachment-Revisionen beginnen bei 1.")
    return attachment_directory(attachment) / "revisions" / f"{revision:06d}.bin"


def clean_attachment_filename(filename: str) -> str:
    if "/" in filename or "\\" in filename:
        raise SuspiciousFileOperation("Dateiname darf keinen Pfad enthalten.")
    basename = PurePath(filename).name
    if basename != filename:
        raise SuspiciousFileOperation("Dateiname darf keinen Pfad enthalten.")
    safe_name = get_valid_filename(basename)
    if not safe_name or safe_name in {".", ".."}:
        raise ValidationError("Ungueltiger Dateiname.")
    return safe_name


def attachment_storage_name(filename: str) -> str:
    return clean_attachment_filename(filename).lower()


def _validate_upload(uploaded_file, storage_name: str) -> None:
    extension = Path(storage_name).suffix.lower()
    if extension in settings.WIKI_BLOCKED_ATTACHMENT_EXTENSIONS:
        raise ValidationError("Dieser Dateityp ist nicht erlaubt.")
    if extension not in settings.WIKI_ALLOWED_ATTACHMENT_EXTENSIONS:
        raise ValidationError("Dieser Dateityp ist nicht freigegeben.")
    if uploaded_file.size > settings.WIKI_MAX_ATTACHMENT_SIZE:
        raise ValidationError("Die Datei ist zu gross.")
    content_type = (getattr(uploaded_file, "content_type", "") or "").split(";", 1)[0].lower()
    allowed_mime_types = ALLOWED_MIME_TYPES_BY_EXTENSION.get(extension, set())
    if content_type and content_type not in allowed_mime_types:
        raise ValidationError("Der MIME-Type passt nicht zum freigegebenen Dateityp.")
    if "/" in storage_name or "\\" in storage_name or storage_name in {".", ".."}:
        raise SuspiciousFileOperation("Ungueltiger Dateipfad.")


def _read_upload(uploaded_file) -> bytes:
    chunks = uploaded_file.chunks() if hasattr(uploaded_file, "chunks") else [uploaded_file.read()]
    return b"".join(chunks)


def _content_type(uploaded_file, storage_name: str) -> str:
    explicit = getattr(uploaded_file, "content_type", "") or ""
    guessed, _ = mimetypes.guess_type(storage_name)
    return explicit or guessed or "application/octet-stream"


def _write_binary(path: Path, content: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_name(f".{path.name}.tmp")
    with tmp_path.open("wb") as handle:
        handle.write(content)
    os.replace(tmp_path, path)


def _write_meta(attachment: Attachment, timestamp) -> None:
    payload = {
        "web": attachment.topic.web.slug,
        "topic": attachment.topic.slug,
        "filename": attachment.original_filename,
        "storage_name": attachment.storage_name,
        "content_type": attachment.content_type,
        "size": attachment.size,
        "current_revision": attachment.current_revision,
        "current_hash": attachment.current_hash,
        "change_note": attachment.change_note,
        "updated_at": timestamp.isoformat(),
        "updated_by_id": attachment.updated_by_id,
        "updated_by_username": getattr(attachment.updated_by, "username", ""),
    }
    path = attachment_meta_path(attachment)
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_name(f".{path.name}.tmp")
    with tmp_path.open("w", encoding="utf-8", newline="\n") as handle:
        json.dump(payload, handle, ensure_ascii=False, sort_keys=True, indent=2)
        handle.write("\n")
    os.replace(tmp_path, path)


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


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))
