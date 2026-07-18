from hashlib import sha256

from django.http import HttpResponse, HttpResponseNotModified
from django.views.decorators.cache import cache_control
from django.views.decorators.http import require_GET

from .defaults import css_variables
from .models import ThemeSettings


@require_GET
@cache_control(public=True, max_age=3600)
def active_theme_css(request):
    theme = ThemeSettings.current()
    variables = css_variables(theme.values_or_defaults())
    stylesheet = ":root {\n" + "".join(
        f"  {name}: {value};\n" for name, value in variables.items()
    ) + "}\n"
    etag = sha256(stylesheet.encode("utf-8")).hexdigest()
    if request.headers.get("If-None-Match") == f'"{etag}"':
        response = HttpResponseNotModified()
        response["ETag"] = f'"{etag}"'
        return response

    response = HttpResponse(stylesheet, content_type="text/css; charset=utf-8")
    response["ETag"] = f'"{etag}"'
    return response
