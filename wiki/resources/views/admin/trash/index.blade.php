<x-wiki-layout title="Papierkorb">
    <h1 class="text-3xl font-bold">Papierkorb</h1><p class="mt-2 text-slate-600">Gelöschte Inhalte bleiben erhalten und können wiederhergestellt werden.</p>
    <section class="mt-8"><h2 class="mb-4 text-2xl font-bold">Artikel</h2><div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($articles as $article)<div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 last:border-0"><span><strong>{{ $article->title }}</strong><small class="ml-2 text-slate-500">{{ $article->web->title }} · {{ $article->deleted_at->format('d.m.Y H:i') }}</small></span><form method="post" action="{{ route('admin.trash.articles.restore', $article->id) }}">@csrf<button class="font-semibold text-sky-700" type="submit">Wiederherstellen</button></form></div>@empty<p class="p-6 text-slate-500">Keine gelöschten Artikel.</p>@endforelse
    </div></section>
    <section class="mt-8"><h2 class="mb-4 text-2xl font-bold">Anhänge</h2><div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($attachments as $attachment)<div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 last:border-0"><span><strong>{{ $attachment->original_name }}</strong><small class="ml-2 text-slate-500">{{ $attachment->article->title }}</small></span><form method="post" action="{{ route('admin.trash.attachments.restore', $attachment->id) }}">@csrf<button class="font-semibold text-sky-700" type="submit">Wiederherstellen</button></form></div>@empty<p class="p-6 text-slate-500">Keine gelöschten Anhänge.</p>@endforelse
    </div></section>
</x-wiki-layout>
