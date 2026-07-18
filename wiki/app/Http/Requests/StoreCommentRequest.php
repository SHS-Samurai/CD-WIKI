<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Bitte einen Kommentar eingeben.',
            'body.max' => 'Der Kommentar darf höchstens 5.000 Zeichen lang sein.',
        ];
    }
}
