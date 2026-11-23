<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Members')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Members" />

    <x-coming-soon :phase="3">The members directory with search and filters.</x-coming-soon>
</div>
