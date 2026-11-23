@props([
    'date',
    'size' => 'md',
])

{{-- Month + day tile, e.g. "DEC / 14". --}}
<span {{ $attributes->class([
    'flex shrink-0 flex-col items-center justify-center rounded-xl bg-[#FFF6D1]',
    'size-12' => $size === 'md',
    'size-11' => $size === 'sm',
]) }}>
    <span @class(['font-bold tracking-[0.06em] text-floating-fg', 'text-[11px]' => $size === 'md', 'text-[10px]' => $size === 'sm'])>{{ Str::upper($date->format('M')) }}</span>
    <span @class(['font-display leading-none font-extrabold', 'text-lg' => $size === 'md', 'text-[17px]' => $size === 'sm'])>{{ $date->format('j') }}</span>
</span>
