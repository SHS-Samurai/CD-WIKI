<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Attachment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function index(): View
    {
        return view('admin.trash.index', [
            'articles' => Article::onlyTrashed()->with('web')->latest('deleted_at')->get(),
            'attachments' => Attachment::onlyTrashed()->with('article.web')->latest('deleted_at')->get(),
        ]);
    }

    public function restoreArticle(int $article, AuditLogger $audit): RedirectResponse
    {
        $article = Article::onlyTrashed()->findOrFail($article);
        $article->restore();
        $audit->write('article.restored', $article, web: $article->web, article: $article);

        return back()->with('status', 'Der Artikel wurde wiederhergestellt.');
    }

    public function restoreAttachment(int $attachment, AuditLogger $audit): RedirectResponse
    {
        $attachment = Attachment::onlyTrashed()->with('article.web')->findOrFail($attachment);
        $attachment->restore();
        $audit->write('attachment.restored', $attachment, article: $attachment->article, web: $attachment->article->web);

        return back()->with('status', 'Der Anhang wurde wiederhergestellt.');
    }
}
