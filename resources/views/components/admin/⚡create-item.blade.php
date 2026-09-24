<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ItemBase;

new #[Layout('components.layouts.admin')] class extends Component {
    public ?ItemBase $item = null;
    public ItemBase $parentGroup;

    public $is_sellable = 1;

    public $base_title = '';
    public $description = '';
    public $base_status = true;
    
    // Sellable fields
    public $base_price = 0;
    public $sku = '';
    public $quantity = 0;

    public $schema = [];
    public $json_data = [];
    public $allowedDescriptorGroups = [];
    public $selected_descriptors = [];

    public function mount($groupId = null, $itemId = null)
    {
        if ($itemId) {
            $this->item = ItemBase::with(['variants', 'describedBy', 'parent'])->findOrFail($itemId);
            $this->parentGroup = $this->item->parent;

            $this->base_title = $this->item->base_title;
            $this->description = $this->item->description;
            $this->base_status = $this->item->base_status;
            $this->base_price = $this->item->base_price;

            $variant = $this->item->variants->first();
            $this->sku = $variant->sku;
            $this->quantity = $variant->quantity;
            $this->json_data = $variant->json_attributes ?? [];
            $this->selected_descriptors = $this->item->describedBy->pluck('id')->toArray();
        } else {
            $this->parentGroup = ItemBase::where('id', $groupId)->whereNull('parent_id')->firstOrFail();
        }

        $parentVariant = $this->parentGroup->variants()->first();
        $parentConfig = $parentVariant->json_attributes ?? [];

        $this->is_sellable = (int) ($parentConfig['is_sellable'] ?? 1);
        $this->schema = $parentConfig['child_schema'] ?? [];

        foreach ($this->schema as $field) {
            if (!array_key_exists($field['name'], $this->json_data)) {
                $this->json_data[$field['name']] = $field['default'] ?? null;
                if ($field['type'] === 'boolean') {
                    $this->json_data[$field['name']] = filter_var($field['default'], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }

        $this->allowedDescriptorGroups = ItemBase::with('children')
            ->whereIn('id', $this->parentGroup->describedBy()->pluck('item_bases.id'))
            ->get();

        $this->calculateExpressions();
    }

    public function calculateExpressions()
    {
        $expressionLanguage = new ExpressionLanguage();
        
        // 1. Sanitize inputs to prevent type errors and handle empty strings
        foreach ($this->schema as $field) {
            if ($field['type'] === 'int') {
                if (!isset($this->json_data[$field['name']]) || trim((string)$this->json_data[$field['name']]) === '') {
                    $this->json_data[$field['name']] = 0;
                } else {
                    $this->json_data[$field['name']] = (int) $this->json_data[$field['name']];
                }
            } elseif ($field['type'] === 'boolean') {
                $this->json_data[$field['name']] = (bool) ($this->json_data[$field['name']] ?? false);
            }
        }

        // 2. Evaluate expressions using sanitized data
        foreach ($this->schema as $field) {
            if ($field['type'] === 'expression') {
                try {
                    $this->json_data[$field['name']] = $expressionLanguage->evaluate($field['default'], $this->json_data);
                } catch (\Exception $e) {
                    $this->json_data[$field['name']] = 'Error: Missing or invalid variables';
                }
            }
        }
    }

    public function updatedJsonData()
    {
        $this->calculateExpressions();
    }

    private function generateUniqueSlug($title, $ignoreId = null)
    {
        $slug = str()->slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (true) {
            $query = ItemBase::where('slug', $slug);
            
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            
            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function save()
    {
        $ignoreId = $this->item ? $this->item->id : null;
        
        $rules = [
            'base_title' => 'required|string',
            'description' => 'nullable|string',
            'base_status' => 'boolean',
        ];

        if ($this->is_sellable) {
            $rules['base_price'] = 'required|numeric|min:0';
            $rules['quantity'] = 'required|integer|min:0';
            
            $ignoreVariantId = $this->item ? $this->item->variants->first()->id : 'NULL';
            $rules['sku'] = "nullable|string|unique:item_variants,sku,{$ignoreVariantId}";
        }

        $this->validate($rules);

        try {
            DB::transaction(function () use ($ignoreId) {
                
                // Final calculation pass to guarantee defaults are applied before saving
                $this->calculateExpressions();

                $slug = $this->generateUniqueSlug($this->base_title, $ignoreId);

                $baseData = [
                    'base_title' => $this->base_title,
                    'slug' => $slug,
                    'description' => $this->description,
                    'base_price' => $this->is_sellable ? $this->base_price : 0,
                    'base_status' => (bool) $this->base_status,
                ];

                $variantData = [
                    'title' => $this->base_title,
                    'price' => $this->is_sellable ? $this->base_price : 0,
                    'status' => (bool) $this->base_status,
                    'sku' => ($this->is_sellable && trim($this->sku) !== '') ? $this->sku : null,
                    'quantity' => $this->is_sellable ? $this->quantity : 0,
                    'json_attributes' => !empty($this->schema) ? $this->json_data : null,
                ];

                if ($this->item) {
                    $this->item->update($baseData);
                    $this->item->variants()->first()->update($variantData);
                    $this->item->describedBy()->sync($this->selected_descriptors);
                } else {
                    $baseData['parent_id'] = $this->parentGroup->id;
                    $this->item = ItemBase::create($baseData);
                    $this->item->variants()->create($variantData);
                    
                    if (!empty($this->selected_descriptors)) {
                        $this->item->describedBy()->sync($this->selected_descriptors);
                    }
                }
            });

            $this->redirect('/admin/groups/' . $this->parentGroup->id, navigate: true);

        } catch (\Exception $e) {
            $this->addError('form_error', 'Failed to save: ' . $e->getMessage());
        }
    }
}; 
?>

<div class="max-w-xl">
    <h1 class="text-lg font-medium mb-4">{{ $item ? 'Edit' : 'Create' }} {{ $parentGroup->base_title }} Item</h1>

    @error('form_error')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="save" class="flex flex-col gap-4 text-sm">
        <div class="flex flex-col gap-2">
            <label>Title</label>
            <input type="text" wire:model="base_title" class="border border-gray-300 p-1.5">

            <label>Description</label>
            <textarea wire:model="description" rows="3" class="border border-gray-300 p-1.5"></textarea>

            <label class="flex items-center gap-1.5 mt-1">
                <input type="checkbox" wire:model="base_status"> Active
            </label>
        </div>

        @if($is_sellable)
            <div class="border-t pt-4 flex gap-4">
                <div class="flex-1">
                    <label class="block mb-1">Price</label>
                    <input type="number" step="0.01" wire:model="base_price" class="border border-gray-300 p-1.5 w-full">
                </div>

                <div class="flex-1">
                    <label class="block mb-1">SKU</label>
                    <input type="text" wire:model="sku" class="border border-gray-300 p-1.5 w-full">
                </div>

                <div class="flex-1">
                    <label class="block mb-1">Quantity</label>
                    <input type="number" wire:model="quantity" class="border border-gray-300 p-1.5 w-full">
                </div>
            </div>
        @endif

        @if(!empty($schema))
            <div class="border-t pt-4">
                <h2 class="font-medium mb-2">Properties</h2>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($schema as $field)
                        <div>
                            <label class="block mb-1 capitalize text-xs text-gray-600">{{ $field['name'] }}</label>
                            @if($field['type'] === 'int')
                                <input type="number" wire:model.live.debounce.300ms="json_data.{{ $field['name'] }}" class="border border-gray-300 p-1.5 w-full">
                            @elseif($field['type'] === 'boolean')
                                <label class="flex items-center gap-1.5 mt-2">
                                    <input type="checkbox" wire:model.live="json_data.{{ $field['name'] }}"> Yes
                                </label>
                            @elseif($field['type'] === 'expression')
                                <input type="text" disabled value="{{ $json_data[$field['name']] ?? '0' }}" title="Formula: {{ $field['default'] }}" class="border border-gray-200 bg-gray-50 text-gray-600 p-1.5 w-full italic cursor-not-allowed">
                            @else
                                <input type="text" wire:model.live.debounce.300ms="json_data.{{ $field['name'] }}" class="border border-gray-300 p-1.5 w-full">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($allowedDescriptorGroups->isNotEmpty())
            <div class="border-t pt-4">
                <h2 class="font-medium mb-2">Descriptors</h2>
                <div class="flex flex-col gap-3">
                    @foreach($allowedDescriptorGroups as $group)
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">{{ $group->base_title }}</span>
                            @if($group->children->isEmpty())
                                <span class="text-xs italic text-gray-400">None available</span>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach($group->children as $child)
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" wire:model="selected_descriptors" value="{{ $child->id }}">
                                            {{ $child->base_title }}
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="submit" class="border bg-gray-800 text-white p-2 hover:bg-black mt-2">
            {{ $item ? 'Update Item' : 'Save Item' }}
        </button>
    </form>
</div>