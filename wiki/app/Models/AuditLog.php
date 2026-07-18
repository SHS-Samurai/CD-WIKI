<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['action', 'user_id', 'user_name', 'ip_address', 'user_agent', 'web_id', 'article_id', 'target_type', 'target_id', 'old_revision', 'new_revision', 'details', 'created_at'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Auditlog-Einträge sind unveränderlich.'));
        static::deleting(fn () => throw new LogicException('Auditlog-Einträge dürfen nicht gelöscht werden.'));
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function web(): BelongsTo
    {
        return $this->belongsTo(Web::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
