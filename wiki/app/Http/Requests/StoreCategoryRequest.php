<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->getKey();

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($categoryId)],
            'slug' => ['required', 'alpha_dash:ascii', 'max:120', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte einen Kategorienamen eingeben.',
            'name.unique' => 'Dieser Kategoriename wird bereits verwendet.',
            'slug.required' => 'Bitte eine URL-Kennung eingeben.',
            'slug.alpha_dash' => 'Die URL-Kennung darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'slug.unique' => 'Diese URL-Kennung wird bereits verwendet.',
        ];
    }
}
