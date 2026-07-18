<?php

namespace App\Services;

use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ThemeService
{
    public function current(): ThemeSetting
    {
        try {
            if (Schema::hasTable('theme_settings')) {
                return Cache::remember('wiki.theme', 3600, fn () => ThemeSetting::query()->first())
                    ?? $this->defaults();
            }
        } catch (Throwable) {
        }

        return $this->defaults();
    }

    public function clear(): void
    {
        Cache::forget('wiki.theme');
    }

    public function css(ThemeSetting $theme): string
    {
        $font = $theme->font_family === 'serif'
            ? 'Georgia, Cambria, "Times New Roman", serif'
            : 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

        return ":root{--wiki-primary:{$theme->primary_color};--wiki-background:{$theme->background_color};--wiki-surface:{$theme->surface_color};--wiki-text:{$theme->text_color};--wiki-muted:{$theme->muted_color};--wiki-font:{$font};--wiki-page-width:{$theme->page_max_width}px}";
    }

    private function defaults(): ThemeSetting
    {
        return new ThemeSetting([
            'wiki_title' => config('app.name', 'CD Wiki'),
            'primary_color' => '#176b87',
            'background_color' => '#f6f7f9',
            'surface_color' => '#ffffff',
            'text_color' => '#1f2933',
            'muted_color' => '#617184',
            'font_family' => 'system',
            'left_sidebar_enabled' => true,
            'right_sidebar_enabled' => true,
            'page_max_width' => 1280,
        ]);
    }
}
