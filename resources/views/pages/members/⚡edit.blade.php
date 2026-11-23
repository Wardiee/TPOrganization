<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit member')] class extends Component
{
    /**
     * Plain id until the Member model exists (Phase 2); then this becomes route model binding.
     */
    public int $member;
};
?>

<div class="contents">
    <x-page-header title="Edit member" />

    <x-coming-soon :phase="3">The form for editing a member.</x-coming-soon>
</div>
