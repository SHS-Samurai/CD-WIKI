<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'article_id', 'original_name', 'storage_name', 'path', 'mime_type', 'search_text', 'size', 'current_revision', 'uploaded_by', 'updated_by'])]
class Attachment extends Model
{
    use SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AttachmentRevision::class)->orderByDesc('revision_number');
    }
}
