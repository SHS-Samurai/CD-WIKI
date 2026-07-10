from django.contrib import admin

from .models import Comment


@admin.register(Comment)
class CommentAdmin(admin.ModelAdmin):
    list_display = (
        "topic",
        "author_username",
        "is_deleted",
        "created_at",
        "deleted_at",
    )
    list_filter = ("is_deleted", "created_at", "deleted_at")
    search_fields = (
        "body",
        "author_username",
        "topic__slug",
        "topic__title",
        "topic__web__slug",
    )
    readonly_fields = (
        "topic",
        "author",
        "author_username",
        "body",
        "is_deleted",
        "deleted_by",
        "deleted_at",
        "created_at",
        "updated_at",
    )

    def has_add_permission(self, request):
        return False

    def has_delete_permission(self, request, obj=None):
        return False
