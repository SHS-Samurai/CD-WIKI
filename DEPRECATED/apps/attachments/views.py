from django.contrib import messages
from django.core.exceptions import PermissionDenied, SuspiciousFileOperation, ValidationError
from django.http import FileResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.audit.models import AuditAction
from apps.audit.services import write_audit_log
from apps.search.services import index_topic_safely
from apps.topics.models import Topic
from apps.webs.permissions import user_can_delete, user_can_upload, user_can_view

from .forms import AttachmentUploadForm
from .models import Attachment
from .storage import attachment_current_path, attachment_storage_name, save_attachment_revision


def attachment_upload(request, web_slug, topic_slug):
    topic = get_object_or_404(
        Topic.objects.select_related("web"),
        web__slug=web_slug,
        slug=topic_slug,
        is_deleted=False,
    )
    if not user_can_view(request.user, topic.web) or not user_can_upload(request.user, topic.web):
        raise PermissionDenied

    if request.method == "POST":
        form = AttachmentUploadForm(request.POST, request.FILES)
        if form.is_valid():
            try:
                storage_name = attachment_storage_name(request.FILES["file"].name)
            except (SuspiciousFileOperation, ValidationError) as exc:
                form.add_error("file", exc)
                storage_name = ""
            old_attachment = Attachment.objects.filter(
                topic=topic,
                storage_name=storage_name,
            ).first() if storage_name else None
            old_revision = old_attachment.current_revision if old_attachment else None
            old_hash = old_attachment.current_hash if old_attachment else ""
            if not form.errors:
                try:
                    attachment = save_attachment_revision(
                        topic=topic,
                        uploaded_file=request.FILES["file"],
                        author=request.user,
                        change_note=form.cleaned_data["change_note"],
                    )
                except (SuspiciousFileOperation, ValidationError) as exc:
                    form.add_error("file", exc)
                except OSError as exc:
                    form.add_error("file", str(exc))
                else:
                    write_audit_log(
                        action=(
                            AuditAction.ATTACHMENT_UPDATED
                            if old_revision
                            else AuditAction.ATTACHMENT_UPLOADED
                        ),
                        user=request.user,
                        request=request,
                        web=topic.web,
                        topic=topic,
                        attachment_name=attachment.original_filename,
                        old_revision=old_revision,
                        new_revision=attachment.current_revision,
                        old_hash=old_hash,
                        new_hash=attachment.current_hash,
                        details={"change_note": attachment.change_note},
                    )
                    _update_search_index(topic, request, attachment, source="attachment_saved")
                    messages.success(request, "Datei gespeichert.")
                    return redirect(
                        "topic_detail",
                        web_slug=topic.web.slug,
                        topic_slug=topic.slug,
                    )
    else:
        form = AttachmentUploadForm()

    return render(
        request,
        "attachments/upload.html",
        {
            "form": form,
            "topic": topic,
            "web": topic.web,
        },
    )


def attachment_download(request, web_slug, topic_slug, attachment_id):
    attachment = get_object_or_404(
        Attachment.objects.select_related("topic__web"),
        pk=attachment_id,
        topic__web__slug=web_slug,
        topic__slug=topic_slug,
        topic__is_deleted=False,
        is_deleted=False,
    )
    if not user_can_view(request.user, attachment.topic.web):
        raise PermissionDenied

    path = attachment_current_path(attachment)
    return FileResponse(
        path.open("rb"),
        as_attachment=True,
        filename=attachment.original_filename,
        content_type=attachment.content_type or "application/octet-stream",
    )


@require_POST
def attachment_delete(request, web_slug, topic_slug, attachment_id):
    attachment = get_object_or_404(
        Attachment.objects.select_related("topic__web"),
        pk=attachment_id,
        topic__web__slug=web_slug,
        topic__slug=topic_slug,
        topic__is_deleted=False,
        is_deleted=False,
    )
    topic = attachment.topic
    if not user_can_view(request.user, topic.web) or not user_can_delete(request.user, topic.web):
        raise PermissionDenied

    attachment.is_deleted = True
    attachment.save(update_fields=["is_deleted", "updated_at"])
    write_audit_log(
        action=AuditAction.ATTACHMENT_DELETED,
        user=request.user,
        request=request,
        web=topic.web,
        topic=topic,
        attachment_name=attachment.original_filename,
        old_revision=attachment.current_revision,
        old_hash=attachment.current_hash,
        details={"source": "attachment_delete"},
    )
    _update_search_index(topic, request, attachment, source="attachment_deleted")
    messages.success(request, "Datei in den Papierkorb verschoben.")
    return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)


def attachment_trash(request):
    if not _is_staff_user(request.user):
        raise PermissionDenied

    attachments = Attachment.objects.select_related(
        "topic__web",
        "updated_by",
    ).filter(
        is_deleted=True,
        topic__is_deleted=False,
    )
    return render(request, "attachments/trash_list.html", {"attachments": attachments})


@require_POST
def attachment_restore(request, attachment_id):
    if not _is_staff_user(request.user):
        raise PermissionDenied

    attachment = get_object_or_404(
        Attachment.objects.select_related("topic__web"),
        pk=attachment_id,
        is_deleted=True,
        topic__is_deleted=False,
    )
    topic = attachment.topic
    attachment.is_deleted = False
    attachment.save(update_fields=["is_deleted", "updated_at"])
    write_audit_log(
        action=AuditAction.ATTACHMENT_UPDATED,
        user=request.user,
        request=request,
        web=topic.web,
        topic=topic,
        attachment_name=attachment.original_filename,
        new_revision=attachment.current_revision,
        new_hash=attachment.current_hash,
        details={"source": "attachment_restored"},
    )
    _update_search_index(topic, request, attachment, source="attachment_restored")
    messages.success(request, "Datei wiederhergestellt.")
    return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)


def _update_search_index(topic, request, attachment, *, source: str) -> None:
    if index_topic_safely(topic):
        write_audit_log(
            action=AuditAction.SEARCH_INDEX_UPDATED,
            user=request.user,
            request=request,
            web=topic.web,
            topic=topic,
            attachment_name=attachment.original_filename,
            details={"source": source},
        )


def _is_staff_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "is_staff", False))
