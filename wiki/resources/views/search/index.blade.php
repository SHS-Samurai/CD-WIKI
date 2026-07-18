<x-wiki-layout title="Suche">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Wiki durchsuchen</h1>
        <form class="mt-5 flex max-w-3xl" method="get" action="{{ route('search') }}">
            <label class="sr-only" for="search-query">Suchbegriff</label>
            <input class="min-w-0 flex-1 rounded-l-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" id="search-query" name="q" type="search" value="{{ $query }}" minlength="2" maxlength="100" required autofocus>
            <button class="rounded-r-md bg-sky-700 px-5 py-2 font-semibold text-white hover:bg-sky-800" type="submit">Suchen</button>
        </form>
        @error('q')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    @if ($results !== null)
        <p class="mb-5 text-sm text-slate-500">{{ $results->total() }} Treffer für „{{ $query }}“</p>
        <div class="space-y-4">
            @forelse ($results as $article)
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <a class="text-xl font-semibold text-sky-800 hover:text-sky-950" href="{{ route('articles.show', [$article->web, $article]) }}">{{ $article->title }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $article->web->title }}</p>
                    <p class="mt-3 text-slate-700">{{ $article->excerpt }}</p>
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Keine passenden Artikel gefunden.</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $results->links() }}</div>
    @endif
</x-wiki-layout>
