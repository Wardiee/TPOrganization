{{-- Branded phone top bar (Home screen only, below lg). Design: docs/design/mobile/Home.dc.html --}}
<div data-static {{ $attributes->class(['tp-navy-dots -mx-4 flex items-center gap-2.5 bg-navy px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-3 lg:hidden']) }}>
    <img src="/images/logo.png" alt="Tropang Potchi Brotherhood logo" class="size-10">
    <span class="flex flex-1 flex-col gap-px">
        <span class="font-display text-[15px] font-extrabold tracking-[0.03em] text-white">TROPANG POTCHI</span>
        <span class="text-xs text-navy-muted">Management System</span>
    </span>
    <a href="{{ route('more') }}" wire:navigate aria-label="Your account" class="rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
        <x-avatar-initials :initials="auth()->user()->initials()" size="touch" />
    </a>
</div>
