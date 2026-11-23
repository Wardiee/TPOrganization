<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-mobile-top-bar />

    <x-page-header title="Dashboard" />

    <x-coming-soon :phase="4">Member counts, attendance, upcoming birthdays and the issues that need your attention.</x-coming-soon>
</div>
