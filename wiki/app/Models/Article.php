<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['web_id', 'title', 'slug', 'content', 'current_revision', 'change_note', 'user_id'])]
class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            $article->title_key = mb_strtolower(trim($article->title));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function web(): BelongsTo
    {
        return $this->belongsTo(Web::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('revision_number');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function editorImages(): HasMany
    {
        return $this->hasMany(EditorImage::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->withTrashed()->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('original_name');
    }
}
