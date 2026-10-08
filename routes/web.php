<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'home');
Route::livewire('/admin', 'admin.home');
Route::livewire('/admin/groups/create', 'admin.create-group');
Route::livewire('/admin/groups/{id}/edit', 'admin.create-group');
Route::livewire('/admin/groups/{id}', 'admin.group-detail');

Route::livewire('/admin/groups/{groupId}/items/create', 'admin.create-item');
Route::livewire('/admin/items/{itemId}/edit', 'admin.create-item');

Route::livewire('/admin/templates', 'admin.templates-index');
Route::livewire('/admin/templates/create', 'admin.builder');
Route::livewire('/admin/templates/{template}', 'admin.builder');
