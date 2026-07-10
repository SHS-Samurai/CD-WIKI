from django.contrib.auth import get_user_model
from django.contrib.auth.models import AnonymousUser, Group
from django.core.exceptions import ValidationError
from django.test import TestCase

from .models import Web, WebPermission, WebPermissionSubject, WebVisibility
from .permissions import user_can


class WebPermissionTests(TestCase):
    def setUp(self):
        user_model = get_user_model()
        self.user = user_model.objects.create_user(
            username="alice",
            password="test-password",
        )
        self.other_user = user_model.objects.create_user(
            username="bob",
            password="test-password",
        )
        self.admin = user_model.objects.create_user(
            username="admin",
            password="test-password",
            is_staff=True,
        )
        self.group = Group.objects.create(name="Technik")
        self.user.groups.add(self.group)

    def test_public_visibility_grants_view_only(self):
        web = Web.objects.create(
            slug="public",
            title="Public",
            visibility=WebVisibility.PUBLIC,
        )

        self.assertTrue(user_can(AnonymousUser(), web, "view"))
        self.assertFalse(user_can(AnonymousUser(), web, "edit"))

    def test_authenticated_visibility_grants_registered_view(self):
        web = Web.objects.create(
            slug="intern",
            title="Intern",
            visibility=WebVisibility.AUTHENTICATED,
        )

        self.assertFalse(user_can(AnonymousUser(), web, "view"))
        self.assertTrue(user_can(self.user, web, "view"))

    def test_user_permission_grants_specific_right(self):
        web = Web.objects.create(slug="user-web", title="User Web")
        WebPermission.objects.create(
            web=web,
            subject_type=WebPermissionSubject.USER,
            user=self.user,
            can_view=True,
            can_edit=True,
        )

        self.assertTrue(user_can(self.user, web, "view"))
        self.assertTrue(user_can(self.user, web, "edit"))
        self.assertFalse(user_can(self.other_user, web, "view"))
        self.assertFalse(user_can(self.user, web, "delete"))

    def test_group_permission_grants_group_members(self):
        web = Web.objects.create(slug="group-web", title="Group Web")
        WebPermission.objects.create(
            web=web,
            subject_type=WebPermissionSubject.GROUP,
            group=self.group,
            can_view=True,
            can_comment=True,
        )

        self.assertTrue(user_can(self.user, web, "view"))
        self.assertTrue(user_can(self.user, web, "comment"))
        self.assertFalse(user_can(self.other_user, web, "view"))

    def test_public_permission_can_grant_comment_to_guests(self):
        web = Web.objects.create(slug="guest-comments", title="Guest Comments")
        WebPermission.objects.create(
            web=web,
            subject_type=WebPermissionSubject.PUBLIC,
            can_view=True,
            can_comment=True,
        )

        self.assertTrue(user_can(AnonymousUser(), web, "view"))
        self.assertTrue(user_can(AnonymousUser(), web, "comment"))

    def test_admin_web_is_staff_only_even_if_permission_exists(self):
        web, _ = Web.objects.get_or_create(slug="admin", defaults={"title": "Admin"})
        WebPermission.objects.create(
            web=web,
            subject_type=WebPermissionSubject.PUBLIC,
            can_view=True,
        )

        self.assertTrue(web.is_admin_web)
        self.assertFalse(user_can(AnonymousUser(), web, "view"))
        self.assertFalse(user_can(self.user, web, "view"))
        self.assertTrue(user_can(self.admin, web, "manage"))

    def test_invalid_subject_combination_is_rejected(self):
        web = Web.objects.create(slug="invalid", title="Invalid")
        permission = WebPermission(
            web=web,
            subject_type=WebPermissionSubject.PUBLIC,
            user=self.user,
            can_view=True,
        )

        with self.assertRaises(ValidationError):
            permission.full_clean()
