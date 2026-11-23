@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1">
    <h1 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-ink">{{ $title }}</h1>
    <p class="text-sm text-muted">{{ $description }}</p>
</div>
