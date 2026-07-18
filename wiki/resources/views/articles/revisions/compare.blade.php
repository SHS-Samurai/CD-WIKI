<x-wiki-layout :title="'Versionsvergleich – '.$article->title">
    <div class="mb-8">
        <a class="text-sm font-semibold text-sky-700 hover:text-sky-900" href="{{ route('articles.revisions.index', [$web, $article]) }}">← Versionsverlauf</a>
        <h1 class="mt-2 text-3xl font-bold">Versionsvergleich</h1>
        <p class="mt-2 text-slate-600">Revision {{ $from->revision_number }} → Revision {{ $to->revision_number }}</p>
    </div>

    <div class="mb-4 flex flex-wrap gap-4 text-sm">
        <span class="rounded bg-red-100 px-3 py-1 text-red-900">− Entfernt</span>
        <span class="rounded bg-emerald-100 px-3 py-1 text-emerald-900">+ Hinzugefügt</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white font-mono text-sm shadow-sm">
        @foreach ($changes as $change)
            <div class="grid min-h-7 grid-cols-[2rem_1fr] border-b border-slate-100 last:border-0 {{ $change['type'] === 'remove' ? 'bg-red-50 text-red-900' : ($change['type'] === 'add' ? 'bg-emerald-50 text-emerald-900' : 'text-slate-600') }}">
                <span class="select-none px-2 py-1 text-center">{{ $change['type'] === 'remove' ? '−' : ($change['type'] === 'add' ? '+' : '') }}</span>
                <span class="whitespace-pre-wrap break-words border-l border-slate-200 px-3 py-1">{{ $change['line'] }}</span>
            </div>
        @endforeach
    </div>
</x-wiki-layout>
