from django.contrib import admin

from .models import AuditLog


@admin.register(AuditLog)
class AuditLogAdmin(admin.ModelAdmin):
    list_display = (
        "created_at",
        "action",
        "username",
        "ip_address",
        "web_slug",
        "topic_slug",
        "attachment_name",
    )
    list_filter = ("action", "created_at", "web_slug")
    search_fields = (
        "username",
        "ip_address",
        "web_slug",
        "topic_slug",
        "attachment_name",
        "user_agent",
    )
    readonly_fields = (
        "created_at",
        "user",
        "user_id_snapshot",
        "username",
        "ip_address",
        "user_agent",
        "action",
        "web",
        "web_slug",
        "topic",
        "topic_slug",
        "attachment_name",
        "old_revision",
        "new_revision",
        "old_hash",
        "new_hash",
        "details",
    )

    def has_add_permission(self, request):
        return False

    def has_change_permission(self, request, obj=None):
        return request.user.is_active and request.user.is_staff

    def has_delete_permission(self, request, obj=None):
        return False
