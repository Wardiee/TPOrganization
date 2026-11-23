@props([
    'status',
    'size' => 'md',
])

@php
    /** @var \App\Enums\MemberStatus $status */
    $tones = [
        'active' => 'bg-active-bg text-active-fg',
        'floating' => 'bg-floating-bg text-floating-fg',
        'inactive' => 'bg-inactive-bg text-inactive-fg',
    ];
    $sizes = [
        'sm' => 'px-[9px] py-1 text-[11px]',
        'md' => 'px-2.5 py-1 text-xs',
    ];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full font-semibold whitespace-nowrap', $tones[$status->value], $sizes[$size]]) }}>{{ $status->label() }}</span>
