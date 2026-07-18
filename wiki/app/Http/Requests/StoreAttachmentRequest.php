<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['attachment' => ['required', 'file', 'max:25600']];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Bitte eine Datei auswählen.',
            'attachment.file' => 'Der Upload ist keine gültige Datei.',
            'attachment.max' => 'Die Datei darf höchstens 25 MB groß sein.',
        ];
    }
}
