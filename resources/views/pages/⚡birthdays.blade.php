<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Birthdays & dates')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Birthdays & dates" />

    <x-coming-soon :phase="7">The month calendar of birthdays, activities and org updates.</x-coming-soon>
</div>
