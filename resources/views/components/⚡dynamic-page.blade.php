<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Page;

new #[Layout('components.layouts.app')] class extends Component {
    public Page $page;

    public function mount($slug)
    {
        $this->page = Page::where('slug', $slug)->firstOrFail();
    }
}; 
?>

<div>
    <h1 class="text-2xl">{{ $page->title }}</h1>
</div>