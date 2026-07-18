<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Wiki installieren</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-12">
            <section class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="bg-gradient-to-r from-sky-800 to-cyan-700 px-6 py-8 text-white sm:px-10">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sky-100">Ersteinrichtung</p>
                    <h1 class="mt-2 text-3xl font-bold">Datenbank verbinden</h1>
                    <p class="mt-3 text-sky-50">Die Installationsroutine legt die Wiki-Datenbank auf dem lokalen MySQL-Server selbst an.</p>
                </header>

                <form class="space-y-6 p-6 sm:p-10" method="post" action="{{ route('installation.store') }}" autocomplete="off">
                    @csrf

                    @if ($requiresSetupToken)
                        <label class="block">
                            <span class="text-sm font-medium">Installationstoken</span>
                            <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="setup_token" type="password" required autocomplete="one-time-code">
                            <span class="mt-1 block text-xs text-slate-500">Das Token wurde mit <code>php artisan wiki:installation-token</code> erzeugt.</span>
                            @error('setup_token')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                        </label>
                    @endif

                    @if ($errors->has('database'))
                        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            {{ $errors->first('database') }}
                        </div>
                    @endif

                    <div class="grid gap-6 sm:grid-cols-[1fr_9rem]">
                        <label class="block">
                            <span class="text-sm font-medium">Datenbankserver</span>
                            <input class="mt-2 block w-full rounded-md border-slate-300 bg-slate-100" name="host" type="text" value="127.0.0.1" readonly required>
                            @error('host')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">Port</span>
                            <input class="mt-2 block w-full rounded-md border-slate-300 bg-slate-100" name="port" type="number" value="3306" readonly required>
                            @error('port')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-medium">Datenbankname</span>
                        <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="database" type="text" value="{{ old('database') }}" required>
                        <span class="mt-1 block text-xs text-slate-500">Wird automatisch erstellt, falls sie noch nicht existiert.</span>
                        @error('database')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>

                    <fieldset class="space-y-6 border-t border-slate-200 pt-6">
                        <legend class="text-lg font-semibold">Erstes Administratorkonto</legend>
                        <label class="block">
                            <span class="text-sm font-medium">Name</span>
                            <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="admin_name" type="text" value="{{ old('admin_name') }}" required autocomplete="name">
                            @error('admin_name')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">E-Mail-Adresse</span>
                            <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="admin_email" type="email" value="{{ old('admin_email') }}" required autocomplete="email">
                            @error('admin_email')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium">Administrator-Passwort</span>
                                <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="admin_password" type="password" required autocomplete="new-password">
                                @error('admin_password')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium">Passwort bestätigen</span>
                                <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="admin_password_confirmation" type="password" required autocomplete="new-password">
                            </label>
                        </div>
                        <p class="text-sm text-slate-500">Mindestens 12 Zeichen sowie Groß- und Kleinbuchstaben und Zahlen.</p>
                    </fieldset>

                    <label class="block">
                        <span class="text-sm font-medium">MySQL-Benutzer</span>
                        <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="username" type="text" value="{{ old('username') }}" required autocomplete="off">
                        @error('username')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium">MySQL-Passwort</span>
                        <input class="mt-2 block w-full rounded-md border-slate-300 focus:border-sky-600 focus:ring-sky-600" name="password" type="password" autocomplete="new-password">
                        @error('password')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <p class="text-sm text-slate-500">Der MySQL-Benutzer benötigt auf dem lokalen Server das Recht, die angegebene Datenbank anzulegen und darin Tabellen zu verwalten.</p>

                    <button class="w-full rounded-md bg-sky-700 px-5 py-3 font-semibold text-white hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2" type="submit">
                        Verbindung prüfen und Wiki installieren
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
