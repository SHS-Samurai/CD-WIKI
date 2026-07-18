<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wiki_title', 'primary_color', 'background_color', 'surface_color', 'text_color', 'muted_color', 'font_family', 'left_sidebar_enabled', 'right_sidebar_enabled', 'page_max_width'])]
class ThemeSetting extends Model
{
    protected function casts(): array
    {
        return [
            'left_sidebar_enabled' => 'boolean',
            'right_sidebar_enabled' => 'boolean',
            'page_max_width' => 'integer',
        ];
    }
}
