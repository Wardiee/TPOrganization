@props([
    'initials',
    'size' => 'md',
])

@php
    $sizes = [
        'md' => 'size-10 text-sm',
        'touch' => 'size-11 text-sm',
        'lg' => 'size-14 font-display text-xl font-extrabold',
    ];
@endphp

<span {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full bg-yellow font-bold text-ink', $sizes[$size]]) }}>{{ $initials }}</span>
