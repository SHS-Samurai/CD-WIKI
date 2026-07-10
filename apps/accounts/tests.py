import re

from django.contrib.auth import get_user_model
from django.core import mail
from django.test import TestCase, override_settings
from django.urls import reverse
from django.utils import timezone

from apps.audit.models import AuditAction, AuditLog

from .models import EmailConfirmation, RateLimitBucket, RegistrationMode, RegistrationSettings
from .services import approve_user


def registration_payload(**overrides):
    payload = {
        "username": "alice",
        "email": "alice@example.test",
        "password1": "StrongPass123!",
        "password2": "StrongPass123!",
        "website": "",
        "started_at": int(timezone.now().timestamp()) - 10,
    }
    payload.update(overrides)
    return payload


@override_settings(
    EMAIL_BACKEND="django.core.mail.backends.locmem.EmailBackend",
    WIKI_REGISTRATION_MIN_SECONDS=0,
)
class RegistrationTests(TestCase):
    def set_mode(self, mode):
        RegistrationSettings.objects.create(mode=mode)

    def test_disabled_registration_returns_403_and_creates_no_user(self):
        self.set_mode(RegistrationMode.DISABLED)

        response = self.client.post(reverse("register"), registration_payload())

        self.assertEqual(response.status_code, 403)
        self.assertFalse(get_user_model().objects.filter(username="alice").exists())

    def test_automatic_registration_creates_active_user_and_auditlog(self):
        self.set_mode(RegistrationMode.AUTOMATIC)

        response = self.client.post(reverse("register"), registration_payload())

        self.assertEqual(response.status_code, 302)
        user = get_user_model().objects.get(username="alice")
        self.assertTrue(user.is_active)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.USER_REGISTERED,
                user=user,
                details__mode=RegistrationMode.AUTOMATIC,
            ).exists()
        )

    def test_admin_approval_registration_creates_inactive_user(self):
        self.set_mode(RegistrationMode.ADMIN_APPROVAL)

        response = self.client.post(reverse("register"), registration_payload())

        self.assertEqual(response.status_code, 302)
        user = get_user_model().objects.get(username="alice")
        self.assertFalse(user.is_active)
        self.assertFalse(EmailConfirmation.objects.exists())

    def test_email_confirmation_creates_inactive_user_and_confirmation_mail(self):
        self.set_mode(RegistrationMode.EMAIL_CONFIRMATION)

        response = self.client.post(reverse("register"), registration_payload())

        self.assertEqual(response.status_code, 302)
        user = get_user_model().objects.get(username="alice")
        self.assertFalse(user.is_active)
        self.assertEqual(EmailConfirmation.objects.filter(user=user).count(), 1)
        self.assertEqual(len(mail.outbox), 1)
        self.assertIn("alice@example.test", mail.outbox[0].to)

    def test_confirmation_link_activates_user_and_writes_auditlog(self):
        self.set_mode(RegistrationMode.EMAIL_CONFIRMATION)
        self.client.post(reverse("register"), registration_payload())
        user = get_user_model().objects.get(username="alice")
        token = re.search(r"/accounts/register/confirm/([^/\s]+)/", mail.outbox[0].body).group(1)

        response = self.client.get(reverse("registration_confirm", kwargs={"token": token}))

        self.assertEqual(response.status_code, 302)
        user.refresh_from_db()
        confirmation = EmailConfirmation.objects.get(user=user)
        self.assertTrue(user.is_active)
        self.assertIsNotNone(confirmation.confirmed_at)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.USER_APPROVED,
                user=user,
                details__source="email_confirmation",
            ).exists()
        )

    def test_invalid_confirmation_token_returns_400(self):
        self.set_mode(RegistrationMode.EMAIL_CONFIRMATION)

        response = self.client.get(reverse("registration_confirm", kwargs={"token": "ungueltig"}))

        self.assertEqual(response.status_code, 400)

    def test_honeypot_rejects_registration(self):
        self.set_mode(RegistrationMode.AUTOMATIC)

        response = self.client.post(
            reverse("register"),
            registration_payload(website="https://spam.example"),
        )

        self.assertEqual(response.status_code, 200)
        self.assertFalse(get_user_model().objects.filter(username="alice").exists())

    @override_settings(WIKI_REGISTRATION_MIN_SECONDS=60)
    def test_minimum_form_time_rejects_fast_submit(self):
        self.set_mode(RegistrationMode.AUTOMATIC)

        response = self.client.post(
            reverse("register"),
            registration_payload(started_at=int(timezone.now().timestamp())),
        )

        self.assertEqual(response.status_code, 200)
        self.assertFalse(get_user_model().objects.filter(username="alice").exists())

    def test_approve_user_activates_user_and_writes_auditlog(self):
        user_model = get_user_model()
        actor = user_model.objects.create_user(username="admin", password="test-password", is_staff=True)
        user = user_model.objects.create_user(
            username="pending",
            email="pending@example.test",
            password="test-password",
            is_active=False,
        )

        changed = approve_user(user, actor=actor)

        self.assertTrue(changed)
        user.refresh_from_db()
        self.assertTrue(user.is_active)
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.USER_APPROVED,
                user=actor,
                details__approved_user_id=user.pk,
            ).exists()
        )

    @override_settings(
        WIKI_REGISTRATION_RATE_LIMIT=1,
        WIKI_REGISTRATION_RATE_WINDOW_SECONDS=3600,
        WIKI_REGISTRATION_RATE_BLOCK_SECONDS=3600,
    )
    def test_registration_rate_limit_blocks_following_submission(self):
        self.set_mode(RegistrationMode.AUTOMATIC)
        self.client.post(reverse("register"), registration_payload())

        response = self.client.post(
            reverse("register"),
            registration_payload(username="bob", email="bob@example.test"),
        )

        self.assertEqual(response.status_code, 429)
        self.assertIn("Retry-After", response)
        self.assertFalse(get_user_model().objects.filter(username="bob").exists())
        self.assertTrue(
            AuditLog.objects.filter(
                action=AuditAction.RATE_LIMIT_BLOCKED,
                details__scope="registration",
            ).exists()
        )


@override_settings(
    WIKI_LOGIN_RATE_LIMIT=2,
    WIKI_LOGIN_RATE_WINDOW_SECONDS=900,
    WIKI_LOGIN_RATE_BLOCK_SECONDS=900,
)
class LoginRateLimitTests(TestCase):
    def setUp(self):
        get_user_model().objects.create_user(
            username="alice",
            password="test-password",
        )

    def test_failed_logins_are_limited_per_ip(self):
        url = reverse("login")
        first = self.client.post(url, {"username": "alice", "password": "wrong"})
        second = self.client.post(url, {"username": "alice", "password": "wrong"})
        blocked = self.client.post(url, {"username": "alice", "password": "test-password"})
        self.client.post(url, {"username": "alice", "password": "test-password"})

        self.assertEqual(first.status_code, 200)
        self.assertEqual(second.status_code, 429)
        self.assertEqual(blocked.status_code, 429)
        self.assertIn("Retry-After", blocked)
        self.assertEqual(RateLimitBucket.objects.count(), 1)
        self.assertEqual(
            AuditLog.objects.filter(action=AuditAction.RATE_LIMIT_BLOCKED).count(),
            1,
        )

    def test_other_ip_is_not_blocked(self):
        url = reverse("login")
        for _ in range(2):
            self.client.post(
                url,
                {"username": "alice", "password": "wrong"},
                REMOTE_ADDR="192.0.2.10",
            )

        response = self.client.post(
            url,
            {"username": "alice", "password": "test-password"},
            REMOTE_ADDR="192.0.2.11",
        )

        self.assertEqual(response.status_code, 302)

    def test_successful_login_clears_counter_and_audits_logout(self):
        url = reverse("login")
        self.client.post(url, {"username": "alice", "password": "wrong"})

        response = self.client.post(
            url,
            {"username": "alice", "password": "test-password"},
        )
        self.client.post(reverse("logout"))

        self.assertEqual(response.status_code, 302)
        self.assertFalse(RateLimitBucket.objects.exists())
        self.assertTrue(AuditLog.objects.filter(action=AuditAction.LOGIN_SUCCESS).exists())
        self.assertTrue(AuditLog.objects.filter(action=AuditAction.LOGOUT).exists())
