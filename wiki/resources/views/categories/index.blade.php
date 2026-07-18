<x-wiki-layout title="Kategorien">
    <div class="mb-8 flex items-center justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Kategorien</h1><p class="mt-2 text-slate-600">Thematisch geordnete Artikel.</p></div>
        @auth @if (auth()->user()->is_admin)<a class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-800" href="{{ route('admin.categories.index') }}">Kategorien verwalten</a>@endif @endauth
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($categories as $category)
            <a class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-sky-400 hover:bg-sky-50" href="{{ route('categories.show', $category) }}">
                <h2 class="font-semibold text-slate-950">{{ $category->name }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $category->description ?: $category->articles_count.' sichtbare Artikel' }}</p>
                <span class="mt-3 block text-xs font-semibold uppercase tracking-wide text-sky-700">{{ $category->articles_count }} Artikel</span>
            </a>
        @empty
            <p class="col-span-full rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Noch keine Kategorien vorhanden.</p>
        @endforelse
    </div>
</x-wiki-layout>
