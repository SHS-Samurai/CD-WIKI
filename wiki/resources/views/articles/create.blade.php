<x-wiki-layout :title="'Neuer Artikel – '.$web->title">
    <p class="text-sm text-slate-500"><a class="hover:text-sky-700" href="{{ route('webs.show', $web) }}">{{ $web->title }}</a></p>
    <h1 class="mb-8 mt-1 text-3xl font-bold">Neuer Artikel</h1>
    @include('articles._form', ['action' => route('articles.store', $web), 'method' => 'POST', 'cancelUrl' => route('webs.show', $web)])
</x-wiki-layout>
