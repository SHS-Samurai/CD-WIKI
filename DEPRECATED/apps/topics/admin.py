from django.contrib import admin

from .models import Topic


@admin.register(Topic)
class TopicAdmin(admin.ModelAdmin):
    list_display = (
        "wiki_path",
        "title",
        "current_revision",
        "last_edited_by",
        "last_edited_at",
        "is_deleted",
    )
    list_filter = ("web", "is_deleted")
    search_fields = ("slug", "title", "web__slug")
    readonly_fields = (
        "current_revision",
        "current_hash",
        "last_edited_by",
        "last_edited_at",
        "created_at",
        "updated_at",
    )
