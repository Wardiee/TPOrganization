@use('App\Support\Navigation')

{{-- Phone navigation (below lg). Design: docs/design/mobile/Home.dc.html --}}
<nav aria-label="Main" {{ $attributes->class(['fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white px-1 pt-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))]']) }}>
    <ul class="grid grid-cols-5">
        @foreach (Navigation::tabs() as $item)
            @php($current = Navigation::isActive($item))
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    @if ($current) aria-current="page" @endif
                    @class([
                        'flex min-h-13 flex-col items-center justify-center gap-[3px] rounded-lg text-[11px] focus-visible:outline-2 focus-visible:outline-blue',
                        'font-bold text-navy' => $current,
                        'font-medium text-muted' => ! $current,
                    ])
                >
                    <span @class(['flex h-7.5 w-14 items-center justify-center rounded-full', 'bg-yellow' => $current])>
                        <x-icon :name="$item['icon']" :size="20" />
                    </span>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
