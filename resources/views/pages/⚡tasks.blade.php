<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tasks')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Tasks" back-to-more />

    <x-coming-soon :phase="8">Reminders and follow-ups, including ones found in your records.</x-coming-soon>
</div>
