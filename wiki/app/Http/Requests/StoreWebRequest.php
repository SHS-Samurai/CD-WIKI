<?php

namespace App\Http\Requests;

use App\Enums\WebVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        $web = $this->route('web');

        return (bool) ($this->user()?->is_admin || ($web && $web->hasRight($this->user(), 'manage')));
    }

    public function rules(): array
    {
        $webId = $this->route('web')?->getKey();

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('webs', 'slug')->ignore($webId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', Rule::enum(WebVisibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Bitte einen Namen für das Web eingeben.',
            'slug.required' => 'Bitte eine URL-Kennung eingeben.',
            'slug.alpha_dash' => 'Die URL-Kennung darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'slug.unique' => 'Diese URL-Kennung wird bereits verwendet.',
            'visibility.required' => 'Bitte eine Sichtbarkeit auswählen.',
        ];
    }
}
