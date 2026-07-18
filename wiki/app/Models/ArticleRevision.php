<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'article_id',
    'revision_number',
    'title',
    'content',
    'user_id',
    'author_name',
    'change_note',
    'created_at',
])]
class ArticleRevision extends Model
{
    public const UPDATED_AT = null;

    public function getRouteKeyName(): string
    {
        return 'revision_number';
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
