<x-wiki-layout title="Alle Artikel">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Alle sichtbaren Artikel</h1>
        <p class="mt-2 text-slate-600">Zuletzt bearbeitete Inhalte aus allen Webs.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($articles as $article)
            <a class="flex items-center justify-between gap-5 border-b border-slate-200 px-6 py-4 last:border-0 hover:bg-sky-50" href="{{ route('articles.show', [$article->web, $article]) }}">
                <span>
                    <strong class="block text-slate-950">{{ $article->title }}</strong>
                    <span class="mt-1 block text-sm text-slate-500">{{ $article->web->title }}</span>
                </span>
                <time class="shrink-0 text-sm text-slate-500" datetime="{{ $article->updated_at->toAtomString() }}">{{ $article->updated_at->format('d.m.Y H:i') }}</time>
            </a>
        @empty
            <p class="p-8 text-center text-slate-500">Noch keine Artikel vorhanden.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $articles->links() }}</div>
</x-wiki-layout>
