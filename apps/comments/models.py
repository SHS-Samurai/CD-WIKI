from django.conf import settings
from django.db import models
from django.utils import timezone


class Comment(models.Model):
    topic = models.ForeignKey(
        "topics.Topic",
        on_delete=models.CASCADE,
        related_name="comments",
    )
    author = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="wiki_comments",
    )
    author_username = models.CharField(max_length=150, blank=True)
    body = models.TextField()
    is_deleted = models.BooleanField(default=False, db_index=True)
    deleted_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="deleted_wiki_comments",
    )
    deleted_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True, db_index=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["created_at", "id"]
        indexes = [
            models.Index(fields=["topic", "is_deleted", "created_at"]),
        ]
        verbose_name = "Kommentar"
        verbose_name_plural = "Kommentare"

    def __str__(self) -> str:
        return f"Kommentar {self.pk or 'neu'} zu {self.topic}"

    def save(self, *args, **kwargs):
        if self.author and not self.author_username:
            self.author_username = self.author.get_username()
        return super().save(*args, **kwargs)

    def soft_delete(self, *, deleted_by=None) -> None:
        self.is_deleted = True
        self.deleted_by = deleted_by if _is_saved_user(deleted_by) else None
        self.deleted_at = timezone.now()
        self.save(update_fields=["is_deleted", "deleted_by", "deleted_at", "updated_at"])


def _is_saved_user(user) -> bool:
    return bool(getattr(user, "is_authenticated", False) and getattr(user, "pk", None))
