<?php

use App\Livewire\Forms\MemberForm;
use App\Models\Chapter;
use App\Models\Member;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit member')] class extends Component
{
    public Member $member;

    public MemberForm $form;

    public function mount(): void
    {
        $this->authorize('update', $this->member);

        $this->form->setMember($this->member);
    }

    public function save(): void
    {
        $this->authorize('update', $this->member);

        $this->form->update();

        $this->redirectRoute('members.show', $this->member, navigate: true);
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
        <a href="{{ route('members.show', $member) }}" wire:navigate class="-ml-2 inline-flex min-h-11 items-center gap-1 self-start rounded-lg px-2 text-[15px] font-semibold text-blue hover:text-navy focus-visible:outline-2 focus-visible:outline-blue lg:min-h-0 lg:text-sm">
            <x-icon name="chevron-left" :size="20" :stroke-width="2.2" />
            {{ $member->full_name }}
        </a>
        <h1 class="font-display text-[26px] font-extrabold tracking-[-0.01em] lg:text-[32px]">Edit member</h1>
    </div>

    <x-member-form
        :form="$form"
        :chapters="$this->chapters"
        :grade-suggestions="$this->gradeSuggestions"
        :cancel-url="route('members.show', $member)"
        submit-label="Save changes"
    />
</div>
