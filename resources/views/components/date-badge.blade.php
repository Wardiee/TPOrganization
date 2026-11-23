@props([
    'date',
    'size' => 'md',
    'tone' => 'light',
])

{{-- Month + day tile, e.g. "DEC / 14". "yellow" is for use on navy cards. --}}
<span {{ $attributes->class([
    'flex shrink-0 flex-col items-center justify-center rounded-xl',
    'size-12' => $size === 'md',
    'size-11' => $size === 'sm',
    'bg-[#FFF6D1]' => $tone === 'light',
    'bg-yellow text-ink' => $tone === 'yellow',
]) }}>
    <span @class([
        'tracking-[0.06em]',
        'font-bold text-floating-fg' => $tone === 'light',
        'font-extrabold' => $tone === 'yellow',
        'text-[11px]' => $size === 'md',
        'text-[10px]' => $size === 'sm',
    ])>{{ Str::upper($date->format('M')) }}</span>
    <span @class(['font-display leading-none font-extrabold', 'text-lg' => $size === 'md', 'text-[17px]' => $size === 'sm'])>{{ $date->format('j') }}</span>
</span>
