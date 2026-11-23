<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Member profile')] class extends Component
{
    /**
     * Plain id until the Member model exists (Phase 2); then this becomes route model binding.
     */
    public int $member;
};
?>

<div class="contents">
    <x-page-header title="Member profile" />

    <x-coming-soon :phase="3">Personal info, attendance, position history, private notes and documents for one member.</x-coming-soon>
</div>
