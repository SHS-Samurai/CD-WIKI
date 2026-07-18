<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_admin' => $this->boolean('is_admin')]);
        $this->merge(['is_approved' => $this->boolean('is_approved')]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
            'is_admin' => ['boolean'],
            'is_approved' => ['boolean'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'distinct', 'exists:groups,id'],
        ];
    }
}
