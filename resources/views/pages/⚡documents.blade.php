<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Documents & photos')] class extends Component
{
    //
};
?>

<div class="contents">
    <x-page-header title="Documents & photos" back-to-more />

    <x-coming-soon :phase="9">Folders, uploads and files linked to members and activities.</x-coming-soon>
</div>
