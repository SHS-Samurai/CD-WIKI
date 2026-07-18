<x-wiki-layout title="Web-Verwaltung">
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Web-Verwaltung</h1>
            <p class="mt-2 text-slate-600">Webs und ihre Zugriffsrechte verwalten.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.users.index') }}">Benutzer</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.groups.index') }}">Gruppen</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.categories.index') }}">Kategorien</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.theme.edit') }}">Theme</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.settings.edit') }}">Einstellungen</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.trash.index') }}">Papierkorb</a>
            <a class="rounded-md border border-slate-300 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="{{ route('admin.audit.index') }}">Auditlog</a>
            <a class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-800" href="{{ route('admin.webs.create') }}">Web anlegen</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700">
                <tr><th class="px-5 py-3">Web</th><th class="px-5 py-3">Sichtbarkeit</th><th class="px-5 py-3">Artikel</th><th class="px-5 py-3">Rechte</th><th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($webs as $web)
                    <tr>
                        <td class="px-5 py-4"><strong>{{ $web->title }}</strong><span class="block text-slate-500">{{ $web->slug }}</span></td>
                        <td class="px-5 py-4">{{ $web->visibility->label() }}</td>
                        <td class="px-5 py-4">{{ $web->articles_count }}</td>
                        <td class="px-5 py-4">{{ $web->permissions_count }}</td>
                        <td class="px-5 py-4 text-right">
                            @unless ($web->is_admin_web)
                                <a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('admin.webs.edit', $web) }}">Bearbeiten</a>
                                <a class="ml-4 font-semibold text-sky-700 hover:text-sky-900" href="{{ route('admin.webs.permissions.index', $web) }}">Rechte</a>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-wiki-layout>
