<x-wiki-layout title="Startseite">
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-gradient-to-r from-sky-800 to-cyan-700 px-6 py-10 text-white sm:px-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-sky-100">Willkommen</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ config('app.name') }}</h1>
            <p class="mt-4 max-w-2xl text-sky-50">Wissen gemeinsam erfassen, sicher verwalten und schnell wiederfinden.</p>
        </div>

        <div class="p-6 sm:p-10">
            <div class="mb-6 flex items-center justify-between gap-4">
                <h2 class="text-2xl font-bold">Webs</h2>
                @auth
                    @if (auth()->user()->is_admin)
                        <a class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800" href="{{ route('admin.webs.create') }}">Web anlegen</a>
                    @endif
                @endauth
            </div>

            @if ($webs->isEmpty())
                <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Für Sie sind noch keine Webs sichtbar.</p>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($webs as $web)
                        <a class="rounded-lg border border-slate-200 p-5 transition hover:border-sky-400 hover:bg-sky-50" href="{{ route('webs.show', $web) }}">
                            <h3 class="font-semibold text-slate-950">{{ $web->title }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ $web->description ?: 'Wiki-Bereich öffnen' }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-wiki-layout>
