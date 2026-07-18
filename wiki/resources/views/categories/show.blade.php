<x-wiki-layout :title="$category->name">
    <div class="mb-8">
        <a class="text-sm font-semibold text-sky-700 hover:text-sky-900" href="{{ route('categories.index') }}">← Kategorien</a>
        <h1 class="mt-2 text-3xl font-bold">{{ $category->name }}</h1>
        @if ($category->description)<p class="mt-3 text-slate-600">{{ $category->description }}</p>@endif
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($articles as $article)
            <a class="flex items-center justify-between gap-5 border-b border-slate-200 px-6 py-4 last:border-0 hover:bg-sky-50" href="{{ route('articles.show', [$article->web, $article]) }}">
                <span><strong class="block">{{ $article->title }}</strong><span class="mt-1 block text-sm text-slate-500">{{ $article->web->title }}</span></span>
                <time class="text-sm text-slate-500">{{ $article->updated_at->format('d.m.Y') }}</time>
            </a>
        @empty
            <p class="p-8 text-center text-slate-500">Keine sichtbaren Artikel in dieser Kategorie.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $articles->links() }}</div>
</x-wiki-layout>
