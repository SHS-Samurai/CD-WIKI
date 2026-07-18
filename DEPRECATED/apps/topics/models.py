from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models

from apps.webs.models import Web


class Topic(models.Model):
    web = models.ForeignKey(
        Web,
        on_delete=models.CASCADE,
        related_name="topics",
    )
    slug = models.SlugField(max_length=120)
    title = models.CharField(max_length=180)
    current_revision = models.PositiveIntegerField(default=0)
    current_hash = models.CharField(max_length=64, blank=True)
    change_note = models.CharField(max_length=255, blank=True)
    last_edited_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="edited_topics",
    )
    last_edited_at = models.DateTimeField(blank=True, null=True)
    is_deleted = models.BooleanField(default=False)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="created_topics",
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["web__slug", "slug"]
        constraints = [
            models.UniqueConstraint(
                fields=["web", "slug"],
                name="unique_topic_slug_per_web",
            )
        ]
        verbose_name = "Topic"
        verbose_name_plural = "Topics"

    def __str__(self) -> str:
        return f"{self.web.slug}/{self.slug}"

    @property
    def wiki_path(self) -> str:
        return f"{self.web.slug}/{self.slug}"

    def clean(self) -> None:
        super().clean()
        self.slug = self.slug.lower()
        if self.slug in {".", ".."}:
            raise ValidationError({"slug": "Ungueltiger Topic-Slug."})

    def save(self, *args, **kwargs):
        self.full_clean()
        return super().save(*args, **kwargs)
