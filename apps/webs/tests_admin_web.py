from django.contrib.auth import get_user_model
from django.test import TestCase
from django.urls import reverse
from unittest.mock import patch

from .models import Web


class AdminWebTests(TestCase):
    def setUp(self):
        user_model = get_user_model()
        self.user = user_model.objects.create_user(
            username="alice",
            password="test-password",
        )
        self.admin = user_model.objects.create_user(
            username="admin",
            password="test-password",
            is_staff=True,
        )

    def test_admin_web_exists_after_migrations(self):
        web = Web.objects.get(slug="admin")

        self.assertTrue(web.is_admin_web)
        self.assertEqual(web.visibility, "private")

    def test_admin_web_denies_anonymous_and_normal_users(self):
        url = reverse("web_detail", kwargs={"web_slug": "admin"})

        self.assertEqual(self.client.get(url).status_code, 403)
        self.client.login(username="alice", password="test-password")
        self.assertEqual(self.client.get(url).status_code, 403)

    def test_admin_web_allows_staff(self):
        self.client.login(username="admin", password="test-password")

        response = self.client.get(reverse("web_detail", kwargs={"web_slug": "admin"}))

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Benutzer")
        self.assertContains(response, "Systemlog")
        self.assertNotContains(response, "geplant")

    def test_admin_status_pages_deny_normal_users(self):
        self.client.login(username="alice", password="test-password")

        for name in (
            "admin_system_status",
            "admin_search_status",
            "admin_file_types",
            "admin_extensions",
        ):
            with self.subTest(name=name):
                self.assertEqual(self.client.get(reverse(name)).status_code, 403)

    @patch(
        "apps.webs.admin_status._meilisearch_health",
        return_value={"state": "ok", "detail": "available"},
    )
    def test_admin_status_pages_are_available_to_staff(self, _health):
        self.client.login(username="admin", password="test-password")

        expected = {
            "admin_system_status": "Systemstatus",
            "admin_search_status": "Meilisearch",
            "admin_file_types": ".pdf",
            "admin_extensions": "Verfuegbare Hooks",
        }
        for name, text in expected.items():
            with self.subTest(name=name):
                response = self.client.get(reverse(name))
                self.assertEqual(response.status_code, 200)
                self.assertContains(response, text)
