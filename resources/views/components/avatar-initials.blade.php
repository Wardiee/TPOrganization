@props([
    'initials',
    'size' => 'md',
    'tone' => 'yellow',
])

@php
    $sizes = [
        'md' => 'size-10 text-[13px] font-bold',
        'touch' => 'size-11 text-sm font-bold',
        'lg' => 'size-14 font-display text-xl font-extrabold',
        'xl' => 'size-16 border-3 border-white font-display text-[22px] font-extrabold',
        'profile' => 'size-21 font-display text-[30px] font-extrabold lg:size-28 lg:border-4 lg:border-white lg:text-[38px]',
    ];

    // Soft tones for light rows (web), solid tones for phone rows. Solid floating/inactive are
    // darker than the design's samples so white initials pass 4.5:1 contrast.
    $tones = [
        'yellow' => 'bg-yellow text-ink',
        'active-soft' => 'bg-info-bg text-info-fg',
        'floating-soft' => 'bg-floating-bg text-floating-fg',
        'inactive-soft' => 'bg-inactive-bg text-inactive-fg',
        'active-solid' => 'bg-blue text-white',
        'floating-solid' => 'bg-[#8A5A00] text-white',
        'inactive-solid' => 'bg-[#6B6E7A] text-white',
    ];
@endphp

<span {{ $attributes->class(['box-border flex shrink-0 items-center justify-center rounded-full', $sizes[$size], $tones[$tone]]) }}>{{ $initials }}</span>
