<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'left_sidebar_enabled' => $this->boolean('left_sidebar_enabled'),
            'right_sidebar_enabled' => $this->boolean('right_sidebar_enabled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'wiki_title' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'surface_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'muted_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_family' => ['required', Rule::in(['system', 'serif'])],
            'left_sidebar_enabled' => ['boolean'],
            'right_sidebar_enabled' => ['boolean'],
            'page_max_width' => ['required', 'integer', 'between:960,1600'],
        ];
    }

    public function messages(): array
    {
        return [
            'wiki_title.required' => 'Bitte einen Wiki-Titel eingeben.',
            '*.regex' => 'Farben müssen im Format #RRGGBB angegeben werden.',
            'page_max_width.between' => 'Die Seitenbreite muss zwischen 960 und 1.600 Pixeln liegen.',
        ];
    }
}
