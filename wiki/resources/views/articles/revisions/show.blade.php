<x-wiki-layout :title="'Revision '.$revision->revision_number.' – '.$article->title">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-5">
        <div>
            <a class="text-sm font-semibold text-sky-700 hover:text-sky-900" href="{{ route('articles.revisions.index', [$web, $article]) }}">← Versionsverlauf</a>
            <h1 class="mt-2 text-3xl font-bold">Revision {{ $revision->revision_number }}: {{ $revision->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $revision->created_at->format('d.m.Y H:i:s') }} · {{ $revision->author_name ?: $revision->author?->name ?: 'Unbekannt' }}</p>
            @if ($revision->change_note)<p class="mt-2 text-slate-700">{{ $revision->change_note }}</p>@endif
        </div>
        <div class="flex gap-2">
            <a class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50" href="{{ route('articles.show', [$web, $article]) }}">Aktuelle Seite</a>
            @can('update', $article)
                @if ($revision->revision_number !== $article->current_revision)
                    <form method="post" action="{{ route('articles.revisions.restore', [$web, $article, $revision]) }}" data-confirm="Diese Revision als neue Version wiederherstellen?">
                        @csrf
                        <button class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800" type="submit">Wiederherstellen</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="wiki-content">{!! $contentHtml !!}</div>
    </article>
</x-wiki-layout>
