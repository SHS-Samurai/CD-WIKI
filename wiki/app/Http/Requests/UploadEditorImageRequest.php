<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEditorImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'extensions:jpg,jpeg,png,gif,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Bitte ein Bild auswählen.',
            'image.image' => 'Die Datei ist kein gültiges Bild.',
            'image.mimes' => 'Erlaubt sind JPG, PNG, GIF und WebP.',
            'image.max' => 'Das Bild darf höchstens 5 MB groß sein.',
        ];
    }
}
