<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Web;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Web $web, Article $article, AuditLogger $audit): RedirectResponse
    {
        abort_unless($web->hasRight($request->user(), 'view') && $web->hasRight($request->user(), 'comment'), 403);
        $comment = $article->comments()->create([
            'user_id' => $request->user()->id,
            'author_name' => $request->user()->name,
            'body' => $request->validated('body'),
        ]);
        $audit->write('comment.created', $comment, article: $article, web: $web);

        return back()->with('status', 'Der Kommentar wurde gespeichert.');
    }

    public function destroy(Request $request, Web $web, Article $article, Comment $comment, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $canDelete = $web->hasRight($user, 'delete') || $web->hasRight($user, 'manage');
        abort_unless($comment->article_id === $article->id && $web->hasRight($user, 'view') && $canDelete, 403);
        $comment->deleted_by = $user->id;
        $comment->save();
        $comment->delete();
        $audit->write('comment.deleted', $comment, article: $article, web: $web);

        return back()->with('status', 'Der Kommentar wurde entfernt.');
    }
}
