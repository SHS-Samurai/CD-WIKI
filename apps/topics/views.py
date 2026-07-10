from django.contrib import messages
from django.core.exceptions import ValidationError
from django.core.exceptions import PermissionDenied
from django.db import IntegrityError, transaction
from django.http import Http404
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.audit.models import AuditAction
from apps.audit.services import log_topic_revision, write_audit_log
from apps.search.services import index_topic_safely
from apps.webs.models import Web
from apps.webs.permissions import (
    user_can_comment,
    user_can_create,
    user_can_delete,
    user_can_edit,
    user_can_upload,
    user_can_view,
)

from .forms import DEFAULT_DOCUMENT, TopicForm, document_to_json
from .models import Topic
from .rendering import render_document
from .storage import (
    create_topic,
    list_topic_revisions,
    load_current_topic,
    load_topic_revision,
    restore_topic_revision,
    save_topic_revision,
)


def topic_detail(request, web_slug, topic_slug):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    return render(request, "topics/topic_detail.html", build_topic_detail_context(request, topic))


def topic_create(request, web_slug):
    web = get_object_or_404(Web, slug=web_slug)
    if not user_can_view(request.user, web) or not user_can_create(request.user, web):
        raise PermissionDenied

    if request.method == "POST":
        form = TopicForm(request.POST)
        if form.is_valid():
            try:
                with transaction.atomic():
                    topic = create_topic(
                        web=web,
                        slug=form.cleaned_data["slug"],
                        title=form.cleaned_data["title"],
                        content=form.cleaned_data["content_json"],
                        author=request.user,
                        change_note=form.cleaned_data["change_note"],
                    )
                    log_topic_revision(
                        topic=topic,
                        user=request.user,
                        request=request,
                        action=AuditAction.TOPIC_CREATED,
                        old_revision=None,
                        new_revision=topic.current_revision,
                        old_hash="",
                        new_hash=topic.current_hash,
                        details={"change_note": topic.change_note},
                    )
            except (IntegrityError, ValidationError):
                form.add_error("slug", "Dieser Slug ist in diesem Web bereits vergeben.")
            else:
                _update_search_index(topic, request, source="topic_created")
                messages.success(request, "Topic gespeichert.")
                return redirect("topic_detail", web_slug=web.slug, topic_slug=topic.slug)
    else:
        form = TopicForm(
            initial={
                "content_json": document_to_json(DEFAULT_DOCUMENT),
            }
        )

    return render(
        request,
        "topics/topic_form.html",
        {
            "form": form,
            "web": web,
            "topic": None,
            "mode": "create",
            "attachments": (),
        },
    )


def topic_edit(request, web_slug, topic_slug):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    if not user_can_edit(request.user, topic.web):
        raise PermissionDenied

    if request.method == "POST":
        form = TopicForm(request.POST, include_slug=False)
        if form.is_valid():
            old_revision = topic.current_revision
            old_hash = topic.current_hash
            with transaction.atomic():
                topic.title = form.cleaned_data["title"]
                topic.save(update_fields=["title", "updated_at"])
                save_topic_revision(
                    topic=topic,
                    content=form.cleaned_data["content_json"],
                    author=request.user,
                    change_note=form.cleaned_data["change_note"],
                )
                log_topic_revision(
                    topic=topic,
                    user=request.user,
                    request=request,
                    action=AuditAction.TOPIC_UPDATED,
                    old_revision=old_revision,
                    new_revision=topic.current_revision,
                    old_hash=old_hash,
                    new_hash=topic.current_hash,
                    details={"change_note": topic.change_note},
                )
            _update_search_index(topic, request, source="topic_updated")
            messages.success(request, "Topic gespeichert.")
            return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)
    else:
        envelope = load_current_topic(topic)
        form = TopicForm(
            include_slug=False,
            initial={
                "title": topic.title,
                "content_json": document_to_json(envelope["content"]),
                "change_note": "",
            },
        )

    return render(
        request,
        "topics/topic_form.html",
        {
            "form": form,
            "web": topic.web,
            "topic": topic,
            "mode": "edit",
            "attachments": topic.attachments.filter(is_deleted=False),
        },
    )


def topic_revisions(request, web_slug, topic_slug):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    revisions = _safe_list_revisions(topic)
    return render(
        request,
        "topics/revision_list.html",
        {
            "topic": topic,
            "web": topic.web,
            "revisions": revisions,
            "can_restore": user_can_edit(request.user, topic.web),
        },
    )


def topic_revision_detail(request, web_slug, topic_slug, revision):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    envelope = _safe_load_revision(topic, revision)
    return render(
        request,
        "topics/revision_detail.html",
        {
            "topic": topic,
            "web": topic.web,
            "revision": envelope,
            "content_html": render_document(envelope["content"]),
            "can_restore": user_can_edit(request.user, topic.web),
        },
    )


@require_POST
def topic_revision_restore(request, web_slug, topic_slug, revision):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    if not user_can_edit(request.user, topic.web):
        raise PermissionDenied

    _safe_load_revision(topic, revision)
    old_revision = topic.current_revision
    old_hash = topic.current_hash
    with transaction.atomic():
        restore_topic_revision(
            topic=topic,
            revision=revision,
            author=request.user,
        )
        log_topic_revision(
            topic=topic,
            user=request.user,
            request=request,
            action=AuditAction.REVISION_RESTORED,
            old_revision=old_revision,
            new_revision=topic.current_revision,
            old_hash=old_hash,
            new_hash=topic.current_hash,
            details={"restored_revision": revision, "change_note": topic.change_note},
        )
    _update_search_index(topic, request, source="revision_restored")
    messages.success(request, "Revision wiederhergestellt.")
    return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)


@require_POST
def topic_delete(request, web_slug, topic_slug):
    topic = _get_visible_topic(request, web_slug, topic_slug)
    if not user_can_delete(request.user, topic.web):
        raise PermissionDenied

    topic.is_deleted = True
    topic.save(update_fields=["is_deleted", "updated_at"])
    write_audit_log(
        action=AuditAction.TOPIC_DELETED,
        user=request.user,
        request=request,
        web=topic.web,
        topic=topic,
        old_revision=topic.current_revision,
        old_hash=topic.current_hash,
        details={"source": "topic_delete"},
    )
    _update_search_index(topic, request, source="topic_deleted")
    messages.success(request, "Topic in den Papierkorb verschoben.")
    return redirect("web_detail", web_slug=topic.web.slug)


def topic_trash(request):
    if not _is_staff_user(request.user):
        raise PermissionDenied

    topics = Topic.objects.select_related("web", "last_edited_by").filter(is_deleted=True)
    return render(request, "topics/trash_list.html", {"topics": topics})


@require_POST
def topic_restore(request, topic_id):
    if not _is_staff_user(request.user):
        raise PermissionDenied

    topic = get_object_or_404(
        Topic.objects.select_related("web", "last_edited_by"),
        pk=topic_id,
        is_deleted=True,
    )
    topic.is_deleted = False
    topic.save(update_fields=["is_deleted", "updated_at"])
    write_audit_log(
        action=AuditAction.TOPIC_UPDATED,
        user=request.user,
        request=request,
        web=topic.web,
        topic=topic,
        new_revision=topic.current_revision,
        new_hash=topic.current_hash,
        details={"source": "topic_restored"},
    )
    _update_search_index(topic, request, source="topic_restored")
    messages.success(request, "Topic wiederhergestellt.")
    return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)


def _get_visible_topic(request, web_slug, topic_slug):
    topic = get_object_or_404(
        Topic.objects.select_related("web", "last_edited_by"),
        web__slug=web_slug,
        slug=topic_slug,
        is_deleted=False,
    )
    if not user_can_view(request.user, topic.web):
        raise PermissionDenied
    return topic


def _safe_load_revision(topic, revision):
    if revision < 1 or revision > topic.current_revision:
        raise Http404("Revision nicht gefunden.")
    try:
        return load_topic_revision(topic, revision)
    except FileNotFoundError as exc:
        raise Http404("Revision nicht gefunden.") from exc


def _safe_list_revisions(topic):
    try:
        return list_topic_revisions(topic)
    except FileNotFoundError as exc:
        raise Http404("Revisionen nicht gefunden.") from exc


def build_topic_detail_context(request, topic, *, comment_form=None):
    from apps.comments.forms import CommentForm

    envelope = load_current_topic(topic)
    can_comment = user_can_comment(request.user, topic.web)
    return {
        "topic": topic,
        "web": topic.web,
        "attachments": topic.attachments.filter(is_deleted=False),
        "comments": topic.comments.select_related("author", "deleted_by"),
        "comment_form": comment_form or (CommentForm() if can_comment else None),
        "content_html": render_document(envelope["content"]),
        "can_edit": user_can_edit(request.user, topic.web),
        "can_comment": can_comment,
        "can_delete_comments": getattr(request.user, "is_staff", False),
        "can_delete": user_can_delete(request.user, topic.web),
        "can_upload": user_can_upload(request.user, topic.web),
    }


def _is_staff_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "is_staff", False))


def _update_search_index(topic, request, *, source: str) -> None:
    if index_topic_safely(topic):
        write_audit_log(
            action=AuditAction.SEARCH_INDEX_UPDATED,
            user=request.user,
            request=request,
            web=topic.web,
            topic=topic,
            details={"source": source},
        )
