@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$alignmentClasses = $align === 'left' ? 'start-0' : 'end-0';
$width = $width === '48' ? 'w-48' : $width;
@endphp

<details class="relative">
    <summary class="cursor-pointer list-none">{{ $trigger }}</summary>
    <div class="absolute z-50 mt-2 {{ $width }} {{ $alignmentClasses }} rounded-md shadow-lg">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">{{ $content }}</div>
    </div>
</details>
