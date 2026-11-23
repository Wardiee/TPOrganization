<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add member')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Add member" />

    <x-coming-soon :phase="3">The form for adding a member.</x-coming-soon>
</div>
