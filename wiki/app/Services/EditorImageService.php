<?php

namespace App\Services;

use App\Models\Article;
use App\Models\EditorImage;
use App\Models\User;

class EditorImageService
{
    public function attachReferenced(Article $article, string $content, User $user): void
    {
        preg_match_all('~/medien/([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})~i', $content, $matches);
        $uuids = array_values(array_unique($matches[1] ?? []));

        if ($uuids === []) {
            return;
        }

        EditorImage::query()
            ->whereIn('uuid', $uuids)
            ->where('web_id', $article->web_id)
            ->where('user_id', $user->id)
            ->where(function ($query) use ($article): void {
                $query->whereNull('article_id')->orWhere('article_id', $article->id);
            })
            ->update(['article_id' => $article->id]);
    }
}
