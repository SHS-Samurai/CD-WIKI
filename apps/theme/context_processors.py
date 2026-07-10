from .models import ThemeSettings


def active_theme(request):
    theme = ThemeSettings.current()
    return {
        "wiki_theme": theme.values_or_defaults(),
        "wiki_theme_version": theme.cache_version(),
    }
