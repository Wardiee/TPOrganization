<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('More')] class extends Component
{
    /**
     * Links under "Organization". Badges with live counts (vacant seats, open tasks,
     * file count) are added once those screens exist (Phases 6, 8 and 9).
     *
     * @return list<array{label: string, detail: string, route: string, icon: string, tile: string}>
     */
    public function organizationLinks(): array
    {
        return [
            ['label' => 'Org structure', 'detail' => 'TROP · TROPS positions and history', 'route' => 'org-structure', 'icon' => 'org', 'tile' => 'bg-[#DDF1F2] text-[#0A5F63]'],
            ['label' => 'Tasks', 'detail' => 'Reminders and follow-ups', 'route' => 'tasks', 'icon' => 'tasks', 'tile' => 'bg-info-bg text-info-fg'],
            ['label' => 'Documents & photos', 'detail' => 'Files and photos in folders', 'route' => 'documents', 'icon' => 'documents', 'tile' => 'bg-[#FFF1B8] text-[#4A3600]'],
        ];
    }

    /**
     * App features that are not available yet; shown greyed out with a "Soon" label.
     *
     * @return list<array{label: string, detail: string}>
     */
    public function upcomingFeatures(): array
    {
        return [
            ['label' => 'Birthday reminders', 'detail' => 'Notify me 1 day before'],
            ['label' => 'Work offline', 'detail' => 'Take attendance without signal, sync later'],
            ['label' => 'Import from Excel', 'detail' => 'Update records from a spreadsheet'],
        ];
    }
};
?>

{{-- Phone-only menu. Design: docs/design/mobile/More.dc.html --}}
<div class="contents">
    <x-page-header title="More" />

    <section aria-label="Your account" class="flex items-center gap-3.5 rounded-[18px] bg-navy p-4 text-white">
        <x-avatar-initials :initials="auth()->user()->initials()" size="lg" />
        <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
            <span class="truncate text-base font-bold">{{ auth()->user()->name }}</span>
            <span class="truncate text-[13px] text-navy-muted">{{ config('admin.title') }} · Admin</span>
        </span>
        <img src="/images/logo.png" alt="" class="size-11">
    </section>

    <section aria-labelledby="more-org-heading" class="flex flex-col gap-2">
        <h2 id="more-org-heading" class="px-1 text-xs font-bold tracking-[0.08em] text-muted uppercase">Organization</h2>
        <x-card class="divide-y divide-line-soft overflow-hidden">
            @foreach ($this->organizationLinks() as $link)
                <a href="{{ route($link['route']) }}" wire:navigate class="flex min-h-16 items-center gap-3.5 px-4 hover:bg-bg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $link['tile'] }}">
                        <x-icon :name="$link['icon']" :size="20" />
                    </span>
                    <span class="flex flex-1 flex-col gap-0.5">
                        <span class="text-[15px] font-semibold">{{ $link['label'] }}</span>
                        <span class="text-xs text-muted">{{ $link['detail'] }}</span>
                    </span>
                    <x-icon name="chevron-right" class="text-[#8A8D99]" />
                </a>
            @endforeach
            <a href="{{ route('reports') }}" wire:navigate class="flex min-h-16 items-center gap-3.5 px-4 hover:bg-bg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#EAE3F7] text-[#4B2E83]">
                    <x-icon name="reports" :size="20" />
                </span>
                <span class="flex flex-1 flex-col gap-0.5">
                    <span class="text-[15px] font-semibold">Reports</span>
                    <span class="text-xs text-muted">Attendance and membership trends</span>
                </span>
                <span class="text-[11px] font-semibold text-muted">Soon</span>
            </a>
        </x-card>
    </section>

    <section aria-labelledby="more-app-heading" class="flex flex-col gap-2">
        <h2 id="more-app-heading" class="px-1 text-xs font-bold tracking-[0.08em] text-muted uppercase">App</h2>
        <x-card class="divide-y divide-line-soft overflow-hidden">
            @foreach ($this->upcomingFeatures() as $feature)
                <div class="flex min-h-15 items-center gap-3.5 px-4" aria-disabled="true">
                    <span class="flex flex-1 flex-col gap-0.5">
                        <span class="text-[15px] font-semibold text-muted">{{ $feature['label'] }}</span>
                        <span class="text-xs text-muted">{{ $feature['detail'] }}</span>
                    </span>
                    <span class="rounded-full bg-inactive-bg px-2 py-0.5 text-[11px] font-semibold text-inactive-fg">Soon</span>
                </div>
            @endforeach
            <a href="{{ route('profile.edit') }}" wire:navigate class="flex min-h-15 items-center gap-3.5 px-4 hover:bg-bg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                <span class="flex flex-1 flex-col gap-0.5">
                    <span class="text-[15px] font-semibold">Account settings</span>
                    <span class="text-xs text-muted">Name, email, password and two-factor login</span>
                </span>
                <x-icon name="chevron-right" class="text-[#8A8D99]" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex min-h-14 w-full items-center px-4 text-left text-[15px] font-semibold text-absent hover:bg-bg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">Sign out</button>
            </form>
        </x-card>
    </section>
</div>
