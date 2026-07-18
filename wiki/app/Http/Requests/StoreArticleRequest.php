<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = (string) $this->input('content', '');
        $content = (string) preg_replace('/\\\\\[\\\\\[([^\]\r\n]{1,180})\\\\\]\\\\\]/u', '[[$1]]', $content);
        $this->merge(['content' => $content]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:2000000'],
            'change_note' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Bitte einen Artikeltitel eingeben.',
            'title.max' => 'Der Artikeltitel darf höchstens 180 Zeichen lang sein.',
            'content.required' => 'Bitte einen Artikelinhalt eingeben.',
            'content.max' => 'Der Artikelinhalt ist zu groß.',
            'change_note.max' => 'Die Änderungsnotiz darf höchstens 255 Zeichen lang sein.',
            'category_ids.*.exists' => 'Eine ausgewählte Kategorie existiert nicht.',
        ];
    }
}
