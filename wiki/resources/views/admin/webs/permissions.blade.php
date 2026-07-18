<x-wiki-layout :title="'Web-Rechte – '.$web->title">
    <div class="mb-8">
        <a class="text-sm font-semibold text-sky-700 hover:text-sky-900" href="{{ auth()->user()->is_admin ? route('admin.webs.index') : route('webs.show', $web) }}">← {{ auth()->user()->is_admin ? 'Web-Verwaltung' : $web->title }}</a>
        <h1 class="mt-2 text-3xl font-bold">Web-Rechte: {{ $web->title }}</h1>
    </div>

    <form class="mb-8 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" method="post" action="{{ route('admin.webs.permissions.store', $web) }}">
        @csrf
        <div class="grid gap-5 md:grid-cols-3">
            <label class="block">
                <span class="text-sm font-semibold">Gilt für</span>
                <select class="mt-2 block w-full rounded-md border-slate-300" name="subject_type" data-subject-select>
                    @foreach ($subjectTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                </select>
            </label>
            <label class="block" data-subject-panel="user">
                <span class="text-sm font-semibold">Benutzer</span>
                <select class="mt-2 block w-full rounded-md border-slate-300" name="user_id">
                    <option value="">Bitte auswählen</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>@endforeach
                </select>
            </label>
            <label class="block" data-subject-panel="group">
                <span class="text-sm font-semibold">Gruppe</span>
                <select class="mt-2 block w-full rounded-md border-slate-300" name="group_id">
                    <option value="">Bitte auswählen</option>
                    @foreach ($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ $group->name }}</option>@endforeach
                </select>
            </label>
        </div>

        <fieldset>
            <legend class="text-sm font-semibold">Erlaubte Aktionen</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['view' => 'Lesen', 'create' => 'Erstellen', 'edit' => 'Bearbeiten', 'comment' => 'Kommentieren', 'upload' => 'Hochladen', 'manage' => 'Verwalten', 'delete' => 'Löschen'] as $right => $label)
                    <label class="flex items-center gap-2 rounded-md border border-slate-200 p-3"><input class="rounded border-slate-300 text-sky-700" name="can_{{ $right }}" type="checkbox" value="1" @checked(old('can_'.$right)) data-web-right="{{ $right }}"><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Öffentliche Gäste können ausschließlich Leserechte erhalten. Andere Rechte setzen Lesen automatisch voraus.</p>
        </fieldset>
        @if ($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <button class="rounded-md bg-sky-700 px-5 py-2.5 font-semibold text-white hover:bg-sky-800" type="submit">Rechte speichern</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100"><tr><th class="px-5 py-3">Subjekt</th><th class="px-5 py-3">Rechte</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($permissions as $permission)
                    <tr>
                        <td class="px-5 py-4">{{ $permission->subject_type->label() }}@if ($permission->user): {{ $permission->user->name }}@elseif ($permission->group): {{ $permission->group->name }}@endif</td>
                        <td class="px-5 py-4">{{ collect(App\Models\Web::RIGHTS)->filter(fn ($right) => $permission->{'can_'.$right})->map(fn ($right) => ['view' => 'Lesen', 'create' => 'Erstellen', 'edit' => 'Bearbeiten', 'comment' => 'Kommentieren', 'upload' => 'Hochladen', 'manage' => 'Verwalten', 'delete' => 'Löschen'][$right])->join(', ') ?: 'Keine' }}</td>
                        <td class="px-5 py-4 text-right"><form method="post" action="{{ route('admin.webs.permissions.destroy', [$web, $permission]) }}">@csrf @method('DELETE')<button class="font-semibold text-red-700 hover:text-red-900" type="submit">Entfernen</button></form></td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-center text-slate-500" colspan="3">Noch keine zusätzlichen Rechte vergeben.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-wiki-layout>
