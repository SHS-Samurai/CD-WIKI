<?php

namespace App\Models;

use App\Enums\WebPermissionSubject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'web_id',
    'subject_type',
    'user_id',
    'group_id',
    'can_view',
    'can_create',
    'can_edit',
    'can_comment',
    'can_upload',
    'can_manage',
    'can_delete',
])]
class WebPermission extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (WebPermission $permission): void {
            $permission->subject_key = match ($permission->subject_type) {
                WebPermissionSubject::User => $permission->user_id !== null && $permission->group_id === null
                    ? "user:{$permission->user_id}"
                    : throw ValidationException::withMessages(['subject_type' => 'Für Benutzerrechte muss genau ein Benutzer gewählt werden.']),
                WebPermissionSubject::Group => $permission->group_id !== null && $permission->user_id === null
                    ? "group:{$permission->group_id}"
                    : throw ValidationException::withMessages(['subject_type' => 'Für Gruppenrechte muss genau eine Gruppe gewählt werden.']),
                WebPermissionSubject::Authenticated,
                WebPermissionSubject::Public => $permission->user_id === null && $permission->group_id === null
                    ? $permission->subject_type->value
                    : throw ValidationException::withMessages(['subject_type' => 'Für dieses Subjekt dürfen Benutzer und Gruppe nicht gesetzt sein.']),
            };
        });
    }

    protected function casts(): array
    {
        return [
            'subject_type' => WebPermissionSubject::class,
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_comment' => 'boolean',
            'can_upload' => 'boolean',
            'can_manage' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    public function web(): BelongsTo
    {
        return $this->belongsTo(Web::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
