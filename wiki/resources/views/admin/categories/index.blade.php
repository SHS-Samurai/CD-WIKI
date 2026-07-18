<x-wiki-layout title="Kategorieverwaltung">
    <div class="mb-8 flex items-center justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Kategorieverwaltung</h1><p class="mt-2 text-slate-600">Kategorien für Artikel anlegen und bearbeiten.</p></div>
        <a class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-800" href="{{ route('admin.categories.create') }}">Kategorie anlegen</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($categories as $category)
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 last:border-0">
                <div><strong>{{ $category->name }}</strong><span class="ml-3 text-sm text-slate-500">{{ $category->articles_count }} Artikel · {{ $category->slug }}</span></div>
                <div class="flex items-center gap-4">
                    <a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('admin.categories.edit', $category) }}">Bearbeiten</a>
                    <form method="post" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Kategorie wirklich löschen? Die Artikel bleiben erhalten.">@csrf @method('DELETE')<button class="font-semibold text-red-700 hover:text-red-900" type="submit">Löschen</button></form>
                </div>
            </div>
        @empty
            <p class="p-8 text-center text-slate-500">Noch keine Kategorien vorhanden.</p>
        @endforelse
    </div>
</x-wiki-layout>
