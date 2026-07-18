<x-wiki-layout title="Web anlegen">
    <h1 class="mb-8 text-3xl font-bold">Web anlegen</h1>
    @include('admin.webs._form', ['action' => route('admin.webs.store'), 'method' => 'POST'])
</x-wiki-layout>
