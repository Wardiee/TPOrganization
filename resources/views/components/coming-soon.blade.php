@props([
    'phase' => null,
])

{{-- Temporary body for screens that are not built yet. --}}
<x-card {{ $attributes->class(['flex flex-col items-start gap-1 p-5 lg:p-6']) }}>
    <p class="font-semibold">{{ $phase ? "Coming in Phase {$phase}" : 'Coming soon' }}</p>
    <p class="text-sm text-muted">{{ $slot }}</p>
</x-card>
