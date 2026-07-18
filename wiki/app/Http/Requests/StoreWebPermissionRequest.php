<?php

namespace App\Http\Requests;

use App\Enums\WebPermissionSubject;
use App\Models\Web;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->route('web')?->hasRight($this->user(), 'manage');
    }

    protected function prepareForValidation(): void
    {
        $rights = [];
        foreach (Web::RIGHTS as $right) {
            $rights["can_{$right}"] = $this->boolean("can_{$right}");
        }

        if (collect($rights)->except('can_view')->contains(true)) {
            $rights['can_view'] = true;
        }

        $this->merge($rights);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('subject_type') !== WebPermissionSubject::Public->value) {
                return;
            }

            foreach (array_diff(Web::RIGHTS, ['view']) as $right) {
                if ($this->boolean("can_{$right}")) {
                    $validator->errors()->add("can_{$right}", 'Öffentliche Gäste dürfen ausschließlich Leserechte erhalten.');
                }
            }
        }];
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::enum(WebPermissionSubject::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'can_view' => ['boolean'],
            'can_create' => ['boolean'],
            'can_edit' => ['boolean'],
            'can_comment' => ['boolean'],
            'can_upload' => ['boolean'],
            'can_manage' => ['boolean'],
            'can_delete' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_type.required' => 'Bitte ein Rechtssubjekt auswählen.',
            'user_id.exists' => 'Der ausgewählte Benutzer existiert nicht.',
            'group_id.exists' => 'Die ausgewählte Gruppe existiert nicht.',
        ];
    }
}
