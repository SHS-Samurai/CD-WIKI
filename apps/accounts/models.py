from django.contrib.auth.models import AbstractUser
from django.conf import settings
from django.db import models
from django.utils import timezone


class RegistrationMode(models.TextChoices):
    DISABLED = "disabled", "Registrierung deaktiviert"
    ADMIN_APPROVAL = "admin_approval", "Registrierung mit Admin-Freigabe"
    EMAIL_CONFIRMATION = "email_confirmation", "Registrierung mit E-Mail-Bestaetigung"
    AUTOMATIC = "automatic", "Registrierung mit automatischer Aktivierung"


class RateLimitScope(models.TextChoices):
    LOGIN = "login", "Login"
    REGISTRATION = "registration", "Registrierung"


class User(AbstractUser):
    class Meta:
        verbose_name = "Benutzer"
        verbose_name_plural = "Benutzer"


class RegistrationSettings(models.Model):
    mode = models.CharField(
        max_length=30,
        choices=RegistrationMode.choices,
        default=RegistrationMode.DISABLED,
    )
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Registrierung"
        verbose_name_plural = "Registrierung"

    def __str__(self) -> str:
        return self.get_mode_display()

    def save(self, *args, **kwargs):
        self.pk = 1
        return super().save(*args, **kwargs)

    @classmethod
    def current(cls):
        mode = getattr(settings, "WIKI_REGISTRATION_MODE", RegistrationMode.DISABLED)
        if mode not in RegistrationMode.values:
            mode = RegistrationMode.DISABLED
        settings_row, _ = cls.objects.get_or_create(pk=1, defaults={"mode": mode})
        return settings_row


class EmailConfirmation(models.Model):
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name="email_confirmations",
    )
    token_hash = models.CharField(max_length=64, unique=True)
    created_at = models.DateTimeField(auto_now_add=True)
    expires_at = models.DateTimeField(db_index=True)
    confirmed_at = models.DateTimeField(blank=True, null=True)

    class Meta:
        ordering = ["-created_at", "-id"]
        verbose_name = "E-Mail-Bestaetigung"
        verbose_name_plural = "E-Mail-Bestaetigungen"

    def __str__(self) -> str:
        return f"E-Mail-Bestaetigung fuer {self.user}"

    @property
    def is_confirmed(self) -> bool:
        return self.confirmed_at is not None

    @property
    def is_expired(self) -> bool:
        return timezone.now() > self.expires_at


class RateLimitBucket(models.Model):
    scope = models.CharField(max_length=30, choices=RateLimitScope.choices)
    key_hash = models.CharField(max_length=64)
    attempt_count = models.PositiveIntegerField(default=0)
    window_started_at = models.DateTimeField(default=timezone.now)
    blocked_until = models.DateTimeField(blank=True, null=True, db_index=True)
    updated_at = models.DateTimeField(auto_now=True, db_index=True)

    class Meta:
        constraints = [
            models.UniqueConstraint(
                fields=["scope", "key_hash"],
                name="unique_rate_limit_scope_key",
            )
        ]
        ordering = ["-updated_at", "scope"]
        verbose_name = "Rate-Limit"
        verbose_name_plural = "Rate-Limits"

    def __str__(self) -> str:
        return f"{self.get_scope_display()}: {self.attempt_count}"
