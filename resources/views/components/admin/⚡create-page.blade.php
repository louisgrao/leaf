<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Page;

// Update the layout path here
new #[Layout('components.layouts.admin')] class extends Component {
    public $title = '';
    public $slug = '';

    public function save()
    {
        Page::create([
            'title' => $this->title,
            'slug' => str()->slug($this->slug),
        ]);

        $this->reset();
        $this->redirect('/' . str()->slug($this->slug), navigate: true);
    }
}; 
?>

<div>
    <h1 class="text-xl mb-4">Create New Page</h1>
    <form wire:submit="save" class="flex flex-col gap-2 max-w-sm">
        <input type="text" wire:model="title" placeholder="Page Title" class="border p-1">
        <input type="text" wire:model="slug" placeholder="URL Slug" class="border p-1">
        <button type="submit" class="bg-gray-200 border p-1 mt-2">Save Page</button>
    </form>
</div>