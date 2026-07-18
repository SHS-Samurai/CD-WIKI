<x-wiki-layout :title="$web->title">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">Web</p>
            <h1 class="mt-1 text-3xl font-bold">{{ $web->title }}</h1>
            @if ($web->description)<p class="mt-3 max-w-3xl text-slate-600">{{ $web->description }}</p>@endif
        </div>
        @auth
            <div class="flex flex-wrap gap-3">
                @if ($web->hasRight(auth()->user(), 'manage'))
                    <a class="rounded-md border border-sky-700 px-4 py-2 font-semibold text-sky-700 hover:bg-sky-50" href="{{ route('admin.webs.permissions.index', $web) }}">Web verwalten</a>
                @endif
                @can('create', [App\Models\Article::class, $web])
                    <a class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-800" href="{{ route('articles.create', $web) }}">Neuer Artikel</a>
                @endcan
            </div>
        @endauth
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($articles as $article)
            <a class="flex items-center justify-between gap-5 border-b border-slate-200 px-6 py-4 last:border-0 hover:bg-sky-50" href="{{ route('articles.show', [$web, $article]) }}">
                <strong>{{ $article->title }}</strong>
                <time class="text-sm text-slate-500">{{ $article->updated_at->format('d.m.Y H:i') }}</time>
            </a>
        @empty
            <p class="p-8 text-center text-slate-500">In diesem Web gibt es noch keine Artikel.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $articles->links() }}</div>
</x-wiki-layout>
