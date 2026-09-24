<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ItemBase;

new #[Layout('components.layouts.admin')] class extends Component {
    public ItemBase $group;

    public function mount($id)
    {
        $this->group = ItemBase::where('id', $id)->whereNull('parent_id')->firstOrFail();
    }
}; 
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-medium text-gray-900">{{ $group->base_title }}</h1>
            <p class="text-gray-500 mt-1">{{ $group->description }}</p>
        </div>
        
        <div class="flex gap-3">
            <a href="/admin/groups/{{ $group->id }}/edit" wire:navigate class="px-4 py-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                Edit Group
            </a>
            <a href="/admin/groups/{{ $group->id }}/items/create" wire:navigate class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Add {{ $group->base_title }} Item
            </a>
        </div>
    </div>

    <div class="border border-gray-200 bg-white">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="p-3 font-medium text-gray-600">Item Title</th>
                    <th class="p-3 font-medium text-gray-600">Base Price</th>
                    <th class="p-3 font-medium text-gray-600">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($group->children as $child)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3 text-blue-600"><a href="/admin/items/{{ $child->id }}/edit" wire:navigate class="hover:underline">{{ $child->base_title }}</a></td>
                        <td class="p-3 text-gray-600">{{ number_format($child->base_price, 2) }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs {{ $child->base_status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $child->base_status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="p-6 text-center text-gray-500">No items found in this group.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>