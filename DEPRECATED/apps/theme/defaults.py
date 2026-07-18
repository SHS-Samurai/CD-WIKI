from __future__ import annotations

from collections.abc import Mapping


DEFAULT_THEME: Mapping[str, int | str | bool] = {
    "primary_color": "#176b87",
    "page_background_color": "#f6f7f9",
    "surface_color": "#ffffff",
    "text_color": "#1f2933",
    "muted_text_color": "#617184",
    "border_color": "#d9dee7",
    "font_size_base": 16,
    "page_max_width": 1120,
    "content_max_width": 1120,
    "sidebar_left_width": 240,
    "sidebar_right_width": 240,
    "radius_strength": 8,
    "left_sidebar_enabled": False,
    "right_sidebar_enabled": False,
}

DEFAULT_PRIMARY_HOVER = "#0f4d61"
LIGHT_TEXT = "#ffffff"
DARK_TEXT = "#1f2933"


def css_variables(values: Mapping[str, int | str | bool]) -> dict[str, str]:
    primary_color = str(values["primary_color"])
    page_background = str(values["page_background_color"])
    surface_color = str(values["surface_color"])
    radius = int(values["radius_strength"])
    return {
        "--color-background": page_background,
        "--color-surface": surface_color,
        "--color-text": str(values["text_color"]),
        "--color-muted": str(values["muted_text_color"]),
        "--color-border": str(values["border_color"]),
        "--color-primary": primary_color,
        "--color-primary-hover": _primary_hover(
            primary_color,
            page_background,
            surface_color,
        ),
        "--color-on-primary": _highest_contrast(primary_color, LIGHT_TEXT, DARK_TEXT),
        "--color-focus": primary_color,
        "--font-size-base": f"{int(values['font_size_base'])}px",
        "--page-max-width": f"{int(values['page_max_width'])}px",
        "--content-max-width": f"{int(values['content_max_width'])}px",
        "--sidebar-left-width": f"{int(values['sidebar_left_width'])}px",
        "--sidebar-right-width": f"{int(values['sidebar_right_width'])}px",
        "--radius-sm": f"{max(0, radius - 4)}px",
        "--radius-md": f"{radius}px",
        "--radius-lg": f"{radius + 4}px",
    }


def _shade_hex(value: str, factor: float) -> str:
    channels = (int(value[index : index + 2], 16) for index in (1, 3, 5))
    return "#" + "".join(
        f"{min(255, max(0, round(channel * factor))):02x}" for channel in channels
    )


def _lighten_hex(value: str, amount: float) -> str:
    channels = (int(value[index : index + 2], 16) for index in (1, 3, 5))
    return "#" + "".join(
        f"{round(channel + ((255 - channel) * amount)):02x}" for channel in channels
    )


def _primary_hover(primary: str, background: str, surface: str) -> str:
    if primary.lower() == str(DEFAULT_THEME["primary_color"]).lower():
        return DEFAULT_PRIMARY_HOVER
    candidates = (_shade_hex(primary, 0.78), _lighten_hex(primary, 0.18))
    return max(
        candidates,
        key=lambda color: min(
            contrast_ratio(color, background),
            contrast_ratio(color, surface),
        ),
    )


def _highest_contrast(background: str, *candidates: str) -> str:
    return max(candidates, key=lambda color: contrast_ratio(color, background))


def contrast_ratio(first: str, second: str) -> float:
    first_luminance = _relative_luminance(first)
    second_luminance = _relative_luminance(second)
    lighter = max(first_luminance, second_luminance)
    darker = min(first_luminance, second_luminance)
    return (lighter + 0.05) / (darker + 0.05)


def _relative_luminance(value: str) -> float:
    channels = [int(value[index : index + 2], 16) / 255 for index in (1, 3, 5)]
    linear = [
        channel / 12.92
        if channel <= 0.04045
        else ((channel + 0.055) / 1.055) ** 2.4
        for channel in channels
    ]
    return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2])
