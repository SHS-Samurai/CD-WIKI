<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\Web;
use App\Services\ArticleRevisionService;
use App\Services\AuditLogger;
use App\Services\EditorImageService;
use App\Services\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing('groups');

        $visibleWebIds = Web::query()->with('permissions')->get()
            ->filter(fn (Web $web) => $web->hasRight($user, 'view'))
            ->modelKeys();

        $articles = Article::query()
            ->with('web')
            ->whereIn('web_id', $visibleWebIds)
            ->latest('updated_at')
            ->paginate(50);

        return view('articles.index', compact('articles'));
    }

    public function show(Request $request, Web $web, Article $article, MarkdownRenderer $renderer): View
    {
        Gate::authorize('view', $article);

        return view('articles.show', [
            'web' => $web,
            'article' => $article->load(['author', 'categories', 'comments.author', 'comments.deleter', 'attachments.revisions']),
            'contentHtml' => $renderer->render($article->content, $web),
            'canComment' => $web->hasRight($request->user(), 'comment'),
            'canUpload' => $web->hasRight($request->user(), 'upload'),
        ]);
    }

    public function create(Request $request, Web $web): View
    {
        Gate::authorize('create', [Article::class, $web]);

        return view('articles.create', [
            'web' => $web,
            'article' => new Article(['title' => trim((string) $request->query('title'))]),
            'categories' => Category::query()->orderBy('name')->get(),
            'canUpload' => $web->hasRight($request->user(), 'upload'),
        ]);
    }

    public function store(
        StoreArticleRequest $request,
        Web $web,
        ArticleRevisionService $revisions,
        EditorImageService $images,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('create', [Article::class, $web]);

        $data = $request->safe()->only(['title', 'content']);
        $article = DB::transaction(function () use ($request, $web, $data, $revisions, $images): Article {
            $article = $web->articles()->create([
                ...$data,
                'slug' => $this->uniqueSlug($web, $data['title']),
                'user_id' => $request->user()->id,
            ]);
            $revisions->record($article, $request->user(), $request->validated('change_note'));
            $article->categories()->sync($request->validated('category_ids', []));
            $images->attachReferenced($article, $data['content'], $request->user());

            return $article->refresh();
        });
        $audit->write('article.created', $article, ['title' => $article->title], article: $article, web: $web, newRevision: $article->current_revision);

        return redirect()->route('articles.show', [$web, $article])
            ->with('status', 'Der Artikel wurde angelegt.');
    }

    public function edit(Request $request, Web $web, Article $article): View
    {
        Gate::authorize('update', $article);

        return view('articles.edit', [
            'web' => $web,
            'article' => $article->load('categories'),
            'categories' => Category::query()->orderBy('name')->get(),
            'canUpload' => $web->hasRight($request->user(), 'upload'),
        ]);
    }

    public function update(
        UpdateArticleRequest $request,
        Web $web,
        Article $article,
        ArticleRevisionService $revisions,
        EditorImageService $images,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $article);
        $data = $request->safe()->only(['title', 'content']);
        $oldRevision = $article->current_revision;
        $article = DB::transaction(function () use ($request, $article, $data, $revisions, $images): Article {
            $article = $revisions->update(
                $article,
                $data,
                $request->user(),
                $request->validated('change_note'),
            );
            $article->categories()->sync($request->validated('category_ids', []));
            $images->attachReferenced($article, $data['content'], $request->user());

            return $article;
        });
        $audit->write('article.updated', $article, article: $article, web: $web, oldRevision: $oldRevision, newRevision: $article->current_revision);

        return redirect()->route('articles.show', [$web, $article])
            ->with('status', 'Der Artikel wurde gespeichert.');
    }

    public function destroy(Web $web, Article $article, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $article->delete();
        $audit->write('article.deleted', $article, article: $article, web: $web, oldRevision: $article->current_revision);

        return redirect()->route('webs.show', $web)
            ->with('status', 'Der Artikel wurde in den Papierkorb verschoben.');
    }

    private function uniqueSlug(Web $web, string $title): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $base = Str::limit($base, 110, '');
        $slug = $base;
        $suffix = 2;

        while ($web->articles()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
