<?php

use App\Enums\TaskCategory;
use App\Models\Task;
use App\Services\TaskSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Tasks')] class extends Component
{
    #[Url(except: 'open')]
    public string $filter = 'open';

    public string $newTitle = '';

    public string $newCategory = 'Members';

    public string $newDue = '';

    /**
     * Bring the automatic tasks up to date with the records before showing them.
     */
    public function mount(TaskSync $taskSync): void
    {
        $this->authorize('viewAny', Task::class);

        $taskSync->sync();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return Task::query()->orderBy('id')->get();
    }

    /**
     * Groups to show for the current filter: Coming up (dated, soonest first), No due date, Completed.
     *
     * @return list<array{key: string, title: string, phoneTitle: string, items: Collection<int, Task>}>
     */
    #[Computed]
    public function groups(): array
    {
        $open = $this->tasks->reject(fn (Task $task) => $task->isDone());
        $done = $this->tasks->filter(fn (Task $task) => $task->isDone())->sortByDesc('completed_at')->values();
        $groups = [];

        if ($this->filter !== 'done') {
            $dated = $open->whereNotNull('due_on')->sortBy(fn (Task $task) => $task->due_on->timestamp)->values();
            $undated = $open->whereNull('due_on')->values();

            if ($dated->isNotEmpty()) {
                $groups[] = ['key' => 'dated', 'title' => 'Coming up', 'phoneTitle' => 'Coming up', 'items' => $dated];
            }

            if ($undated->isNotEmpty()) {
                $groups[] = ['key' => 'undated', 'title' => 'No due date', 'phoneTitle' => 'Anytime', 'items' => $undated];
            }
        }

        if ($this->filter !== 'open' && $done->isNotEmpty()) {
            $groups[] = ['key' => 'done', 'title' => 'Completed', 'phoneTitle' => 'Completed', 'items' => $done];
        }

        return $groups;
    }

    #[Computed]
    public function openCount(): int
    {
        return $this->tasks->reject(fn (Task $task) => $task->isDone())->count();
    }

    public function toggle(int $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);
        $this->authorize('update', $task);

        $task->forceFill([
            'completed_at' => $task->isDone() ? null : CarbonImmutable::now(),
            'auto_closed' => false,
        ])->save();

        unset($this->tasks, $this->groups, $this->openCount);
    }

    public function addTask(): void
    {
        $this->authorize('create', Task::class);

        $validated = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newCategory' => ['required', Rule::enum(TaskCategory::class)],
            'newDue' => ['nullable', 'date'],
        ], [], ['newTitle' => 'task', 'newCategory' => 'category', 'newDue' => 'due date']);

        Task::create([
            'title' => trim($validated['newTitle']),
            'detail' => 'Added by you',
            'category' => $validated['newCategory'],
            'due_on' => $validated['newDue'] ?: null,
            'is_auto' => false,
        ]);

        $this->reset('newTitle', 'newDue');
        $this->filter = 'open';
        unset($this->tasks, $this->groups, $this->openCount);
    }

    /**
     * Only tasks you added can be deleted; automatic ones close themselves when the problem is fixed.
     */
    public function deleteTask(int $taskId): void
    {
        $task = Task::query()->where('is_auto', false)->findOrFail($taskId);
        $this->authorize('delete', $task);

        $task->delete();

        unset($this->tasks, $this->groups, $this->openCount);
    }
};
?>

@php
    $categoryTones = [
        'Members' => 'bg-info-bg text-info-fg',
        'Structure' => 'bg-[#DDF1F2] text-[#0A5F63]',
        'Activities' => 'bg-[#EAE3F7] text-[#4B2E83]',
        'Birthdays' => 'bg-[#FFF1B8] text-[#4A3600]',
        'Records' => 'bg-inactive-bg text-inactive-fg',
    ];
    $priorityTones = [
        'High' => 'bg-alert-bg text-alert-fg',
        'Medium' => 'bg-floating-bg text-floating-fg',
        'Low' => 'bg-[#F0EFE8] text-inactive-fg',
    ];
    $doneCount = $this->tasks->count() - $this->openCount;
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-sm text-ink focus:outline-2 focus:outline-blue aria-invalid:border-absent';
@endphp

{{-- Designs: docs/design/web/Tasks.dc.html (lg+), docs/design/mobile/Tasks.dc.html (phones) --}}
<div class="contents">
    <x-page-header title="Tasks" :subtitle="$this->openCount.' open · '.$doneCount.' done'" back-to-more>
        <div role="group" aria-label="Filter tasks" class="hidden gap-1 rounded-xl bg-[#ECEAE0] p-1 lg:flex">
            @foreach (['open' => 'Open', 'all' => 'All', 'done' => 'Completed'] as $value => $label)
                <button type="button" wire:click="$set('filter', '{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}" @class([
                    'min-h-11 rounded-[9px] px-3.5 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                    'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $filter === $value,
                ])>{{ $label }}</button>
            @endforeach
        </div>
    </x-page-header>

    <div role="group" aria-label="Filter tasks" class="grid grid-cols-3 gap-1 rounded-xl bg-[#ECEBE4] p-1 lg:hidden">
        @foreach (['open' => 'Open', 'all' => 'All', 'done' => 'Completed'] as $value => $label)
            <button type="button" wire:click="$set('filter', '{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}" @class([
                'min-h-11 rounded-[9px] text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $filter === $value,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        {{-- ───────────── Task groups ───────────── --}}
        <div class="flex min-w-0 flex-col gap-[18px] lg:flex-[999_1_600px] lg:gap-5">
            @forelse ($this->groups as $group)
                <section aria-labelledby="group-{{ $group['key'] }}" wire:key="group-{{ $group['key'] }}" class="flex flex-col gap-2 lg:gap-0 lg:overflow-hidden lg:rounded-2xl lg:border lg:border-line lg:bg-white">
                    <h2 id="group-{{ $group['key'] }}" class="flex justify-between text-[13px] font-bold tracking-[0.06em] text-muted uppercase lg:items-baseline lg:border-b lg:border-line lg:bg-[#FAF9F4] lg:px-5 lg:py-4 lg:text-base lg:tracking-normal lg:text-ink lg:normal-case">
                        <span><span class="lg:hidden">{{ $group['phoneTitle'] }}</span><span class="hidden lg:inline">{{ $group['title'] }}</span></span>
                        <span class="lg:text-[13px] lg:font-normal lg:text-muted">{{ $group['items']->count() }}<span class="hidden lg:inline"> {{ Str::plural('task', $group['items']->count()) }}</span></span>
                    </h2>
                    <ul class="overflow-hidden rounded-2xl border border-line bg-white lg:rounded-none lg:border-0">
                        @foreach ($group['items'] as $task)
                            @php($done = $task->isDone())
                            @php($link = $task->linkUrl())
                            <li wire:key="task-{{ $task->id }}" class="flex items-start gap-3 border-b border-line-soft px-3.5 py-3 last:border-b-0 lg:gap-3.5 lg:px-5 lg:py-3.5">
                                <label class="-my-2.5 -mr-2 -ml-2.5 flex size-11 shrink-0 cursor-pointer items-center justify-center lg:m-0 lg:size-auto lg:pt-px">
                                    <input type="checkbox" wire:click="toggle({{ $task->id }})" @checked($done) aria-label="{{ $done ? 'Mark not done' : 'Mark done' }}: {{ $task->title }}" class="size-[22px] cursor-pointer accent-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                                </label>
                                <div class="flex min-w-0 flex-1 flex-col gap-[5px]">
                                    @if ($link)
                                        <a href="{{ $link }}" wire:navigate @class(['-my-3 block py-3 text-[15px] font-semibold hover:text-blue lg:hidden', 'text-muted line-through' => $done])>{{ $task->title }}</a>
                                    @endif
                                    <span @class(['text-[15px] font-semibold', 'hidden lg:inline' => (bool) $link, 'text-muted line-through' => $done])>{{ $task->title }}</span>
                                    @if ($task->detail)
                                        <span class="text-xs text-muted lg:text-[13px]">{{ $task->detail }}</span>
                                    @endif
                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-md px-2 py-[3px] text-xs font-semibold {{ $categoryTones[$task->category->value] }}">{{ $task->category->value }}</span>
                                        @if ($task->priority && ! $done)
                                            <span class="rounded-md px-2 py-[3px] text-xs font-semibold {{ $priorityTones[$task->priority->value] }}">{{ $task->priority->value }}<span class="hidden lg:inline"> priority</span></span>
                                        @endif
                                        <span @class(['text-xs font-semibold lg:hidden', 'text-active-fg' => $done, 'text-ink' => ! $done && $task->due_on, 'text-muted' => ! $done && ! $task->due_on])>
                                            {{ $done ? 'Done' : ($task->due_on?->format('M j, Y') ?? 'No due date') }}
                                        </span>
                                        @if ($task->is_auto)
                                            <span class="hidden text-xs text-muted lg:inline">· {{ $task->auto_closed ? 'Closed automatically' : 'Created automatically' }}</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="hidden shrink-0 flex-col items-end gap-1.5 lg:flex">
                                    <span @class(['text-[13px] font-semibold whitespace-nowrap', 'text-active-fg' => $done, 'text-ink' => ! $done && $task->due_on, 'text-muted' => ! $done && ! $task->due_on])>
                                        {{ $done ? 'Done' : ($task->due_on?->format('M j, Y') ?? 'No due date') }}
                                    </span>
                                    @if ($link)
                                        <a href="{{ $link }}" wire:navigate class="inline-flex min-h-11 items-center text-[13px] font-semibold text-blue hover:text-navy">Open<span class="sr-only">: {{ $task->title }}</span></a>
                                    @endif
                                    @if (! $task->is_auto)
                                        <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete the task “{{ $task->title }}”?" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-absent hover:underline">Delete<span class="sr-only">: {{ $task->title }}</span></button>
                                    @endif
                                </div>
                                @if (! $task->is_auto)
                                    <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete the task “{{ $task->title }}”?" class="-my-2.5 -mr-2 flex size-11 shrink-0 items-center justify-center text-muted lg:hidden" aria-label="Delete: {{ $task->title }}">
                                        <x-icon name="close" :size="16" />
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <div class="flex flex-col items-center gap-1.5 rounded-2xl border border-line bg-white px-5 py-12 text-center">
                    <p class="text-base font-semibold">Nothing here</p>
                    <p class="text-sm text-muted">No tasks match this filter.</p>
                </div>
            @endforelse
        </div>

        {{-- ───────────── Desktop side: add a task, what the system adds ───────────── --}}
        <div class="hidden min-w-0 flex-col gap-5 lg:flex lg:flex-[1_1_320px]">
            @can('create', App\Models\Task::class)
                <x-card class="flex flex-col gap-3 p-[22px]">
                    <h2 class="text-lg font-bold">Add a task</h2>
                    <form wire:submit="addTask" class="flex flex-col gap-3" novalidate>
                        <label class="flex flex-col gap-1.5 text-[13px] font-semibold">
                            What needs to be done?
                            <input type="text" wire:model="newTitle" placeholder="e.g. Book venue for next outing" @error('newTitle') aria-invalid="true" @enderror class="{{ $input }} font-normal">
                        </label>
                        @error('newTitle') <p class="-mt-1.5 text-sm text-absent">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-2.5">
                            <label class="flex flex-[1_1_120px] flex-col gap-1.5 text-[13px] font-semibold">
                                Category
                                <select wire:model="newCategory" class="{{ $input }} font-normal">
                                    @foreach (App\Enums\TaskCategory::cases() as $category)
                                        <option value="{{ $category->value }}">{{ $category->value }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex flex-[1_1_120px] flex-col gap-1.5 text-[13px] font-semibold">
                                Due date
                                <input type="date" wire:model="newDue" class="{{ $input }} font-normal">
                            </label>
                        </div>
                        <button type="submit" class="min-h-11 rounded-[10px] bg-blue text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Add task</button>
                    </form>
                </x-card>
            @endcan

            <section aria-labelledby="auto-heading" class="tp-navy flex flex-col gap-2.5 rounded-2xl bg-navy p-[22px] text-white">
                <h2 id="auto-heading" class="text-lg font-bold">Tasks the system adds for you</h2>
                <p class="text-sm text-navy-text">A birthday greeting task {{ App\Services\TaskSync::BIRTHDAY_DAYS_AHEAD }} days before each active member’s birthday.</p>
                <p class="text-sm text-navy-text">A task when a seat is held by an inactive member, or members have no position.</p>
                <p class="text-sm text-navy-text">A task when member records conflict or are missing info.</p>
                <p class="text-[13px] font-semibold text-yellow">They close by themselves once the records are fixed.</p>
            </section>
        </div>
    </div>

    {{-- ───────────── Phone: quick add bar above the tab bar ───────────── --}}
    @can('create', App\Models\Task::class)
        <form wire:submit="addTask" class="sticky bottom-[calc(59px+max(8px,env(safe-area-inset-bottom)))] z-20 -mx-4 flex flex-col gap-1 border-t border-line bg-white px-4 py-2.5 lg:hidden" novalidate>
            <div class="flex gap-2">
                <label for="new-task" class="sr-only">New task</label>
                <input id="new-task" type="text" wire:model="newTitle" placeholder="Add a task" @error('newTitle') aria-invalid="true" @enderror class="min-h-12 min-w-0 flex-1 rounded-xl border border-line-input bg-[#FAF9F4] px-3.5 text-[15px] text-ink focus-visible:outline-2 focus-visible:outline-blue aria-invalid:border-absent">
                <button type="submit" aria-label="Add task" class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                    <x-icon name="plus" :size="20" :stroke-width="2.4" />
                </button>
            </div>
            @error('newTitle') <p class="text-sm text-absent">{{ $message }}</p> @enderror
        </form>
    @endcan
</div>
