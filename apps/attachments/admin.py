from django.contrib import admin

from .models import Attachment


@admin.register(Attachment)
class AttachmentAdmin(admin.ModelAdmin):
    list_display = (
        "original_filename",
        "topic",
        "current_revision",
        "content_type",
        "size",
        "is_deleted",
        "updated_at",
    )
    list_filter = ("is_deleted", "content_type", "topic__web")
    search_fields = ("original_filename", "storage_name", "topic__slug", "topic__web__slug")
    readonly_fields = (
        "storage_name",
        "size",
        "current_revision",
        "current_hash",
        "uploaded_by",
        "updated_by",
        "created_at",
        "updated_at",
    )
