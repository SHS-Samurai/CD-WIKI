<?php

namespace App\Models;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'slug',
    'title',
    'description',
    'visibility',
    'created_by',
    'updated_by',
])]
class Web extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public const RIGHTS = [
        'view',
        'create',
        'edit',
        'comment',
        'upload',
        'manage',
        'delete',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => WebVisibility::class,
            'is_admin_web' => 'boolean',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(WebPermission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasRight(?User $user, string $right): bool
    {
        if (! in_array($right, self::RIGHTS, true)) {
            throw new InvalidArgumentException("Unbekanntes Web-Recht: {$right}");
        }

        if ($user !== null && $user->approved_at === null) {
            return false;
        }

        if ($right !== 'view' && ($user === null || ! $user->hasVerifiedEmail())) {
            return false;
        }

        if ($user?->is_admin) {
            return true;
        }

        if ($this->is_admin_web) {
            return false;
        }

        if ($right === 'view' && $this->visibilityGrantsView($user)) {
            return true;
        }

        $groupIds = [];
        if ($user !== null) {
            $groupIds = $user->relationLoaded('groups')
                ? $user->groups->modelKeys()
                : $user->groups()->pluck('groups.id')->all();
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(function (WebPermission $permission) use ($right, $user, $groupIds): bool {
                if (! $permission->{"can_{$right}"}) {
                    return false;
                }

                return match ($permission->subject_type) {
                    WebPermissionSubject::Public => $right === 'view',
                    WebPermissionSubject::Authenticated => $user !== null,
                    WebPermissionSubject::User => $user !== null && $permission->user_id === $user->id,
                    WebPermissionSubject::Group => in_array($permission->group_id, $groupIds, true),
                };
            });
        }

        return $this->permissions()
            ->where("can_{$right}", true)
            ->where(function ($query) use ($right, $user, $groupIds) {
                if ($right === 'view') {
                    $query->where('subject_type', WebPermissionSubject::Public->value);
                } else {
                    $query->where('subject_type', WebPermissionSubject::Authenticated->value);
                }

                if ($user === null) {
                    return;
                }

                $query->orWhere('subject_type', WebPermissionSubject::Authenticated->value)
                    ->orWhere(function ($query) use ($user) {
                        $query->where('subject_type', WebPermissionSubject::User->value)
                            ->where('user_id', $user->id);
                    });

                if ($groupIds !== []) {
                    $query->orWhere(function ($query) use ($groupIds) {
                        $query->where('subject_type', WebPermissionSubject::Group->value)
                            ->whereIn('group_id', $groupIds);
                    });
                }
            })
            ->exists();
    }

    private function visibilityGrantsView(?User $user): bool
    {
        return $this->visibility === WebVisibility::Public
            || ($this->visibility === WebVisibility::Authenticated && $user !== null);
    }
}
