<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'home');
Route::livewire('/admin', 'admin.home');
Route::livewire('/admin/create', 'admin.create-page');
Route::livewire('/admin/groups/create', 'admin.create-group');
Route::livewire('/admin/groups/{id}/edit', 'admin.create-group');
Route::livewire('/admin/groups/{id}', 'admin.group-detail');

Route::livewire('/admin/groups/{groupId}/items/create', 'admin.create-item');
Route::livewire('/admin/items/{itemId}/edit', 'admin.create-item');

Route::livewire('/{slug}', 'dynamic-page');