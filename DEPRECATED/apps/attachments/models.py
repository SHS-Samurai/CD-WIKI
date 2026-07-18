from django.conf import settings
from django.db import models


class Attachment(models.Model):
    topic = models.ForeignKey(
        "topics.Topic",
        on_delete=models.CASCADE,
        related_name="attachments",
    )
    original_filename = models.CharField(max_length=255)
    storage_name = models.CharField(max_length=255)
    content_type = models.CharField(max_length=120, blank=True)
    size = models.PositiveBigIntegerField(default=0)
    current_revision = models.PositiveIntegerField(default=0)
    current_hash = models.CharField(max_length=64, blank=True)
    change_note = models.CharField(max_length=255, blank=True)
    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="uploaded_attachments",
    )
    updated_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="updated_attachments",
    )
    is_deleted = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["topic__web__slug", "topic__slug", "storage_name"]
        constraints = [
            models.UniqueConstraint(
                fields=["topic", "storage_name"],
                name="unique_attachment_name_per_topic",
            )
        ]
        verbose_name = "Dateianhang"
        verbose_name_plural = "Dateianhaenge"

    def __str__(self) -> str:
        return f"{self.topic.wiki_path}/{self.original_filename}"
