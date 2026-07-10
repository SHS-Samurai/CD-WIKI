from django import forms
from django.contrib import admin
from django.db import models, transaction

from apps.audit.models import AuditAction
from apps.audit.services import write_audit_log

from .models import ThemeSettings


@admin.register(ThemeSettings)
class ThemeSettingsAdmin(admin.ModelAdmin):
    list_display = ("__str__", "updated_at")
    readonly_fields = ("updated_at",)
    actions = ("restore_selected_defaults",)
    fieldsets = (
        (
            "Farben",
            {
                "fields": (
                    "primary_color",
                    "page_background_color",
                    "surface_color",
                    "text_color",
                    "muted_text_color",
                    "border_color",
                )
            },
        ),
        (
            "Layout",
            {
                "fields": (
                    "font_size_base",
                    "page_max_width",
                    "content_max_width",
                    "sidebar_left_width",
                    "sidebar_right_width",
                    "radius_strength",
                    "left_sidebar_enabled",
                    "right_sidebar_enabled",
                )
            },
        ),
        ("Status", {"fields": ("updated_at",)}),
    )
    formfield_overrides = {
        models.CharField: {"widget": forms.TextInput(attrs={"type": "color"})},
    }

    @admin.action(description="Standardwerte wiederherstellen")
    @transaction.atomic
    def restore_selected_defaults(self, request, queryset):
        for theme in queryset:
            theme.restore_defaults()
            theme.save()
            write_audit_log(
                action=AuditAction.THEME_UPDATED,
                user=request.user,
                request=request,
                details={"source": "admin_reset", "fields": "defaults"},
            )
        self.message_user(request, "Theme-Standardwerte wiederhergestellt.")

    @transaction.atomic
    def save_model(self, request, obj, form, change):
        super().save_model(request, obj, form, change)
        if form.changed_data or not change:
            write_audit_log(
                action=AuditAction.THEME_UPDATED,
                user=request.user,
                request=request,
                details={"source": "admin_form", "fields": sorted(form.changed_data)},
            )

    def has_add_permission(self, request):
        return not ThemeSettings.objects.exists()

    def has_delete_permission(self, request, obj=None):
        return False
