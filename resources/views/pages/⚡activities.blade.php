<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activities & attendance')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Activities & attendance" />

    <x-coming-soon :phase="5">Activities and taking attendance.</x-coming-soon>
</div>
