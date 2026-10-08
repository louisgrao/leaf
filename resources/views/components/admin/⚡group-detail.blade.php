<?php

use App\Models\ItemBase;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.admin')] class extends Component {
    public ItemBase $group;

    public ?int $template_id = null;

    public $is_sellable = false;
    public $has_sku = false;
    public $schema = [];
    public $descriptorGroups = [];

    public $search = '';
    public $statusFilter = 'Published'; // Now defaults to Published

    public function mount($id)
    {
        $this->group = ItemBase::with(['describedBy', 'template'])
            ->where('id', $id)
            ->whereNull('parent_id')
            ->firstOrFail();
        $this->template_id = $this->group->template_id;

        $this->is_sellable = (bool) $this->group->is_sellable;

        $config = $this->group->json_specifications ?? [];
        $sellableConfig = $config['sellable_fields'] ?? [];

        $this->has_sku = (bool) ($sellableConfig['has_sku'] ?? 1);
        $this->schema = $config['child_schema'] ?? [];
        $this->descriptorGroups = $this->group->describedBy;

        $this->statusFilter = session()->get("group_{$id}_status_filter", 'Published');
    }

    public function updatedTemplateId($value): void
    {
        $this->group->update([
            'template_id' => $value ?: null,
        ]);
        $this->group->refresh();
    }

    public function updatedStatusFilter($value)
    {
        session()->put("group_{$this->group->id}_status_filter", $value);
    }

    public function with()
    {
        $query = ItemBase::with(['variants', 'describedBy'])->where('parent_id', $this->group->id);

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        if (!empty(trim($this->search))) {
            $searchTerm = '%' . trim($this->search) . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)->orWhereHas('describedBy', function ($q2) use ($searchTerm) {
                    $q2->where('name', 'like', $searchTerm);
                });
            });
        }

        return [
            'children' => $query->latest()->get(),
            'detailTemplates' => Template::where('type', 'item_detail')->get(),
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
            <a href="/admin/groups/{{ $group->id }}/edit" wire:navigate
                class="px-4 py-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                Edit Group
            </a>
            <a href="/admin/groups/{{ $group->id }}/items/create" wire:navigate
                class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                Add {{ $group->name }} Item
            </a>
        </div>
    </div>

    <div class="mb-6 p-3 bg-white border border-gray-200 flex flex-wrap items-center justify-between gap-4 text-sm">
        <div class="flex items-center gap-3">
            <span class="text-xs uppercase font-semibold text-gray-500">Detail Page Template:</span>
            <select wire:model.live="template_id"
                class="border border-gray-300 p-1 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">Default (No Template Assigned)</option>
                @foreach ($detailTemplates as $templateOption)
                    <option value="{{ $templateOption->id }}">{{ $templateOption->name }}</option>
                @endforeach
            </select>
            @if ($group->template)
                <a href="/admin/templates/{{ $group->template_id }}" wire:navigate
                    class="text-xs text-blue-600 hover:underline">
                    Edit Template &rarr;
                </a>
            @endif
        </div>

        @php
            $designUrl =
                '/admin/templates/create?type=item_detail&group_id=' .
                $group->id .
                '&return_to=' .
                urlencode(request()->fullUrl());
        @endphp
        <a href="{{ $designUrl }}" wire:navigate class="text-xs text-blue-600 hover:underline font-medium">
            Design Custom Layout &rarr;
        </a>
    </div>

    <div class="flex gap-4 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by item name or descriptor..."
            class="border border-gray-300 p-2 text-sm flex-1 max-w-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">

        <select wire:model.live="statusFilter"
            class="border border-gray-300 p-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
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
                    <!-- Always visible -->
                    <th class="p-3 font-medium text-gray-600">Name</th>

                    <!-- Priority 2: Hidden until MD -->
                    @if ($is_sellable)
                        @if ($has_sku)
                            <th class="hidden md:table-cell p-3 font-medium text-gray-600">SKU</th>
                        @endif
                        <th class="hidden md:table-cell p-3 font-medium text-gray-600">Price</th>
                        <th class="hidden md:table-cell p-3 font-medium text-gray-600">Quantity</th>
                    @endif

                    @if ($descriptorGroups->isNotEmpty())
                        <!-- Priority 3(Collapsed): Visible on LG, hidden on XL -->
                        <th class="hidden lg:table-cell xl:hidden p-3 font-medium text-gray-600">Descriptors</th>

                        <!-- Priority 4 (Expanded): Hidden until XL -->
                        @foreach ($descriptorGroups as $descGroup)
                            <th class="hidden xl:table-cell p-3 font-medium text-gray-600">{{ $descGroup->name }}</th>
                        @endforeach
                    @endif

                    <!-- Priority 5: Hidden until 2XL -->
                    @foreach ($schema as $field)
                        <th class="hidden 2xl:table-cell p-3 font-medium text-gray-600 capitalize">{{ $field['name'] }}
                        </th>
                    @endforeach

                    <!-- Always visible -->
                    <th class="p-3 font-medium text-gray-600">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($children as $child)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        @php $variant = $child->variants->first(); @endphp

                        <!-- Always visible -->
                        <td class="p-3 text-blue-600">
                            <a href="/admin/items/{{ $child->id }}/edit" wire:navigate
                                class="hover:underline">{{ $child->name }}</a>
                        </td>

                        <!-- Priority 2: Hidden until MD -->
                        @if ($is_sellable)
                            @if ($has_sku)
                                <td class="hidden md:table-cell p-3 text-gray-600">
                                    {{ $variant?->sku ?? '-' }}
                                </td>
                            @endif
                            <td class="hidden md:table-cell p-3 text-gray-600">
                                {{ $variant?->price !== null ? number_format($variant->price, 2) : '-' }}
                            </td>
                            <td class="hidden md:table-cell p-3 text-gray-600">
                                {{ $variant?->quantity ?? 0 }}
                            </td>
                        @endif

                        @if ($descriptorGroups->isNotEmpty())
                            <!-- Priority 3(Collapsed): Visible on LG, hidden on XL -->
                            <td class="hidden lg:table-cell xl:hidden p-3 text-gray-500 text-sm truncate max-w-[200px]">
                                {{ $child->describedBy->pluck('name')->join(', ') ?: '-' }}
                            </td>

                            <!-- Priority 4 (Expanded): Hidden until XL -->
                            @foreach ($descriptorGroups as $descGroup)
                                <td class="hidden xl:table-cell p-3 text-gray-600 text-sm">
                                    {{ $child->describedBy->where('parent_id', $descGroup->id)->pluck('name')->join(', ') ?: '-' }}
                                </td>
                            @endforeach
                        @endif

                        <!-- Priority 5: Hidden until 2XL -->
                        @foreach ($schema as $field)
                            <td class="hidden 2xl:table-cell p-3 text-gray-600">
                                @if ($field['type'] === 'boolean')
                                    {{ !empty($child->json_specifications[$field['name']]) ? 'Yes' : 'No' }}
                                @else
                                    {{ $child->json_specifications[$field['name']] ?? '-' }}
                                @endif
                            </td>
                        @endforeach

                        <!-- Always visible -->
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <span title="Visibility: {{ $child->status }}"
                                    class="flex-shrink-0 w-3 h-3 rounded-full 
                                      {{ $child->status === 'Published' ? 'bg-green-500' : ($child->status === 'Draft' ? 'bg-yellow-400' : 'bg-gray-400') }}">
                                </span>

                                @if ($child->status === 'Published')
                                    <span class="text-gray-300">|</span>

                                    <span title="Interaction: {{ $variant?->status ? 'Active' : 'Disabled' }}"
                                        class="flex-shrink-0 w-3 h-3 rounded-full border-[2px] bg-transparent 
                                          {{ $variant?->status ? 'border-green-500' : 'border-gray-400' }}">
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="100%" class="p-6 text-center text-gray-500">
                            @if (!empty($search) || $statusFilter !== 'All')
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
