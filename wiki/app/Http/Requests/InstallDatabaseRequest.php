<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class InstallDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! is_file(storage_path('app/installed'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host' => '127.0.0.1',
            'port' => 3306,
        ]);
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'in:127.0.0.1'],
            'port' => ['required', 'integer', 'in:3306'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
            'setup_token' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'host.in' => 'Der Datenbankserver ist fest auf den lokalen Server eingestellt.',
            'port.in' => 'Der Datenbankport ist fest auf 3306 eingestellt.',
            'database.required' => 'Bitte den Datenbanknamen eingeben.',
            'database.regex' => 'Der Datenbankname enthält ungültige Zeichen.',
            'username.required' => 'Bitte den Datenbankbenutzer eingeben.',
            'admin_name.required' => 'Bitte den Namen des Administrators eingeben.',
            'admin_email.required' => 'Bitte die E-Mail-Adresse des Administrators eingeben.',
            'admin_email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            'admin_password.required' => 'Bitte ein Administrator-Passwort eingeben.',
            'admin_password.confirmed' => 'Die Passwortbestätigung stimmt nicht überein.',
        ];
    }
}
