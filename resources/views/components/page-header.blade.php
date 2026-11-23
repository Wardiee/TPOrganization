@props([
    'title',
    'subtitle' => null,
    'backToMore' => false,
])

{{-- Page title row. Phones: screens under More get a "‹ More" back link. --}}
<header {{ $attributes->class(['flex flex-col gap-2.5 lg:gap-0', 'pt-2' => $backToMore, 'pt-4' => ! $backToMore, 'lg:pt-0']) }}>
    @if ($backToMore)
        <a href="{{ route('more') }}" wire:navigate class="-ml-2 inline-flex min-h-11 items-center gap-1 self-start rounded-lg px-2 text-[15px] font-semibold text-blue hover:text-navy focus-visible:outline-2 focus-visible:outline-blue lg:hidden">
            <x-icon name="chevron-left" :size="22" :stroke-width="2.2" />
            More
        </a>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 lg:gap-4">
        <div class="flex flex-col gap-0.5 lg:gap-1.5">
            <h1 @class([
                'font-display font-extrabold tracking-[-0.01em] lg:text-[32px]',
                'text-[26px]' => $backToMore,
                'text-[28px]' => ! $backToMore,
            ])>{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-[13px] text-muted lg:text-sm">{{ $subtitle }}</p>
            @endif
        </div>

        @if ($slot->isNotEmpty())
            <div class="flex flex-wrap gap-2.5">{{ $slot }}</div>
        @endif
    </div>
</header>
