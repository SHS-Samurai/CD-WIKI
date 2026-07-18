"""URL configuration for the wiki project."""

from django.contrib import admin
from django.urls import path

from apps.accounts import views as account_views
from apps.attachments import views as attachment_views
from apps.comments import views as comment_views
from apps.search import views as search_views
from apps.theme import views as theme_views
from apps.topics import views as topic_views
from apps.webs import views as web_views


urlpatterns = [
    path("", web_views.web_list, name="home"),
    path("search/", search_views.search_view, name="search"),
    path("theme/active.css", theme_views.active_theme_css, name="theme_css"),
    path(
        "accounts/login/",
        account_views.RateLimitedLoginView.as_view(),
        name="login",
    ),
    path(
        "accounts/logout/",
        account_views.AuditedLogoutView.as_view(),
        name="logout",
    ),
    path("admin/login/", account_views.admin_login_redirect, name="admin_login_redirect"),
    path("accounts/register/", account_views.register, name="register"),
    path(
        "accounts/register/confirm/<str:token>/",
        account_views.confirm_registration,
        name="registration_confirm",
    ),
    path("w/<slug:web_slug>/", web_views.web_detail, name="web_detail"),
    path("w/<slug:web_slug>/new/", topic_views.topic_create, name="topic_create"),
    path("w/<slug:web_slug>/<slug:topic_slug>/", topic_views.topic_detail, name="topic_detail"),
    path("w/<slug:web_slug>/<slug:topic_slug>/edit/", topic_views.topic_edit, name="topic_edit"),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/delete/",
        topic_views.topic_delete,
        name="topic_delete",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/revisions/",
        topic_views.topic_revisions,
        name="topic_revisions",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/revisions/<int:revision>/",
        topic_views.topic_revision_detail,
        name="topic_revision_detail",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/revisions/<int:revision>/restore/",
        topic_views.topic_revision_restore,
        name="topic_revision_restore",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/attachments/upload/",
        attachment_views.attachment_upload,
        name="attachment_upload",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/attachments/<int:attachment_id>/download/",
        attachment_views.attachment_download,
        name="attachment_download",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/attachments/<int:attachment_id>/delete/",
        attachment_views.attachment_delete,
        name="attachment_delete",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/comments/create/",
        comment_views.comment_create,
        name="comment_create",
    ),
    path(
        "w/<slug:web_slug>/<slug:topic_slug>/comments/<int:comment_id>/delete/",
        comment_views.comment_delete,
        name="comment_delete",
    ),
    path("system/trash/topics/", topic_views.topic_trash, name="topic_trash"),
    path(
        "system/admin/status/",
        web_views.admin_system_status,
        name="admin_system_status",
    ),
    path(
        "system/admin/search/",
        web_views.admin_search_status,
        name="admin_search_status",
    ),
    path(
        "system/admin/file-types/",
        web_views.admin_file_types,
        name="admin_file_types",
    ),
    path(
        "system/admin/extensions/",
        web_views.admin_extensions,
        name="admin_extensions",
    ),
    path(
        "system/trash/topics/<int:topic_id>/restore/",
        topic_views.topic_restore,
        name="topic_restore",
    ),
    path("system/trash/attachments/", attachment_views.attachment_trash, name="attachment_trash"),
    path(
        "system/trash/attachments/<int:attachment_id>/restore/",
        attachment_views.attachment_restore,
        name="attachment_restore",
    ),
    path("admin/", admin.site.urls),
]
