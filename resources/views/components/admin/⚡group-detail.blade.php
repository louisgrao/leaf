<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ItemBase;

new #[Layout('components.layouts.admin')] class extends Component {
    public ItemBase $group;
    
    public $is_sellable = false;
    public $has_sku = false;
    public $schema = [];
    public $descriptorGroups = [];

    public $search = '';
    public $statusFilter = 'Published'; // Now defaults to Published

    public function mount($id)
    {
        // Load the group along with the descriptor groups it allows
        $this->group = ItemBase::with(['describedBy'])->where('id', $id)->whereNull('parent_id')->firstOrFail();
        
        $this->is_sellable = (bool) $this->group->is_sellable;
        
        $config = $this->group->json_specifications ?? [];
        $sellableConfig = $config['sellable_fields'] ?? [];
        
        $this->has_sku = (bool) ($sellableConfig['has_sku'] ?? 1);
        $this->schema = $config['child_schema'] ?? [];
        
        // Store the allowed descriptor groups for table headers
        $this->descriptorGroups = $this->group->describedBy;
    }

    public function with()
    {
        $query = ItemBase::with(['variants', 'describedBy'])
            ->where('parent_id', $this->group->id);

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        if (!empty(trim($this->search))) {
            $searchTerm = '%' . trim($this->search) . '%';
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhereHas('describedBy', function($q2) use ($searchTerm) {
                      $q2->where('name', 'like', $searchTerm);
                  });
            });
        }

        return [
            'children' => $query->latest()->get()
        ];
    }
}; 
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-medium text-gray-900">{{ $group->name }}</h1>
            <p class="text-gray-500 mt-1">{{ $group->description }}</p>
        </div>
        
        <div class="flex gap-3">
            <a href="/admin/groups/{{ $group->id }}/edit" wire:navigate class="px-4 py-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                Edit Group
            </a>
            <a href="/admin/groups/{{ $group->id }}/items/create" wire:navigate class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Add {{ $group->name }} Item
            </a>
        </div>
    </div>

    <div class="flex gap-4 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by item name or descriptor..." class="border border-gray-300 p-2 text-sm flex-1 max-w-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
        
        <select wire:model.live="statusFilter" class="border border-gray-300 p-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
            <option value="All">All Statuses</option>
            <option value="Published">Published</option>
            <option value="Draft">Draft</option>
            <option value="Archived">Archived</option>
        </select>
    </div>

    <div class="border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <!-- Priority 1: Always visible -->
                    <th class="p-3 font-medium text-gray-600">Item Name</th>
                    
                    @if($is_sellable)
                        <!-- Priority 4 & 5: Hidden on small screens -->
                        <th class="hidden md:table-cell p-3 font-medium text-gray-600">Price</th>
                        <th class="hidden md:table-cell p-3 font-medium text-gray-600">Quantity</th>
                        
                        @if($has_sku)
                            <!-- Priority 6: Hidden on medium screens -->
                            <th class="hidden lg:table-cell p-3 font-medium text-gray-600">SKU</th>
                        @endif
                    @endif
                    
                    @if($descriptorGroups->isNotEmpty())
                        <!-- Priority 7 (Collapsed): Visible on XL, hidden on 2XL -->
                        <th class="hidden xl:table-cell 2xl:hidden p-3 font-medium text-gray-600">Descriptors</th>
                        
                        <!-- Priority 7 (Expanded): Hidden until 2XL -->
                        @foreach($descriptorGroups as $descGroup)
                            <th class="hidden 2xl:table-cell p-3 font-medium text-gray-600">{{ $descGroup->name }}</th>
                        @endforeach
                    @endif

                    <!-- Priority 8: Hidden until 2XL screens -->
                    @foreach($schema as $field)
                        <th class="hidden 2xl:table-cell p-3 font-medium text-gray-600 capitalize">{{ $field['name'] }}</th>
                    @endforeach
                    
                    @if($is_sellable)
                        <!-- Priority 3: Hidden on extra-small mobile only -->
                        <th class="hidden sm:table-cell p-3 font-medium text-gray-600">Purchase Status</th>
                    @endif

                    <!-- Priority 2: Always visible -->
                    <th class="p-3 font-medium text-gray-600">Visible Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($children as $child)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3 text-blue-600">
                            <a href="/admin/items/{{ $child->id }}/edit" wire:navigate class="hover:underline">{{ $child->name }}</a>
                        </td>
                        
                        @if($is_sellable)
                            @php $variant = $child->variants->first(); @endphp
                            <td class="hidden md:table-cell p-3 text-gray-600">
                                {{ $variant?->price !== null ? number_format($variant->price, 2) : '-' }}
                            </td>
                            <td class="hidden md:table-cell p-3 text-gray-600">
                                {{ $variant?->quantity ?? 0 }}
                            </td>
                            @if($has_sku)
                                <td class="hidden lg:table-cell p-3 text-gray-600">
                                    {{ $variant?->sku ?? '-' }}
                                </td>
                            @endif
                        @endif

                        @if($descriptorGroups->isNotEmpty())
                            <!-- Collapsed Descriptors -->
                            <td class="hidden xl:table-cell 2xl:hidden p-3 text-gray-500 text-sm truncate max-w-[200px]">
                                {{ $child->describedBy->pluck('name')->join(', ') ?: '-' }}
                            </td>
                            
                            <!-- Expanded Descriptors -->
                            @foreach($descriptorGroups as $descGroup)
                                <td class="hidden 2xl:table-cell p-3 text-gray-600 text-sm">
                                    {{ $child->describedBy->where('parent_id', $descGroup->id)->pluck('name')->join(', ') ?: '-' }}
                                </td>
                            @endforeach
                        @endif

                        @foreach($schema as $field)
                            <td class="hidden 2xl:table-cell p-3 text-gray-600">
                                @if($field['type'] === 'boolean')
                                    {{ !empty($child->json_specifications[$field['name']]) ? 'Yes' : 'No' }}
                                @else
                                    {{ $child->json_specifications[$field['name']] ?? '-' }}
                                @endif
                            </td>
                        @endforeach
                        
                        @if($is_sellable)
                            <td class="hidden sm:table-cell p-3">
                                <span class="text-xs {{ $variant?->status ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $variant?->status ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                        @endif

                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded border 
                                {{ $child->status === 'Published' ? 'bg-green-50 text-green-700 border-green-200' : 
                                  ($child->status === 'Draft' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 
                                  'bg-slate-50 text-slate-600 border-slate-200') }}">
                                {{ $child->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="100%" class="p-6 text-center text-gray-500">
                            @if(!empty($search) || $statusFilter !== 'All')
                                No items found matching your filters.
                            @else
                                No items found in this group.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>