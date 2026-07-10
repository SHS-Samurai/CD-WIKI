from django.conf import settings
from django.contrib.auth.models import Group
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import Q


class WebVisibility(models.TextChoices):
    PRIVATE = "private", "Privat"
    PUBLIC = "public", "Oeffentlich lesbar"
    AUTHENTICATED = "authenticated", "Nur registrierte Benutzer"
    GROUPS = "groups", "Nur bestimmte Gruppen"
    USERS = "users", "Nur bestimmte Benutzer"


class WebPermissionSubject(models.TextChoices):
    USER = "user", "Benutzer"
    GROUP = "group", "Gruppe"
    AUTHENTICATED = "authenticated", "Registrierte Benutzer"
    PUBLIC = "public", "Oeffentliche Gaeste"


WEB_RIGHTS = (
    "view",
    "create",
    "edit",
    "comment",
    "upload",
    "manage",
    "delete",
)


class Web(models.Model):
    slug = models.SlugField(max_length=80, unique=True)
    title = models.CharField(max_length=160)
    description = models.TextField(blank=True)
    visibility = models.CharField(
        max_length=20,
        choices=WebVisibility.choices,
        default=WebVisibility.PRIVATE,
    )
    is_admin_web = models.BooleanField(default=False)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="created_webs",
    )
    updated_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.SET_NULL,
        related_name="updated_webs",
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["slug"]
        verbose_name = "Web"
        verbose_name_plural = "Webs"

    def __str__(self) -> str:
        return self.title

    def clean(self) -> None:
        super().clean()
        self.slug = self.slug.lower()
        if self.slug == "admin":
            self.is_admin_web = True
        if self.is_admin_web and self.visibility != WebVisibility.PRIVATE:
            raise ValidationError(
                {"visibility": "Das Admin-Web muss privat bleiben."}
            )

    def save(self, *args, **kwargs):
        self.full_clean()
        return super().save(*args, **kwargs)

    def has_right(self, user, right: str) -> bool:
        if right not in WEB_RIGHTS:
            raise ValueError(f"Unknown web right: {right}")

        if self._is_admin_user(user):
            return True

        if self.is_admin_web:
            return False

        if right == "view" and self._visibility_grants_view(user):
            return True

        return self.permissions.granting(user, right).exists()

    def _visibility_grants_view(self, user) -> bool:
        if self.visibility == WebVisibility.PUBLIC:
            return True
        if self.visibility == WebVisibility.AUTHENTICATED:
            return bool(getattr(user, "is_authenticated", False))
        return False

    @staticmethod
    def _is_admin_user(user) -> bool:
        return bool(
            getattr(user, "is_authenticated", False)
            and getattr(user, "is_staff", False)
        )


class WebPermissionQuerySet(models.QuerySet):
    def granting(self, user, right: str):
        if right not in WEB_RIGHTS:
            raise ValueError(f"Unknown web right: {right}")

        filters = Q(**{f"can_{right}": True})

        subject_filter = Q(subject_type=WebPermissionSubject.PUBLIC)
        if getattr(user, "is_authenticated", False):
            subject_filter |= Q(subject_type=WebPermissionSubject.AUTHENTICATED)
            subject_filter |= Q(subject_type=WebPermissionSubject.USER, user=user)
            group_ids = list(user.groups.values_list("id", flat=True))
            if group_ids:
                subject_filter |= Q(
                    subject_type=WebPermissionSubject.GROUP,
                    group_id__in=group_ids,
                )

        return self.filter(filters & subject_filter)


class WebPermission(models.Model):
    web = models.ForeignKey(
        Web,
        on_delete=models.CASCADE,
        related_name="permissions",
    )
    subject_type = models.CharField(
        max_length=20,
        choices=WebPermissionSubject.choices,
    )
    subject_key = models.CharField(max_length=191, editable=False)
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        blank=True,
        null=True,
        on_delete=models.CASCADE,
        related_name="web_permissions",
    )
    group = models.ForeignKey(
        Group,
        blank=True,
        null=True,
        on_delete=models.CASCADE,
        related_name="web_permissions",
    )
    can_view = models.BooleanField(default=False)
    can_create = models.BooleanField(default=False)
    can_edit = models.BooleanField(default=False)
    can_comment = models.BooleanField(default=False)
    can_upload = models.BooleanField(default=False)
    can_manage = models.BooleanField(default=False)
    can_delete = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    objects = WebPermissionQuerySet.as_manager()

    class Meta:
        ordering = ["web__slug", "subject_type", "subject_key"]
        constraints = [
            models.UniqueConstraint(
                fields=["web", "subject_key"],
                name="unique_web_permission_subject",
            ),
            models.CheckConstraint(
                condition=(
                    Q(subject_type=WebPermissionSubject.USER, user__isnull=False, group__isnull=True)
                    | Q(subject_type=WebPermissionSubject.GROUP, user__isnull=True, group__isnull=False)
                    | Q(subject_type=WebPermissionSubject.AUTHENTICATED, user__isnull=True, group__isnull=True)
                    | Q(subject_type=WebPermissionSubject.PUBLIC, user__isnull=True, group__isnull=True)
                ),
                name="valid_web_permission_subject",
            ),
        ]
        verbose_name = "Web-Recht"
        verbose_name_plural = "Web-Rechte"

    def __str__(self) -> str:
        rights = ", ".join(right for right in WEB_RIGHTS if getattr(self, f"can_{right}"))
        return f"{self.web.slug}: {self.subject_key} ({rights or 'keine Rechte'})"

    def clean(self) -> None:
        super().clean()
        if self.subject_type == WebPermissionSubject.USER:
            if not self.user or self.group:
                raise ValidationError("Benutzerrechte brauchen genau einen Benutzer.")
        elif self.subject_type == WebPermissionSubject.GROUP:
            if not self.group or self.user:
                raise ValidationError("Gruppenrechte brauchen genau eine Gruppe.")
        elif self.user or self.group:
            raise ValidationError(
                "Oeffentliche und registrierte Rechte duerfen keinen Benutzer und keine Gruppe setzen."
            )
        self.subject_key = self.build_subject_key()

    def save(self, *args, **kwargs):
        self.full_clean()
        return super().save(*args, **kwargs)

    def build_subject_key(self) -> str:
        if self.subject_type == WebPermissionSubject.USER:
            return f"user:{self.user_id}"
        if self.subject_type == WebPermissionSubject.GROUP:
            return f"group:{self.group_id}"
        return self.subject_type
