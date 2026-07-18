<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArticleRevisionService
{
    public function record(Article $article, User $user, ?string $changeNote = null): ArticleRevision
    {
        return DB::transaction(function () use ($article, $user, $changeNote): ArticleRevision {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);

            return $this->recordLocked($locked, $user, $changeNote);
        });
    }

    /** @param array{title: string, content: string} $data */
    public function update(Article $article, array $data, User $user, ?string $changeNote = null): Article
    {
        return DB::transaction(function () use ($article, $data, $user, $changeNote): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);
            $locked->fill($data);
            $locked->user_id = $user->id;
            $this->recordLocked($locked, $user, $changeNote);

            return $locked;
        });
    }

    public function restore(Article $article, ArticleRevision $revision, User $user): Article
    {
        return DB::transaction(function () use ($article, $revision, $user): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->id);
            $snapshot = ArticleRevision::query()
                ->where('article_id', $locked->id)
                ->whereKey($revision->id)
                ->firstOrFail();

            $locked->title = $snapshot->title;
            $locked->content = $snapshot->content;
            $locked->user_id = $user->id;
            $this->recordLocked(
                $locked,
                $user,
                "Revision {$snapshot->revision_number} wiederhergestellt",
            );

            return $locked;
        });
    }

    private function recordLocked(Article $article, User $user, ?string $changeNote): ArticleRevision
    {
        $number = $article->current_revision + 1;
        $note = filled($changeNote) ? trim($changeNote) : null;

        $article->current_revision = $number;
        $article->change_note = $note;
        $article->save();

        return $article->revisions()->create([
            'revision_number' => $number,
            'title' => $article->title,
            'content' => $article->content,
            'user_id' => $user->id,
            'author_name' => $user->name,
            'change_note' => $note,
            'created_at' => now(),
        ]);
    }
}
