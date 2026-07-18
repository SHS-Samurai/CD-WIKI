@props(['name', 'show' => false, 'maxWidth' => '2xl'])

@php
$maxWidth = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'][$maxWidth];
@endphp

<dialog id="modal-{{ $name }}" @if($show) data-initial-open @endif class="w-full {{ $maxWidth }} rounded-lg bg-white p-0 shadow-xl backdrop:bg-slate-900/70">
    {{ $slot }}
</dialog>
