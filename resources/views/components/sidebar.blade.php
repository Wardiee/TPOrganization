@use('App\Support\Navigation')

{{-- Desktop navigation (lg and up). Design: docs/design/web/Main.dc.html --}}
<nav aria-label="Main" {{ $attributes->class(['tp-navy flex-col gap-7 bg-navy px-4 py-6 text-navy-text']) }}>
    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-[10px] px-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
        <img src="/images/logo.png" alt="Tropang Potchi Brotherhood logo" class="size-13">
        <span class="flex flex-col gap-0.5">
            <span class="font-display text-base font-extrabold tracking-[0.03em] whitespace-nowrap text-white">TROPANG POTCHI</span>
            <span class="text-xs text-navy-muted">Management System</span>
        </span>
    </a>

    <ul class="flex flex-col gap-1">
        @foreach (Navigation::sidebar() as $item)
            @php($current = Navigation::isActive($item))
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    @if ($current) aria-current="page" @endif
                    @class([
                        'flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow',
                        'tp-glow bg-yellow font-semibold text-ink' => $current,
                        'font-medium text-navy-text hover:bg-navy-2 hover:text-white' => ! $current,
                    ])
                >
                    <x-icon :name="$item['icon']" />
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    @php($onSettings = request()->routeIs('profile.edit', 'security.edit'))
    <div class="mt-auto flex flex-col gap-1">
        <a
            href="{{ route('profile.edit') }}"
            wire:navigate
            @if ($onSettings) aria-current="page" @endif
            @class([
                'flex items-center gap-3 rounded-xl bg-navy-2 p-3 hover:bg-[#2F3A8A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow',
                'ring-2 ring-yellow' => $onSettings,
            ])
        >
            <x-avatar-initials :initials="auth()->user()->initials()" />
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</span>
                <span class="truncate text-xs text-navy-muted">{{ config('admin.title') }}</span>
                <span class="sr-only">Account settings</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-[10px] px-3 text-sm font-medium text-navy-text hover:bg-navy-2 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow" data-test="logout-button">
                <x-icon name="sign-out" />
                Sign out
            </button>
        </form>
    </div>
</nav>
