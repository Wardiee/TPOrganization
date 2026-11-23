<?php

use App\Http\Controllers\DocumentFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');

    Route::livewire('members', 'pages::members.index')->name('members.index');
    Route::livewire('members/create', 'pages::members.create')->name('members.create');
    Route::livewire('members/{member}', 'pages::members.show')->whereNumber('member')->name('members.show');
    Route::livewire('members/{member}/edit', 'pages::members.edit')->whereNumber('member')->name('members.edit');

    Route::livewire('org-structure', 'pages::org-structure')->name('org-structure');
    Route::livewire('activities', 'pages::activities')->name('activities');
    Route::livewire('birthdays', 'pages::birthdays')->name('birthdays');
    Route::livewire('tasks', 'pages::tasks')->name('tasks');
    Route::livewire('documents', 'pages::documents')->name('documents');
    Route::get('documents/{document}/download', [DocumentFileController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentFileController::class, 'preview'])->name('documents.preview');
    Route::livewire('reports', 'pages::reports')->name('reports');
    Route::livewire('more', 'pages::more')->name('more');
});

require __DIR__.'/settings.php';
