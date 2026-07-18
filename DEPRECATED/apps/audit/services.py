from __future__ import annotations

from typing import Any

from django.http import HttpRequest

from apps.request_metadata import get_client_ip

from .models import AuditAction, AuditLog


def write_audit_log(
    *,
    action: str,
    user=None,
    request: HttpRequest | None = None,
    web=None,
    topic=None,
    attachment_name: str = "",
    old_revision: int | None = None,
    new_revision: int | None = None,
    old_hash: str = "",
    new_hash: str = "",
    details: dict[str, Any] | None = None,
    username: str = "",
) -> AuditLog:
    if request is not None:
        user = user or getattr(request, "user", None)

    log = AuditLog(
        user=user if _is_saved_user(user) else None,
        username=username or _username(user),
        ip_address=get_client_ip(request) if request is not None else None,
        user_agent=_user_agent(request),
        action=action,
        web=web,
        topic=topic,
        attachment_name=attachment_name,
        old_revision=old_revision,
        new_revision=new_revision,
        old_hash=old_hash,
        new_hash=new_hash,
        details=details or {},
    )
    log.save()
    return log


def log_topic_revision(
    *,
    topic,
    user=None,
    request: HttpRequest | None = None,
    action: str = AuditAction.TOPIC_UPDATED,
    old_revision: int | None = None,
    new_revision: int | None = None,
    old_hash: str = "",
    new_hash: str = "",
    details: dict[str, Any] | None = None,
) -> AuditLog:
    return write_audit_log(
        action=action,
        user=user,
        request=request,
        web=topic.web,
        topic=topic,
        old_revision=old_revision,
        new_revision=new_revision,
        old_hash=old_hash,
        new_hash=new_hash,
        details=details,
    )


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))


def _username(user) -> str:
    if not user:
        return ""
    username = getattr(user, "get_username", lambda: "")()
    return username or getattr(user, "username", "")


def _user_agent(request: HttpRequest | None) -> str:
    if request is None:
        return ""
    return request.META.get("HTTP_USER_AGENT", "")
