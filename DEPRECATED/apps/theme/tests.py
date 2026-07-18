from django.contrib.auth import get_user_model
from django.core.exceptions import ValidationError
from django.test import TestCase
from django.urls import reverse

from apps.audit.models import AuditAction, AuditLog

from .defaults import DEFAULT_THEME, css_variables
from .models import ThemeSettings


class ThemeSettingsTests(TestCase):
    def test_stylesheet_uses_defaults_without_saved_settings(self):
        response = self.client.get(reverse("theme_css"))

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response["Content-Type"], "text/css; charset=utf-8")
        self.assertContains(response, "--color-primary: #176b87;")
        self.assertContains(response, "--color-primary-hover: #0f4d61;")
        self.assertContains(response, "--color-muted: #617184;")
        names = {
            line.split(":", 1)[0].strip()
            for line in response.content.decode("utf-8").splitlines()
            if line.strip().startswith("--")
        }
        self.assertEqual(names, set(css_variables(DEFAULT_THEME)))

    def test_valid_theme_values_are_written_as_fixed_css_variables(self):
        ThemeSettings.objects.create(
            primary_color="#2458c4",
            page_background_color="#f0f4ff",
            surface_color="#ffffff",
            text_color="#1b2430",
            muted_text_color="#5b687a",
            border_color="#c9d3e1",
            font_size_base=17,
            page_max_width=1280,
            content_max_width=980,
            sidebar_left_width=220,
            sidebar_right_width=260,
            radius_strength=12,
            left_sidebar_enabled=True,
        )

        response = self.client.get(reverse("theme_css"))

        self.assertContains(response, "--color-primary: #2458c4;")
        self.assertContains(response, "--color-on-primary: #ffffff;")
        self.assertContains(response, "--font-size-base: 17px;")
        self.assertContains(response, "--page-max-width: 1280px;")
        self.assertNotContains(response, "url(")

    def test_invalid_colors_and_measurements_are_rejected(self):
        invalid_values = (
            {"primary_color": "red"},
            {"primary_color": "#123456;"},
            {"primary_color": "url(test)"},
            {"font_size_base": 13},
            {"sidebar_left_width": 100},
            {"page_max_width": 1000, "content_max_width": 1200},
            {"text_color": "#ffffff"},
            {"muted_text_color": "#d9dee7"},
            {"primary_color": "#ffffff"},
        )

        for values in invalid_values:
            with self.subTest(values=values):
                with self.assertRaises(ValidationError):
                    ThemeSettings(**values).save()

    def test_invalid_stored_values_fall_back_to_defaults(self):
        theme = ThemeSettings.objects.create()
        ThemeSettings.objects.filter(pk=theme.pk).update(primary_color="#zzzzzz")

        response = self.client.get(reverse("theme_css"))

        self.assertContains(response, "--color-primary: #176b87;")
        self.assertNotContains(response, "#zzzzzz")

    def test_cache_version_includes_microseconds(self):
        theme = ThemeSettings()
        theme.updated_at = datetime(2026, 7, 10, 12, 30, 45, 123456, tzinfo=UTC)

        self.assertEqual(theme.cache_version(), "20260710123045123456")

    def test_stylesheet_supports_etag_revalidation(self):
        first_response = self.client.get(reverse("theme_css"))
        second_response = self.client.get(
            reverse("theme_css"),
            HTTP_IF_NONE_MATCH=first_response["ETag"],
        )

        self.assertEqual(second_response.status_code, 304)
        self.assertEqual(second_response["ETag"], first_response["ETag"])
        self.assertIn("public", first_response["Cache-Control"])


class ThemeAdminTests(TestCase):
    def setUp(self):
        user_model = get_user_model()
        self.staff = user_model.objects.create_superuser(
            username="admin",
            email="admin@example.test",
            password="test-password",
        )

    def test_theme_administration_requires_staff_permission(self):
        response = self.client.get(reverse("admin:theme_themesettings_changelist"))

        self.assertEqual(response.status_code, 302)

    def test_admin_change_writes_audit_log_and_reset_restores_defaults(self):
        self.client.login(username="admin", password="test-password")
        add_url = reverse("admin:theme_themesettings_add")
        response = self.client.post(
            add_url,
            {
                "primary_color": "#2458c4",
                "page_background_color": "#f0f4ff",
                "surface_color": "#ffffff",
                "text_color": "#1b2430",
                "muted_text_color": "#5b687a",
                "border_color": "#c9d3e1",
                "font_size_base": 17,
                "page_max_width": 1280,
                "content_max_width": 980,
                "sidebar_left_width": 220,
                "sidebar_right_width": 260,
                "radius_strength": 12,
                "left_sidebar_enabled": "on",
                "_save": "Speichern",
            },
        )

        self.assertEqual(response.status_code, 302)
        self.assertTrue(AuditLog.objects.filter(action=AuditAction.THEME_UPDATED).exists())
        theme = ThemeSettings.objects.get()
        response = self.client.post(
            reverse("admin:theme_themesettings_changelist"),
            {
                "action": "restore_selected_defaults",
                "_selected_action": str(theme.pk),
            },
        )

        self.assertEqual(response.status_code, 302)
        theme.refresh_from_db()
        self.assertEqual(theme.primary_color, DEFAULT_THEME["primary_color"])
        self.assertEqual(theme.page_max_width, DEFAULT_THEME["page_max_width"])
        self.assertGreaterEqual(
            AuditLog.objects.filter(action=AuditAction.THEME_UPDATED).count(),
            2,
        )

    def test_failed_audit_log_rolls_back_theme_change(self):
        self.client.login(username="admin", password="test-password")
        with patch(
            "apps.theme.admin.write_audit_log",
            side_effect=RuntimeError("Audit-Fehler"),
        ):
            with self.assertRaises(RuntimeError):
                self.client.post(
                    reverse("admin:theme_themesettings_add"),
                    {
                        "primary_color": "#2458c4",
                        "page_background_color": "#f0f4ff",
                        "surface_color": "#ffffff",
                        "text_color": "#1b2430",
                        "muted_text_color": "#5b687a",
                        "border_color": "#c9d3e1",
                        "font_size_base": 17,
                        "page_max_width": 1280,
                        "content_max_width": 980,
                        "sidebar_left_width": 220,
                        "sidebar_right_width": 260,
                        "radius_strength": 12,
                        "_save": "Speichern",
                    },
                )

        self.assertFalse(ThemeSettings.objects.exists())


class ThemeLayoutTests(TestCase):
    def test_all_sidebar_combinations_render(self):
        expected_classes = (
            (False, False, "site-layout--none"),
            (True, False, "site-layout--left"),
            (False, True, "site-layout--right"),
            (True, True, "site-layout--both"),
        )
        theme = ThemeSettings.objects.create()

        for left_enabled, right_enabled, expected_class in expected_classes:
            with self.subTest(expected_class=expected_class):
                theme.left_sidebar_enabled = left_enabled
                theme.right_sidebar_enabled = right_enabled
                theme.save()

                response = self.client.get(reverse("home"))

                self.assertEqual(response.status_code, 200)
                self.assertContains(response, expected_class)
                self.assertContains(response, reverse("theme_css"))
from datetime import UTC, datetime
from unittest.mock import patch
