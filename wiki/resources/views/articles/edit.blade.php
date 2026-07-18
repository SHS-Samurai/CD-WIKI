<x-wiki-layout :title="'Bearbeiten – '.$article->title">
    <p class="text-sm text-slate-500"><a class="hover:text-sky-700" href="{{ route('articles.show', [$web, $article]) }}">{{ $article->title }}</a></p>
    <h1 class="mb-8 mt-1 text-3xl font-bold">Artikel bearbeiten</h1>
    @include('articles._form', ['action' => route('articles.update', [$web, $article]), 'method' => 'PATCH', 'cancelUrl' => route('articles.show', [$web, $article])])
</x-wiki-layout>
