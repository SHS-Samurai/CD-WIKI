from django.contrib import admin

from .models import Web, WebPermission


class WebPermissionInline(admin.TabularInline):
    model = WebPermission
    extra = 0
    fields = (
        "subject_type",
        "user",
        "group",
        "can_view",
        "can_create",
        "can_edit",
        "can_comment",
        "can_upload",
        "can_manage",
        "can_delete",
    )


@admin.register(Web)
class WebAdmin(admin.ModelAdmin):
    list_display = ("slug", "title", "visibility", "is_admin_web", "updated_at")
    list_filter = ("visibility", "is_admin_web")
    search_fields = ("slug", "title", "description")
    prepopulated_fields = {"slug": ("title",)}
    inlines = [WebPermissionInline]


@admin.register(WebPermission)
class WebPermissionAdmin(admin.ModelAdmin):
    list_display = (
        "web",
        "subject_type",
        "subject_key",
        "can_view",
        "can_create",
        "can_edit",
        "can_comment",
        "can_upload",
        "can_manage",
        "can_delete",
    )
    list_filter = ("subject_type", "can_view", "can_edit", "can_manage")
    search_fields = ("web__slug", "web__title", "subject_key")
    readonly_fields = ("subject_key",)
