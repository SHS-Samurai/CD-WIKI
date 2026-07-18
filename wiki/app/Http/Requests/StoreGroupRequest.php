<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('groups')->ignore($this->route('group')?->getKey())],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
