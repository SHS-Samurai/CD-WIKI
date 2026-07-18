<x-wiki-layout :title="'Web bearbeiten – '.$web->title">
    <div class="mb-8 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">Web bearbeiten</h1>
        <a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('admin.webs.permissions.index', $web) }}">Web-Rechte verwalten</a>
    </div>
    @include('admin.webs._form', ['action' => route('admin.webs.update', $web), 'method' => 'PATCH'])
</x-wiki-layout>
