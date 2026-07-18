from __future__ import annotations

import re

from django.core.exceptions import ValidationError
from django.core.validators import MaxValueValidator, MinValueValidator
from django.db import models

from .defaults import DEFAULT_THEME, contrast_ratio


HEX_COLOR_RE = re.compile(r"#[0-9A-Fa-f]{6}\Z")
COLOR_FIELDS = (
    "primary_color",
    "page_background_color",
    "surface_color",
    "text_color",
    "muted_text_color",
    "border_color",
)
THEME_FIELDS = tuple(DEFAULT_THEME)


class ThemeSettings(models.Model):
    primary_color = models.CharField(max_length=7, default=DEFAULT_THEME["primary_color"])
    page_background_color = models.CharField(
        max_length=7,
        default=DEFAULT_THEME["page_background_color"],
    )
    surface_color = models.CharField(max_length=7, default=DEFAULT_THEME["surface_color"])
    text_color = models.CharField(max_length=7, default=DEFAULT_THEME["text_color"])
    muted_text_color = models.CharField(
        max_length=7,
        default=DEFAULT_THEME["muted_text_color"],
    )
    border_color = models.CharField(max_length=7, default=DEFAULT_THEME["border_color"])
    font_size_base = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["font_size_base"],
        validators=[MinValueValidator(14), MaxValueValidator(20)],
    )
    page_max_width = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["page_max_width"],
        validators=[MinValueValidator(960), MaxValueValidator(1920)],
    )
    content_max_width = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["content_max_width"],
        validators=[MinValueValidator(560), MaxValueValidator(1600)],
    )
    sidebar_left_width = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["sidebar_left_width"],
        validators=[MinValueValidator(180), MaxValueValidator(400)],
    )
    sidebar_right_width = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["sidebar_right_width"],
        validators=[MinValueValidator(180), MaxValueValidator(400)],
    )
    radius_strength = models.PositiveSmallIntegerField(
        default=DEFAULT_THEME["radius_strength"],
        validators=[MinValueValidator(0), MaxValueValidator(24)],
    )
    left_sidebar_enabled = models.BooleanField(default=DEFAULT_THEME["left_sidebar_enabled"])
    right_sidebar_enabled = models.BooleanField(default=DEFAULT_THEME["right_sidebar_enabled"])
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = "Theme-Einstellungen"
        verbose_name_plural = "Theme-Einstellungen"

    def __str__(self) -> str:
        return "Globale Theme-Einstellungen"

    def clean(self):
        errors = {}
        for field_name in COLOR_FIELDS:
            if not HEX_COLOR_RE.fullmatch(getattr(self, field_name, "")):
                errors[field_name] = "Bitte eine Hex-Farbe im Format #RRGGBB eingeben."
        if not errors:
            contrast_pairs = {
                "text_color": ("page_background_color", "surface_color"),
                "muted_text_color": ("page_background_color", "surface_color"),
                "primary_color": ("page_background_color", "surface_color"),
            }
            for foreground_field, background_fields in contrast_pairs.items():
                minimum_contrast = min(
                    contrast_ratio(
                        getattr(self, foreground_field),
                        getattr(self, background_field),
                    )
                    for background_field in background_fields
                )
                if minimum_contrast < 4.5:
                    errors[foreground_field] = (
                        "Der Kontrast zu Seiten- und Oberflaechenfarbe muss mindestens "
                        "4,5:1 betragen."
                    )
        if self.content_max_width > self.page_max_width:
            errors["content_max_width"] = (
                "Die maximale Inhaltsbreite darf nicht groesser als die Seitenbreite sein."
            )
        if errors:
            raise ValidationError(errors)

    def save(self, *args, **kwargs):
        self.pk = 1
        self.full_clean()
        return super().save(*args, **kwargs)

    @classmethod
    def current(cls) -> "ThemeSettings":
        return cls.objects.filter(pk=1).first() or cls(pk=1)

    def values_or_defaults(self) -> dict[str, int | str | bool]:
        try:
            self.full_clean()
        except ValidationError:
            return dict(DEFAULT_THEME)
        return {field_name: getattr(self, field_name) for field_name in THEME_FIELDS}

    def cache_version(self) -> str:
        if self.updated_at is None:
            return "default"
        return self.updated_at.strftime("%Y%m%d%H%M%S%f")

    def restore_defaults(self) -> None:
        for field_name, value in DEFAULT_THEME.items():
            setattr(self, field_name, value)
