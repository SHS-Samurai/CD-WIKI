<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'web_id', 'article_id', 'user_id', 'path', 'original_name', 'mime_type', 'size'])]
class EditorImage extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function web(): BelongsTo
    {
        return $this->belongsTo(Web::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
