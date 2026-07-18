<x-wiki-layout title="Layout und Theme">
    <h1 class="mb-8 text-3xl font-bold">Layout und Theme</h1>
    <form class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" method="post" action="{{ route('admin.theme.update') }}">@csrf @method('PATCH')
        <label class="block"><span class="text-sm font-semibold">Wiki-Titel</span><input class="mt-2 block w-full rounded-md border-slate-300" name="wiki_title" value="{{ old('wiki_title', $theme->wiki_title) }}" maxlength="120" required></label>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (['primary_color' => 'Primärfarbe', 'background_color' => 'Hintergrund', 'surface_color' => 'Flächen', 'text_color' => 'Text', 'muted_color' => 'Sekundärtext'] as $field => $label)
                <label><span class="block text-sm font-semibold">{{ $label }}</span><input class="mt-2 h-11 w-full rounded border border-slate-300" name="{{ $field }}" type="color" value="{{ old($field, $theme->{$field}) }}"></label>
            @endforeach
        </div>
        <div class="grid gap-5 sm:grid-cols-2"><label><span class="block text-sm font-semibold">Schrift</span><select class="mt-2 block w-full rounded-md border-slate-300" name="font_family"><option value="system" @selected($theme->font_family === 'system')>Systemschrift</option><option value="serif" @selected($theme->font_family === 'serif')>Serifenschrift</option></select></label><label><span class="block text-sm font-semibold">Maximale Seitenbreite</span><input class="mt-2 block w-full rounded-md border-slate-300" name="page_max_width" type="number" min="960" max="1600" value="{{ old('page_max_width', $theme->page_max_width) }}"></label></div>
        <div class="flex flex-wrap gap-5"><label class="flex items-center gap-2"><input class="rounded" name="left_sidebar_enabled" type="checkbox" value="1" @checked(old('left_sidebar_enabled', $theme->left_sidebar_enabled))> Linke Sidebar</label><label class="flex items-center gap-2"><input class="rounded" name="right_sidebar_enabled" type="checkbox" value="1" @checked(old('right_sidebar_enabled', $theme->right_sidebar_enabled))> Rechte Sidebar</label></div>
        @if ($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <button class="rounded-md bg-sky-700 px-5 py-2.5 font-semibold text-white" type="submit">Speichern</button>
    </form>
</x-wiki-layout>
