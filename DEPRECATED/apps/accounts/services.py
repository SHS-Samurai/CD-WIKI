import hashlib
import secrets
from datetime import timedelta

from django.conf import settings
from django.contrib.auth import get_user_model
from django.core.mail import send_mail
from django.urls import reverse
from django.utils import timezone

from apps.audit.models import AuditAction
from apps.audit.services import write_audit_log

from .models import EmailConfirmation, RegistrationMode, RegistrationSettings


def registration_settings() -> RegistrationSettings:
    return RegistrationSettings.current()


def register_user(*, form, request):
    settings_row = registration_settings()
    mode = settings_row.mode
    if mode == RegistrationMode.DISABLED:
        raise RegistrationDisabled

    user = form.save(commit=False)
    user.email = form.cleaned_data["email"]
    user.is_active = mode == RegistrationMode.AUTOMATIC
    user.save()

    write_audit_log(
        action=AuditAction.USER_REGISTERED,
        user=user,
        request=request,
        details={"mode": mode},
    )

    confirmation = None
    if mode == RegistrationMode.EMAIL_CONFIRMATION:
        token, confirmation = create_email_confirmation(user)
        send_confirmation_email(request, user, token)

    return user, mode, confirmation


def create_email_confirmation(user):
    token = secrets.token_urlsafe(32)
    confirmation = EmailConfirmation.objects.create(
        user=user,
        token_hash=hash_token(token),
        expires_at=timezone.now() + timedelta(hours=settings.WIKI_EMAIL_CONFIRMATION_HOURS),
    )
    return token, confirmation


def confirm_email_token(token: str, *, request=None):
    token_hash = hash_token(token)
    confirmation = EmailConfirmation.objects.select_related("user").filter(
        token_hash=token_hash,
        confirmed_at__isnull=True,
    ).first()
    if confirmation is None or confirmation.is_expired:
        return None

    user = confirmation.user
    user.is_active = True
    user.save(update_fields=["is_active"])
    confirmation.confirmed_at = timezone.now()
    confirmation.save(update_fields=["confirmed_at"])
    write_audit_log(
        action=AuditAction.USER_APPROVED,
        user=user,
        request=request,
        details={"source": "email_confirmation"},
    )
    return user


def approve_user(user, *, actor=None, request=None):
    if user.is_active:
        return False
    user.is_active = True
    user.save(update_fields=["is_active"])
    write_audit_log(
        action=AuditAction.USER_APPROVED,
        user=actor,
        request=request,
        details={"approved_user_id": user.pk, "approved_username": user.get_username()},
    )
    return True


def send_confirmation_email(request, user, token: str) -> None:
    path = reverse("registration_confirm", kwargs={"token": token})
    url = request.build_absolute_uri(path)
    send_mail(
        subject="Wiki Registrierung bestaetigen",
        message=(
            "Bitte bestaetige deine Registrierung im Wiki.\n\n"
            f"{url}\n\n"
            "Wenn du dich nicht registriert hast, kannst du diese E-Mail ignorieren."
        ),
        from_email=None,
        recipient_list=[user.email],
        fail_silently=False,
    )


def hash_token(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


class RegistrationDisabled(Exception):
    pass
