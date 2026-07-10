from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as DjangoUserAdmin

from .models import EmailConfirmation, RateLimitBucket, RegistrationSettings, User
from .services import approve_user


@admin.register(User)
class UserAdmin(DjangoUserAdmin):
    actions = ["approve_selected_users"]

    @admin.action(description="Ausgewaehlte Benutzer freischalten")
    def approve_selected_users(self, request, queryset):
        count = 0
        for user in queryset:
            if approve_user(user, actor=request.user, request=request):
                count += 1
        self.message_user(request, f"{count} Benutzer freigeschaltet.")


@admin.register(RegistrationSettings)
class RegistrationSettingsAdmin(admin.ModelAdmin):
    list_display = ("mode", "updated_at")
    fields = ("mode",)

    def has_add_permission(self, request):
        return not RegistrationSettings.objects.exists()

    def has_delete_permission(self, request, obj=None):
        return False


@admin.register(EmailConfirmation)
class EmailConfirmationAdmin(admin.ModelAdmin):
    list_display = ("user", "expires_at", "confirmed_at", "created_at")
    search_fields = ("user__username", "user__email")
    readonly_fields = ("user", "token_hash", "created_at", "expires_at", "confirmed_at")

    def has_add_permission(self, request):
        return False

    def has_delete_permission(self, request, obj=None):
        return False


@admin.register(RateLimitBucket)
class RateLimitBucketAdmin(admin.ModelAdmin):
    list_display = ("scope", "attempt_count", "blocked_until", "updated_at")
    list_filter = ("scope",)
    readonly_fields = (
        "scope",
        "key_hash",
        "attempt_count",
        "window_started_at",
        "blocked_until",
        "updated_at",
    )

    def has_add_permission(self, request):
        return False

    def has_change_permission(self, request, obj=None):
        return False
