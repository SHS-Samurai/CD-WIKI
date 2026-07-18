@props(['title' => null])
@php($wikiTheme = app(App\Services\ThemeService::class)->current())

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' – '.$wikiTheme->wiki_title : $wikiTheme->wiki_title }}</title>
        <link rel="stylesheet" href="{{ route('theme.css', ['v' => $wikiTheme->updated_at?->timestamp ?? 1]) }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="wiki-themed min-h-screen antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="wiki-surface border-b shadow-sm">
                <div class="wiki-container flex flex-wrap items-center gap-4 px-4 py-4 sm:px-6">
                    <a class="text-xl font-bold tracking-tight text-sky-800" href="{{ route('home') }}">{{ $wikiTheme->wiki_title }}</a>
                    <nav class="flex flex-wrap items-center gap-1 text-sm font-medium" aria-label="Hauptnavigation">
                        <a class="rounded-md px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('home', 'webs.*') ? 'bg-sky-50 text-sky-800' : 'text-slate-700' }}" href="{{ route('home') }}">Webs</a>
                        <a class="rounded-md px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('articles.*') ? 'bg-sky-50 text-sky-800' : 'text-slate-700' }}" href="{{ route('articles.index') }}">Alle Artikel</a>
                        <a class="rounded-md px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('categories.*') ? 'bg-sky-50 text-sky-800' : 'text-slate-700' }}" href="{{ route('categories.index') }}">Kategorien</a>
                    </nav>
                    <form class="flex min-w-64 flex-1 sm:justify-end" method="get" action="{{ route('search') }}">
                        <label class="sr-only" for="global-search">Wiki durchsuchen</label>
                        <input class="w-full max-w-sm rounded-l-md border-slate-300 text-sm focus:border-sky-600 focus:ring-sky-600" id="global-search" name="q" type="search" value="{{ request('q') }}" placeholder="Wiki durchsuchen">
                        <button class="rounded-r-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800" type="submit">Suchen</button>
                    </form>
                    <div class="flex items-center gap-2 text-sm">
                        @auth
                            @if (auth()->user()->is_admin)<a class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 {{ request()->routeIs('admin.*') ? 'bg-sky-50 text-sky-800' : '' }}" href="{{ route('admin.webs.index') }}">Verwaltung</a>@endif
                            <a class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100" href="{{ route('profile.edit') }}">Profil</a>
                            <form method="post" action="{{ route('logout') }}">@csrf<button class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100" type="submit">Abmelden</button></form>
                        @else
                            <a class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100" href="{{ route('login') }}">Anmelden</a>
                            @if (Route::has('register') && app(App\Services\SystemSettings::class)->registrationMode() !== 'closed')<a class="rounded-md bg-sky-700 px-3 py-2 font-semibold text-white hover:bg-sky-800" href="{{ route('register') }}">Registrieren</a>@endif
                        @endauth
                    </div>
                </div>
            </header>

            <div class="wiki-container wiki-page-grid flex-1 px-4 py-8 sm:px-6 {{ $wikiTheme->left_sidebar_enabled ? 'has-left-sidebar' : '' }} {{ $wikiTheme->right_sidebar_enabled ? 'has-right-sidebar' : '' }}">
                @if ($wikiTheme->left_sidebar_enabled)
                    <aside class="wiki-sidebar wiki-sidebar-left" aria-label="Seitennavigation">
                        <nav class="grid gap-1 text-sm">
                            <a href="{{ route('home') }}">Webs</a><a href="{{ route('articles.index') }}">Artikel</a><a href="{{ route('categories.index') }}">Kategorien</a><a href="{{ route('search') }}">Suche</a>
                        </nav>
                    </aside>
                @endif
                <main class="min-w-0">
                    @if (session('status'))<div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
                    {{ $slot }}
                </main>
                @if ($wikiTheme->right_sidebar_enabled)
                    <aside class="wiki-sidebar wiki-sidebar-right" aria-label="Werkzeuge">
                        <nav class="grid gap-1 text-sm">
                            @auth
                                @if (auth()->user()->is_admin)
                                    <a href="{{ route('admin.webs.index') }}">Verwaltung</a><a href="{{ route('admin.trash.index') }}">Papierkorb</a><a href="{{ route('admin.audit.index') }}">Auditlog</a>
                                @endif
                                <a href="{{ route('profile.edit') }}">Profil</a>
                                <form method="post" action="{{ route('logout') }}">@csrf<button class="wiki-sidebar-button" type="submit">Sitzung beenden</button></form>
                            @else
                                <a href="{{ route('login') }}">Anmelden</a>
                            @endauth
                        </nav>
                    </aside>
                @endif
            </div>

            <footer class="wiki-container border-t py-5 text-sm text-slate-500">{{ $wikiTheme->wiki_title }}</footer>
        </div>
    </body>
</html>
