<?php

use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.admin')] class extends Component {
    public string $search = '';

    public string $typeFilter = 'All';

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = Template::query();

        if ($this->typeFilter !== 'All') {
            $query->where('type', $this->typeFilter);
        }

        if (!empty(trim($this->search))) {
            $searchTerm = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)->orWhere('slug', 'like', $searchTerm);
            });
        }

        return [
            'templates' => $query->latest()->get(),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-medium text-gray-900">Page Builder Templates</h1>
            <p class="text-gray-500 mt-1 text-sm">Manage dynamic templates, item detail layouts, and catalog components.
            </p>
        </div>

        <a href="/admin/templates/create" wire:navigate
            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
            New Template
        </a>
    </div>

    <div class="flex gap-4 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search templates by name or slug..."
            class="border border-gray-300 p-2 text-sm flex-1 max-w-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" />

        <select wire:model.live="typeFilter"
            class="border border-gray-300 p-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
            <option value="All">All Types</option>
            <option value="page">Standard Page</option>
            <option value="item_detail">Item Detail Layout</option>
            <option value="catalog">Catalog</option>
        </select>
    </div>

    <div class="border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <th class="p-3 font-medium">Name</th>
                    <th class="p-3 font-medium">Type</th>
                    <th class="p-3 font-medium">Slug</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse ($templates as $template)
                    <tr class="hover:bg-gray-50">
                        <td class="p-3">
                            <a href="/admin/templates/{{ $template->id }}" wire:navigate
                                class="font-medium text-blue-600 hover:underline">
                                {{ $template->name }}
                            </a>
                        </td>
                        <td class="p-3">
                            @php
                                $typeStyles = match ($template->type) {
                                    'item_detail' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'catalog' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-blue-50 text-blue-700 border-blue-200',
                                };
                                $typeLabel = match ($template->type) {
                                    'item_detail' => 'Item Detail',
                                    'catalog' => 'Catalog',
                                    default => 'Page',
                                };
                            @endphp
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $typeStyles }}">
                                {{ $typeLabel }}
                            </span>
                        </td>
                        <td class="p-3 text-gray-500 font-mono text-xs">
                            {{ $template->slug ? '/' . $template->slug : '—' }}
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center gap-1.5 text-xs">
                                <span
                                    class="w-2 h-2 rounded-full {{ $template->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                <span class="{{ $template->is_active ? 'text-green-700' : 'text-gray-500' }}">
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <a href="/admin/templates/{{ $template->id }}" wire:navigate
                                class="text-blue-600 hover:underline text-xs font-medium">
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-gray-500 text-sm">
                            @if (!empty($search) || $typeFilter !== 'All')
                                No templates match your search criteria.
                            @else
                                No templates found. Click "New Template" to design your first layout.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
