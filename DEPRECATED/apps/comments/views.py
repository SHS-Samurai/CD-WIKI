from django.contrib import messages
from django.core.exceptions import PermissionDenied
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.audit.models import AuditAction
from apps.audit.services import write_audit_log
from apps.topics.models import Topic
from apps.webs.permissions import user_can_comment, user_can_view

from .forms import CommentForm
from .models import Comment


@require_POST
def comment_create(request, web_slug, topic_slug):
    topic = _get_topic_for_comment_action(request, web_slug, topic_slug)
    if not user_can_comment(request.user, topic.web):
        raise PermissionDenied

    form = CommentForm(request.POST)
    if form.is_valid():
        comment = Comment.objects.create(
            topic=topic,
            author=request.user if _is_saved_user(request.user) else None,
            body=form.cleaned_data["body"],
        )
        write_audit_log(
            action=AuditAction.COMMENT_CREATED,
            user=request.user,
            request=request,
            web=topic.web,
            topic=topic,
            details={"comment_id": comment.pk},
        )
        messages.success(request, "Kommentar gespeichert.")
        return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)

    return render(
        request,
        "topics/topic_detail.html",
        _topic_detail_context(request, topic, comment_form=form),
        status=400,
    )


@require_POST
def comment_delete(request, web_slug, topic_slug, comment_id):
    topic = _get_topic_for_comment_action(request, web_slug, topic_slug)
    if not getattr(request.user, "is_staff", False):
        raise PermissionDenied

    comment = get_object_or_404(
        Comment,
        pk=comment_id,
        topic=topic,
    )
    if not comment.is_deleted:
        comment.soft_delete(deleted_by=request.user)
        write_audit_log(
            action=AuditAction.COMMENT_DELETED,
            user=request.user,
            request=request,
            web=topic.web,
            topic=topic,
            details={"comment_id": comment.pk},
        )
        messages.success(request, "Kommentar geloescht.")
    return redirect("topic_detail", web_slug=topic.web.slug, topic_slug=topic.slug)


def _get_topic_for_comment_action(request, web_slug, topic_slug):
    topic = get_object_or_404(
        Topic.objects.select_related("web"),
        web__slug=web_slug,
        slug=topic_slug,
        is_deleted=False,
    )
    if not user_can_view(request.user, topic.web):
        raise PermissionDenied
    return topic


def _topic_detail_context(request, topic, *, comment_form=None):
    from apps.topics.views import build_topic_detail_context

    return build_topic_detail_context(request, topic, comment_form=comment_form)


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))
