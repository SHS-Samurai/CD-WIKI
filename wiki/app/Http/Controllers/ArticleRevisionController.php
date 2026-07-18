<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Web;
use App\Services\ArticleRevisionService;
use App\Services\AuditLogger;
use App\Services\LineDiff;
use App\Services\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleRevisionController extends Controller
{
    public function index(Web $web, Article $article): View
    {
        Gate::authorize('view', $article);
        $revisions = $article->revisions()->with('author')->paginate(30);
        $revisionNumbers = $article->revisions()->pluck('revision_number');

        return view('articles.revisions.index', compact('web', 'article', 'revisions', 'revisionNumbers'));
    }

    public function show(
        Web $web,
        Article $article,
        ArticleRevision $revision,
        MarkdownRenderer $renderer,
    ): View {
        Gate::authorize('view', $article);

        return view('articles.revisions.show', [
            'web' => $web,
            'article' => $article,
            'revision' => $revision->load('author'),
            'contentHtml' => $renderer->render($revision->content, $web),
        ]);
    }

    public function compare(Request $request, Web $web, Article $article, LineDiff $diff): View
    {
        Gate::authorize('view', $article);
        $available = $article->revisions()->pluck('revision_number')->all();
        $validated = $request->validate([
            'from' => ['required', 'integer', Rule::in($available)],
            'to' => ['required', 'integer', Rule::in($available)],
        ], [
            'from.required' => 'Bitte eine Ausgangsversion auswählen.',
            'from.in' => 'Die gewählte Ausgangsversion existiert nicht.',
            'to.required' => 'Bitte eine Zielversion auswählen.',
            'to.in' => 'Die gewählte Zielversion existiert nicht.',
        ]);

        $from = $article->revisions()->where('revision_number', $validated['from'])->firstOrFail();
        $to = $article->revisions()->where('revision_number', $validated['to'])->firstOrFail();

        return view('articles.revisions.compare', [
            'web' => $web,
            'article' => $article,
            'from' => $from,
            'to' => $to,
            'changes' => $diff->compare(
                "Titel: {$from->title}\n\n{$from->content}",
                "Titel: {$to->title}\n\n{$to->content}",
            ),
        ]);
    }

    public function restore(
        Request $request,
        Web $web,
        Article $article,
        ArticleRevision $revision,
        ArticleRevisionService $revisions,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $article);
        $oldRevision = $article->current_revision;
        $article = $revisions->restore($article, $revision, $request->user());
        $audit->write('article.revision_restored', $revision, ['restored_revision' => $revision->revision_number], article: $article, web: $web, oldRevision: $oldRevision, newRevision: $article->current_revision);

        return redirect()->route('articles.show', [$web, $article])
            ->with('status', "Revision {$revision->revision_number} wurde als neue Revision wiederhergestellt.");
    }
}
