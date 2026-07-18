from django.conf import settings
from django.db import models


class AuditAction(models.TextChoices):
    LOGIN_SUCCESS = "login_success", "Login erfolgreich"
    LOGIN_FAILED = "login_failed", "Login fehlgeschlagen"
    LOGOUT = "logout", "Logout"
    USER_REGISTERED = "user_registered", "Benutzer registriert"
    USER_APPROVED = "user_approved", "Benutzer freigeschaltet"
    WEB_CREATED = "web_created", "Web erstellt"
    WEB_UPDATED = "web_updated", "Web geaendert"
    PERMISSIONS_UPDATED = "permissions_updated", "Rechte geaendert"
    TOPIC_CREATED = "topic_created", "Topic erstellt"
    TOPIC_UPDATED = "topic_updated", "Topic geaendert"
    TOPIC_DELETED = "topic_deleted", "Topic geloescht"
    REVISION_RESTORED = "revision_restored", "Revision wiederhergestellt"
    ATTACHMENT_UPLOADED = "attachment_uploaded", "Attachment hochgeladen"
    ATTACHMENT_UPDATED = "attachment_updated", "Attachment geaendert"
    ATTACHMENT_DELETED = "attachment_deleted", "Attachment geloescht"
    COMMENT_CREATED = "comment_created", "Kommentar erstellt"
    COMMENT_DELETED = "comment_deleted", "Kommentar geloescht"
    SEARCH_INDEX_UPDATED = "search_index_updated", "Suchindex aktualisiert"
    THEME_UPDATED = "theme_updated", "Theme geaendert"
    RATE_LIMIT_BLOCKED = "rate_limit_blocked", "Rate-Limit ausgeloest"


class AuditLog(models.Model):
    created_at = models.DateTimeField(auto_now_add=True, db_index=True)
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="audit_logs",
    )
    user_id_snapshot = models.BigIntegerField(blank=True, null=True)
    username = models.CharField(max_length=150, blank=True)
    ip_address = models.GenericIPAddressField(blank=True, null=True)
    user_agent = models.TextField(blank=True)
    action = models.CharField(max_length=60, choices=AuditAction.choices, db_index=True)
    web = models.ForeignKey(
        "webs.Web",
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="audit_logs",
    )
    web_slug = models.CharField(max_length=80, blank=True)
    topic = models.ForeignKey(
        "topics.Topic",
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="audit_logs",
    )
    topic_slug = models.CharField(max_length=120, blank=True)
    attachment_name = models.CharField(max_length=255, blank=True)
    old_revision = models.PositiveIntegerField(blank=True, null=True)
    new_revision = models.PositiveIntegerField(blank=True, null=True)
    old_hash = models.CharField(max_length=64, blank=True)
    new_hash = models.CharField(max_length=64, blank=True)
    details = models.JSONField(default=dict, blank=True)

    class Meta:
        ordering = ["-created_at", "-id"]
        indexes = [
            models.Index(fields=["web_slug", "topic_slug"]),
            models.Index(fields=["created_at", "action"]),
        ]
        verbose_name = "Auditlog"
        verbose_name_plural = "Auditlogs"

    def __str__(self) -> str:
        target = self.web_slug
        if self.topic_slug:
            target = f"{target}/{self.topic_slug}" if target else self.topic_slug
        return f"{self.created_at:%Y-%m-%d %H:%M:%S} {self.action} {target}".strip()

    def save(self, *args, **kwargs):
        self._sync_snapshots()
        return super().save(*args, **kwargs)

    def _sync_snapshots(self) -> None:
        if self.user:
            self.user_id_snapshot = self.user.pk
            self.username = self.username or self.user.get_username()
        if self.web:
            self.web_slug = self.web_slug or self.web.slug
        if self.topic:
            self.topic_slug = self.topic_slug or self.topic.slug
            if not self.web and self.topic.web_id:
                self.web = self.topic.web
            if not self.web_slug and self.topic.web_id:
                self.web_slug = self.topic.web.slug
