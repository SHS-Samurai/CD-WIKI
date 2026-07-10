from django.shortcuts import get_object_or_404, render
from django.core.exceptions import PermissionDenied

from apps.topics.models import Topic

from .admin_status import (
    dashboard_metrics,
    extension_status,
    file_type_status,
    search_status,
    system_status,
)
from .models import Web
from .permissions import user_can_create, user_can_view


def web_list(request):
    webs = [
        web
        for web in Web.objects.all()
        if user_can_view(request.user, web)
    ]
    return render(request, "webs/web_list.html", {"webs": webs})


def web_detail(request, web_slug):
    web = get_object_or_404(Web, slug=web_slug)
    if not user_can_view(request.user, web):
        raise PermissionDenied

    if web.is_admin_web:
        return render(
            request,
            "webs/admin_dashboard.html",
            {"web": web, "metrics": dashboard_metrics()},
        )

    topics = Topic.objects.filter(web=web, is_deleted=False)
    return render(
        request,
        "webs/web_detail.html",
        {
            "web": web,
            "topics": topics,
            "can_create": user_can_create(request.user, web),
        },
    )


def admin_system_status(request):
    web = _get_admin_web(request)
    return render(
        request,
        "webs/admin_system_status.html",
        {"web": web, **system_status()},
    )


def admin_search_status(request):
    web = _get_admin_web(request)
    return render(
        request,
        "webs/admin_search_status.html",
        {"web": web, **search_status()},
    )


def admin_file_types(request):
    web = _get_admin_web(request)
    return render(
        request,
        "webs/admin_file_types.html",
        {"web": web, **file_type_status()},
    )


def admin_extensions(request):
    web = _get_admin_web(request)
    return render(
        request,
        "webs/admin_extensions.html",
        {"web": web, **extension_status()},
    )


def _get_admin_web(request):
    web = get_object_or_404(Web, slug="admin", is_admin_web=True)
    if not user_can_view(request.user, web):
        raise PermissionDenied
    return web
