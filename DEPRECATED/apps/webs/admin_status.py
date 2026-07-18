import json
import os
from pathlib import Path
from urllib import error as urlerror
from urllib import request as urlrequest

from django.apps import apps
from django.conf import settings
from django.contrib.auth import get_user_model
from django.db import connection
from django.utils import timezone

from apps.accounts.models import RateLimitBucket, RegistrationMode, RegistrationSettings
from apps.attachments.models import Attachment
from apps.attachments.storage import ALLOWED_MIME_TYPES_BY_EXTENSION
from apps.audit.models import AuditAction, AuditLog
from apps.topics.models import Topic

from .models import Web


PLUGIN_HOOKS = (
    "on_topic_before_save",
    "on_topic_after_save",
    "on_topic_render",
    "on_attachment_uploaded",
    "on_attachment_deleted",
    "on_search_index",
    "on_user_registered",
    "admin_menu_items",
    "topic_toolbar_items",
)


def dashboard_metrics() -> dict:
    return {
        "user_count": get_user_model().objects.count(),
        "web_count": Web.objects.count(),
        "topic_count": Topic.objects.filter(is_deleted=False).count(),
        "attachment_count": Attachment.objects.filter(is_deleted=False).count(),
        "active_rate_limits": RateLimitBucket.objects.filter(
            blocked_until__gt=timezone.now()
        ).count(),
        "registration_mode": _registration_mode_label(),
    }


def system_status() -> dict:
    return {
        "checks": [
            _database_status(),
            _storage_status(),
            {
                "name": "Debug-Modus",
                "state": "warning" if settings.DEBUG else "ok",
                "detail": "aktiv" if settings.DEBUG else "deaktiviert",
            },
            {
                "name": "Sichere Session-Cookies",
                "state": "ok" if settings.SESSION_COOKIE_SECURE else "warning",
                "detail": "aktiv" if settings.SESSION_COOKIE_SECURE else "deaktiviert",
            },
            {
                "name": "HTTPS-Weiterleitung",
                "state": "ok" if settings.SECURE_SSL_REDIRECT else "warning",
                "detail": "aktiv" if settings.SECURE_SSL_REDIRECT else "deaktiviert",
            },
            {
                "name": "HSTS",
                "state": "ok" if settings.SECURE_HSTS_SECONDS else "warning",
                "detail": f"{settings.SECURE_HSTS_SECONDS} Sekunden",
            },
        ],
        "metrics": dashboard_metrics(),
        "database_name": settings.DATABASES["default"]["NAME"],
        "email_backend": settings.EMAIL_BACKEND,
    }


def search_status() -> dict:
    health = _meilisearch_health()
    last_index_update = AuditLog.objects.filter(
        action=AuditAction.SEARCH_INDEX_UPDATED
    ).first()
    return {
        "health": health,
        "url": settings.MEILISEARCH["URL"],
        "index_name": settings.WIKI_SEARCH_INDEX_NAME,
        "topic_count": Topic.objects.filter(is_deleted=False).count(),
        "attachment_count": Attachment.objects.filter(is_deleted=False).count(),
        "last_index_update": last_index_update,
        "master_key_configured": bool(settings.MEILISEARCH.get("MASTER_KEY")),
    }


def file_type_status() -> dict:
    allowed = []
    for extension in sorted(settings.WIKI_ALLOWED_ATTACHMENT_EXTENSIONS):
        allowed.append(
            {
                "extension": extension,
                "mime_types": sorted(ALLOWED_MIME_TYPES_BY_EXTENSION.get(extension, set())),
            }
        )
    return {
        "allowed": allowed,
        "blocked": sorted(settings.WIKI_BLOCKED_ATTACHMENT_EXTENSIONS),
        "max_size_mb": round(settings.WIKI_MAX_ATTACHMENT_SIZE / (1024 * 1024), 1),
    }


def extension_status() -> dict:
    installed = [
        config
        for config in apps.get_app_configs()
        if config.name.startswith("apps.plugins.")
    ]
    return {"installed": installed, "hooks": PLUGIN_HOOKS}


def _database_status() -> dict:
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT 1")
            cursor.fetchone()
    except Exception as exc:
        return {"name": "MySQL", "state": "error", "detail": str(exc)}
    return {"name": "MySQL", "state": "ok", "detail": "erreichbar"}


def _storage_status() -> dict:
    root = Path(settings.WIKI_STORAGE_ROOT)
    available = root.is_dir() and os.access(root, os.R_OK | os.W_OK)
    return {
        "name": "Storage",
        "state": "ok" if available else "error",
        "detail": str(root.resolve()),
    }


def _meilisearch_health() -> dict:
    url = f"{settings.MEILISEARCH['URL'].rstrip('/')}/health"
    headers = {}
    if settings.MEILISEARCH.get("MASTER_KEY"):
        headers["Authorization"] = f"Bearer {settings.MEILISEARCH['MASTER_KEY']}"
    health_request = urlrequest.Request(url, headers=headers)
    try:
        with urlrequest.urlopen(health_request, timeout=2) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except (OSError, ValueError, urlerror.URLError) as exc:
        return {"state": "error", "detail": str(exc)}
    status = payload.get("status", "unknown")
    return {
        "state": "ok" if status == "available" else "warning",
        "detail": status,
    }


def _registration_mode_label() -> str:
    row = RegistrationSettings.objects.filter(pk=1).first()
    mode = row.mode if row else settings.WIKI_REGISTRATION_MODE
    return dict(RegistrationMode.choices).get(mode, mode)
