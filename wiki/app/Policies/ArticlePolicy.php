<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use App\Models\Web;

class ArticlePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        return $article->web->hasRight($user, 'view');
    }

    public function create(User $user, Web $web): bool
    {
        return $web->hasRight($user, 'view') && $web->hasRight($user, 'create');
    }

    public function update(User $user, Article $article): bool
    {
        return $article->web->hasRight($user, 'view')
            && $article->web->hasRight($user, 'edit');
    }

    public function delete(User $user, Article $article): bool
    {
        return $article->web->hasRight($user, 'view')
            && $article->web->hasRight($user, 'delete');
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }
}
