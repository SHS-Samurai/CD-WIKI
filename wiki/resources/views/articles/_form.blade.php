<form method="post" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="block">
            <span class="text-sm font-semibold">Titel</span>
            <input class="mt-2 block w-full rounded-md border-slate-300 text-lg focus:border-sky-600 focus:ring-sky-600" name="title" type="text" value="{{ old('title', $article->title) }}" maxlength="180" required autofocus>
            @error('title')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>

        <div data-wiki-editor @if ($canUpload) data-upload-url="{{ route('editor-images.store', $web) }}" @endif>
            <span class="mb-2 block text-sm font-semibold">Inhalt</span>
            <div class="flex flex-wrap gap-1 rounded-t-lg border border-b-0 border-slate-300 bg-slate-100 p-2" aria-label="Editor-Werkzeuge">
                @foreach ([
                    'paragraph' => 'Text', 'heading1' => 'H1', 'heading2' => 'H2',
                    'bold' => 'Fett', 'italic' => 'Kursiv', 'bulletList' => 'Liste',
                    'orderedList' => 'Nummern', 'blockquote' => 'Zitat', 'codeBlock' => 'Code',
                    'link' => 'Link', 'wikiLink' => 'Wiki-Link', 'table' => 'Tabelle',
                    'horizontalRule' => 'Trennlinie', 'undo' => 'Zurück', 'redo' => 'Vor',
                ] as $command => $label)
                    <button class="rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium hover:border-sky-500 hover:bg-sky-50" type="button" data-editor-command="{{ $command }}">{{ $label }}</button>
                @endforeach
                @if ($canUpload)
                    <button class="rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium hover:border-sky-500 hover:bg-sky-50" type="button" data-image-upload>Bild</button>
                @endif
            </div>
            <div class="hidden border border-slate-300" data-editor-surface></div>
            <textarea class="block min-h-96 w-full rounded-b-lg border-slate-300 font-mono text-sm focus:border-sky-600 focus:ring-sky-600" name="content" required>{{ old('content', $article->content) }}</textarea>
            <p class="mt-2 text-sm text-slate-500">Wiki-Links werden als <code>[[Artikeltitel]]</code> gespeichert.</p>
            @error('content')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </div>

        @if ($categories->isNotEmpty())
            <fieldset>
                <legend class="text-sm font-semibold">Kategorien</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($categories as $category)
                        <label class="flex items-center gap-2 rounded-md border border-slate-200 p-3">
                            <input class="rounded border-slate-300 text-sky-700" name="category_ids[]" type="checkbox" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $article->categories->modelKeys() ?? [])))>
                            <span>{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('category_ids.*')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </fieldset>
        @endif

        <label class="block">
            <span class="text-sm font-semibold">Änderungsnotiz <span class="font-normal text-slate-500">(optional)</span></span>
            <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="change_note" type="text" value="{{ old('change_note') }}" maxlength="255" placeholder="Was wurde geändert?">
            @error('change_note')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>

        <div class="flex items-center gap-3">
            <button class="rounded-md bg-sky-700 px-5 py-2.5 font-semibold text-white hover:bg-sky-800" type="submit">Speichern</button>
            <a class="rounded-md px-4 py-2.5 text-slate-600 hover:bg-slate-100" href="{{ $cancelUrl }}">Abbrechen</a>
        </div>
    </div>
</form>
