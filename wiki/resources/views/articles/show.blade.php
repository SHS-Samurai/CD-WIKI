<x-wiki-layout :title="$article->title">
    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <header class="mb-8 border-b border-slate-200 pb-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <a class="text-sm font-semibold uppercase tracking-wide text-sky-700 hover:text-sky-900" href="{{ route('webs.show', $web) }}">{{ $web->title }}</a>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ $article->title }}</h1>
                    <p class="mt-3 text-sm text-slate-500">Zuletzt geändert am {{ $article->updated_at->format('d.m.Y H:i') }}@if ($article->author) von {{ $article->author->name }}@endif</p>
                    @if ($article->categories->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">@foreach ($article->categories as $category)<a class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-sky-100" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>@endforeach</div>
                    @endif
                </div>
                <div class="flex gap-2">
                    <a class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50" href="{{ route('articles.revisions.index', [$web, $article]) }}">Versionen ({{ $article->current_revision }})</a>
                    @can('update', $article)
                        <a class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50" href="{{ route('articles.edit', [$web, $article]) }}">Bearbeiten</a>
                    @endcan
                    @can('delete', $article)
                        <form method="post" action="{{ route('articles.destroy', [$web, $article]) }}" data-confirm="Artikel wirklich in den Papierkorb verschieben?">
                            @csrf @method('DELETE')
                            <button class="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" type="submit">Löschen</button>
                        </form>
                    @endcan
                </div>
            </div>
        </header>

        <div class="wiki-content">{!! $contentHtml !!}</div>
    </article>

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold">Anhänge</h2>
        <div class="mt-4 divide-y divide-slate-200">
            @forelse ($article->attachments as $attachment)
                <div class="flex flex-wrap items-center justify-between gap-4 py-3">
                    <div>
                        <a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('attachments.download', [$web, $article, $attachment]) }}">{{ $attachment->original_name }}</a>
                        <p class="mt-1 text-xs text-slate-500">Revision {{ $attachment->current_revision }} · {{ number_format($attachment->size / 1024, 1, ',', '.') }} KB</p>
                        @if ($attachment->revisions->count() > 1)
                            <details class="mt-2 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold text-sky-700">Frühere Dateiversionen</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($attachment->revisions as $revision)
                                        <li><a class="hover:text-sky-900 hover:underline" href="{{ route('attachments.revisions.download', [$web, $article, $attachment, $revision]) }}">Revision {{ $revision->revision_number }} vom {{ $revision->created_at->format('d.m.Y H:i') }}</a></li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    @can('delete', $article)<form method="post" action="{{ route('attachments.destroy', [$web, $article, $attachment]) }}" data-confirm="Anhang in den Papierkorb verschieben?">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700" type="submit">Löschen</button></form>@endcan
                </div>
            @empty
                <p class="py-4 text-sm text-slate-500">Keine Anhänge vorhanden.</p>
            @endforelse
        </div>
        @auth
            @if ($canUpload)
                <form class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-5" method="post" action="{{ route('attachments.store', [$web, $article]) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="block flex-1"><span class="text-sm font-semibold">Datei hochladen oder gleichnamigen Anhang aktualisieren</span><input class="mt-2 block w-full text-sm" name="attachment" type="file" accept=".pdf,.docx,.txt,.md,.xlsx,.html" required></label>
                    <button class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white" type="submit">Hochladen</button>
                    @error('attachment')<p class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
                </form>
            @endif
        @endauth
    </section>

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold">Kommentare</h2>
        <div class="mt-5 space-y-4">
            @forelse ($article->comments as $comment)
                <article class="rounded-lg border border-slate-200 p-4">
                    @if ($comment->trashed())
                        <p class="italic text-slate-500">Dieser Kommentar wurde entfernt.</p>
                    @else
                        <header class="flex items-center justify-between gap-4 text-sm text-slate-500"><span><strong class="text-slate-700">{{ $comment->author_name }}</strong> · {{ $comment->created_at->format('d.m.Y H:i') }}</span>@auth @if (auth()->user()->is_admin)<form method="post" action="{{ route('comments.destroy', [$web, $article, $comment]) }}" data-confirm="Kommentar wirklich entfernen?">@csrf @method('DELETE')<button class="font-semibold text-red-700" type="submit">Entfernen</button></form>@endif @endauth</header>
                        <p class="mt-3 whitespace-pre-wrap text-slate-800">{{ $comment->body }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Noch keine Kommentare vorhanden.</p>
            @endforelse
        </div>
        @auth
            @if ($canComment)
                <form class="mt-6 border-t border-slate-200 pt-5" method="post" action="{{ route('comments.store', [$web, $article]) }}">@csrf<label class="block"><span class="text-sm font-semibold">Kommentar</span><textarea class="mt-2 block min-h-28 w-full rounded-md border-slate-300" name="body" maxlength="5000" required>{{ old('body') }}</textarea></label>@error('body')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror<button class="mt-3 rounded-md bg-sky-700 px-4 py-2 font-semibold text-white" type="submit">Kommentieren</button></form>
            @endif
        @endauth
    </section>
</x-wiki-layout>
