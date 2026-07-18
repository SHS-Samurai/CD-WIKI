<x-wiki-layout :title="'Versionen – '.$article->title">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-5">
        <div>
            <a class="text-sm font-semibold text-sky-700 hover:text-sky-900" href="{{ route('articles.show', [$web, $article]) }}">← {{ $article->title }}</a>
            <h1 class="mt-2 text-3xl font-bold">Versionsverlauf</h1>
            <p class="mt-2 text-slate-600">Aktuelle Revision: {{ $article->current_revision }}</p>
        </div>
    </div>

    @if ($revisionNumbers->count() >= 2)
        <form class="mb-8 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" method="get" action="{{ route('articles.revisions.compare', [$web, $article]) }}">
            <label>
                <span class="block text-sm font-semibold">Von Revision</span>
                <select class="mt-2 rounded-md border-slate-300" name="from">
                    @foreach ($revisionNumbers as $number)<option value="{{ $number }}" @selected($loop->index === 1)>{{ $number }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="block text-sm font-semibold">Zu Revision</span>
                <select class="mt-2 rounded-md border-slate-300" name="to">
                    @foreach ($revisionNumbers as $number)<option value="{{ $number }}">{{ $number }}</option>@endforeach
                </select>
            </label>
            <button class="rounded-md bg-sky-700 px-4 py-2.5 font-semibold text-white hover:bg-sky-800" type="submit">Unterschiede anzeigen</button>
        </form>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($revisions as $revision)
            <a class="block border-b border-slate-200 px-6 py-5 last:border-0 hover:bg-sky-50" href="{{ route('articles.revisions.show', [$web, $article, $revision]) }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <strong>Revision {{ $revision->revision_number }}@if ($revision->revision_number === $article->current_revision) <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Aktuell</span>@endif</strong>
                    <time class="text-sm text-slate-500" datetime="{{ $revision->created_at->toAtomString() }}">{{ $revision->created_at->format('d.m.Y H:i:s') }}</time>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ $revision->author_name ?: $revision->author?->name ?: 'Unbekannt' }}</p>
                @if ($revision->change_note)<p class="mt-2 text-sm text-slate-700">{{ $revision->change_note }}</p>@endif
            </a>
        @empty
            <p class="p-8 text-center text-slate-500">Keine Revisionen vorhanden.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $revisions->links() }}</div>
</x-wiki-layout>
