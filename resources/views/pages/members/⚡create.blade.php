<?php

use App\Livewire\Forms\MemberForm;
use App\Models\Chapter;
use App\Models\Member;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add member')] class extends Component
{
    public MemberForm $form;

    public function mount(): void
    {
        $this->authorize('create', Member::class);
    }

    public function save(): void
    {
        $this->authorize('create', Member::class);

        $member = $this->form->store();

        $this->redirectRoute('members.show', $member, navigate: true);
    }

    /**
     * @return Collection<int, Chapter>
     */
    #[Computed]
    public function chapters(): Collection
    {
        return Chapter::query()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function gradeSuggestions(): Collection
    {
        return Member::query()->whereNotNull('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level');
    }
};
?>

<div class="contents">
    <div class="flex flex-col gap-2.5 pt-2 lg:gap-1.5 lg:pt-0">
        <a href="{{ route('members.index') }}" wire:navigate class="-ml-2 inline-flex min-h-11 items-center gap-1 self-start rounded-lg px-2 text-[15px] font-semibold text-blue hover:text-navy focus-visible:outline-2 focus-visible:outline-blue lg:text-sm">
            <x-icon name="chevron-left" :size="20" :stroke-width="2.2" />
            Members
        </a>
        <h1 class="font-display text-[26px] font-extrabold tracking-[-0.01em] lg:text-[32px]">Add member</h1>
        <p class="text-[13px] text-muted lg:text-sm">Only the full name is required. You can fill in the rest later.</p>
    </div>

    <x-member-form
        :form="$form"
        :chapters="$this->chapters"
        :grade-suggestions="$this->gradeSuggestions"
        :cancel-url="route('members.index')"
        submit-label="Add member"
    />
</div>
