<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'home');
Route::livewire('/admin', 'admin.home');
Route::livewire('/admin/create', 'admin.create-page');
Route::livewire('/{slug}', 'dynamic-page');