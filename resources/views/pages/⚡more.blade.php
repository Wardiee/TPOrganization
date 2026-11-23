<?php

use App\Enums\MemberStatus;
use App\Models\Position;
use App\Models\Task;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('More')] class extends Component
{
    /**
     * Links under "Organization", with live badges: seats needing someone, and open tasks.
     * (The file count joins when Documents is built in Phase 9.)
     *
     * @return list<array{label: string, detail: string, route: string, icon: string, tile: string, badge: string|null, badgeTone: string}>
     */
    public function organizationLinks(): array
    {
        $vacant = $this->vacantSeats();
        $openTasks = Task::query()->whereNull('completed_at')->count();

        return [
            ['label' => 'Org structure', 'detail' => 'TROP · TROPS positions and history', 'route' => 'org-structure', 'icon' => 'org', 'tile' => 'bg-[#DDF1F2] text-[#0A5F63]', 'badge' => $vacant > 0 ? "{$vacant} vacant" : null, 'badgeTone' => 'bg-alert-bg text-alert-fg'],
            ['label' => 'Tasks', 'detail' => 'Reminders and follow-ups', 'route' => 'tasks', 'icon' => 'tasks', 'tile' => 'bg-info-bg text-info-fg', 'badge' => $openTasks > 0 ? "{$openTasks} open" : null, 'badgeTone' => 'bg-navy text-white'],
            ['label' => 'Documents & photos', 'detail' => 'Files and photos in folders', 'route' => 'documents', 'icon' => 'documents', 'tile' => 'bg-[#FFF1B8] text-[#4A3600]', 'badge' => null, 'badgeTone' => ''],
        ];
    }

    /**
     * TROP/TROPS seats that are empty or held by an inactive member.
     */
    private function vacantSeats(): int
    {
        return Position::query()
            ->whereNotNull('branch')
            ->where(fn ($query) => $query
                ->whereDoesntHave('assignments', fn ($assignment) => $assignment->whereNull('ended_on'))
                ->orWhereHas('assignments', fn ($assignment) => $assignment->whereNull('ended_on')->whereRelation('member', 'status', MemberStatus::Inactive)))
            ->count();
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
                    @if ($link['badge'])
                        <span class="shrink-0 rounded-full px-2 py-[3px] text-[11px] font-bold {{ $link['badgeTone'] }}">{{ $link['badge'] }}</span>
                    @else
                        <x-icon name="chevron-right" class="text-[#8A8D99]" />
                    @endif
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
