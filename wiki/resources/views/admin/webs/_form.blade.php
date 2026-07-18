<form class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" method="post" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <label class="block">
        <span class="text-sm font-semibold">Name</span>
        <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="title" type="text" value="{{ old('title', $web->title) }}" maxlength="160" required>
        @error('title')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block">
        <span class="text-sm font-semibold">URL-Kennung</span>
        <input class="mt-2 block w-full rounded-md border-slate-300 font-mono focus:border-sky-600 focus:ring-sky-600" name="slug" type="text" value="{{ old('slug', $web->slug) }}" maxlength="80" required pattern="[A-Za-z0-9_-]+">
        <span class="mt-1 block text-sm text-slate-500">Zum Beispiel: technik-handbuch</span>
        @error('slug')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block">
        <span class="text-sm font-semibold">Beschreibung</span>
        <textarea class="mt-2 block min-h-28 w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="description">{{ old('description', $web->description) }}</textarea>
        @error('description')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>
    <label class="block">
        <span class="text-sm font-semibold">Sichtbarkeit</span>
        <select class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="visibility" required>
            @foreach ($visibilities as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $web->visibility?->value) === $visibility->value)>{{ $visibility->label() }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-sm text-slate-500">Bei „Benutzer“ und „Gruppen“ wird der Zugriff anschließend über Web-Rechte vergeben.</span>
        @error('visibility')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
    </label>

    <div class="flex gap-3">
        <button class="rounded-md bg-sky-700 px-5 py-2.5 font-semibold text-white hover:bg-sky-800" type="submit">Speichern</button>
        <a class="rounded-md px-4 py-2.5 text-slate-600 hover:bg-slate-100" href="{{ route('admin.webs.index') }}">Abbrechen</a>
    </div>
</form>
