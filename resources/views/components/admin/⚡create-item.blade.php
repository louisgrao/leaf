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

    // Base Fields
    public $name = '';
    public $description = '';
    public $status = 'Published';

    // Variant Fields (Only standard properties for now)
    public $variant_status = true;
    
    // Sellable Variant Fields
    public $price = null;
    public $quantity = 0;
    public $sku = '';
    public $barcode = '';
    public $compare_at_price = null;
    public $cost_price = null;
    public $commission_rate = null;
    public $supplier_id = null;
    public $shipping_weight = null;
    public $shipping_dimensions = ''; // Stored as simple string/JSON

    // Progressive Disclosure Config
    public $has_sku = 1;
    public $has_barcode = 0;
    public $has_compare_at_price = 0;
    public $has_cost_price = 0;
    public $has_commission_rate = 0;
    public $has_supplier_id = 0;
    public $has_shipping_weight = 0;
    public $has_shipping_dimensions = 0;

    public $schema = [];
    public $json_specifications = [];
    public $allowedDescriptorGroups = [];
    public $selected_descriptors = [];

    public function mount($groupId = null, $itemId = null)
    {
        if ($itemId) {
            $this->item = ItemBase::with(['variants', 'describedBy', 'parent'])->findOrFail($itemId);
            $this->parentGroup = $this->item->parent;

            $this->name = $this->item->name;
            $this->description = $this->item->description;
            $this->status = $this->item->status;
            $this->json_specifications = $this->item->json_specifications ?? [];
            $this->selected_descriptors = $this->item->describedBy->pluck('id')->toArray();

            $variant = $this->item->variants->first();
            $this->variant_status = $variant->status;
            $this->price = $variant->price;
            $this->quantity = $variant->quantity;
            $this->sku = $variant->sku;
            $this->barcode = $variant->barcode;
            $this->compare_at_price = $variant->compare_at_price;
            $this->cost_price = $variant->cost_price;
            $this->commission_rate = $variant->commission_rate;
            $this->supplier_id = $variant->supplier_id;
            $this->shipping_weight = $variant->shipping_weight;
            $this->shipping_dimensions = $variant->shipping_dimensions ? json_encode($variant->shipping_dimensions) : '';
        } else {
            $this->parentGroup = ItemBase::where('id', $groupId)->whereNull('parent_id')->firstOrFail();
        }

        // FIX: Check the actual database column on the parent group, not the JSON config
        $this->is_sellable = (bool) $this->parentGroup->is_sellable;

        $parentConfig = $this->parentGroup->json_specifications ?? [];
        $this->schema = $parentConfig['child_schema'] ?? [];

        $sellableConfig = $parentConfig['sellable_fields'] ?? [];
        $this->has_sku = (int) ($sellableConfig['has_sku'] ?? 1);
        $this->has_barcode = (int) ($sellableConfig['has_barcode'] ?? 0);
        $this->has_compare_at_price = (int) ($sellableConfig['has_compare_at_price'] ?? 0);
        $this->has_cost_price = (int) ($sellableConfig['has_cost_price'] ?? 0);
        $this->has_commission_rate = (int) ($sellableConfig['has_commission_rate'] ?? 0);
        $this->has_supplier_id = (int) ($sellableConfig['has_supplier_id'] ?? 0);
        $this->has_shipping_weight = (int) ($sellableConfig['has_shipping_weight'] ?? 0);
        $this->has_shipping_dimensions = (int) ($sellableConfig['has_shipping_dimensions'] ?? 0);

        foreach ($this->schema as $field) {
            if (!array_key_exists($field['name'], $this->json_specifications)) {
                $this->json_specifications[$field['name']] = $field['default'] ?? null;
                if ($field['type'] === 'boolean') {
                    $this->json_specifications[$field['name']] = filter_var($field['default'], FILTER_VALIDATE_BOOLEAN);
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
        
        foreach ($this->schema as $field) {
            if ($field['type'] === 'int') {
                if (!isset($this->json_specifications[$field['name']]) || trim((string)$this->json_specifications[$field['name']]) === '') {
                    $this->json_specifications[$field['name']] = 0;
                } else {
                    $this->json_specifications[$field['name']] = (int) $this->json_specifications[$field['name']];
                }
            } elseif ($field['type'] === 'boolean') {
                $this->json_specifications[$field['name']] = (bool) ($this->json_specifications[$field['name']] ?? false);
            }
        }

        foreach ($this->schema as $field) {
            if ($field['type'] === 'expression') {
                try {
                    $this->json_specifications[$field['name']] = $expressionLanguage->evaluate($field['default'], $this->json_specifications);
                } catch (\Exception $e) {
                    $this->json_specifications[$field['name']] = 'Error: Missing or invalid variables';
                }
            }
        }
    }

    public function updatedJsonSpecifications()
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
            'name' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'required|in:Published,Draft,Archived',
            'variant_status' => 'boolean',
        ];

        if ($this->is_sellable) {
            // FIX: Allow price and quantity to be left completely blank by the user
            $rules['price'] = 'nullable|numeric|min:0';
            $rules['quantity'] = 'nullable|integer|min:0';
            
            $ignoreVariantId = $this->item ? $this->item->variants->first()->id : 'NULL';
            
            if ($this->has_sku) $rules['sku'] = "nullable|string|unique:item_variants,sku,{$ignoreVariantId}";
            if ($this->has_barcode) $rules['barcode'] = "nullable|string";
            if ($this->has_compare_at_price) $rules['compare_at_price'] = 'nullable|numeric|min:0';
            if ($this->has_cost_price) $rules['cost_price'] = 'nullable|numeric|min:0';
            if ($this->has_commission_rate) $rules['commission_rate'] = 'nullable|numeric|min:0|max:100';
            if ($this->has_supplier_id) $rules['supplier_id'] = 'nullable|integer';
            if ($this->has_shipping_weight) $rules['shipping_weight'] = 'nullable|numeric|min:0';
            if ($this->has_shipping_dimensions) $rules['shipping_dimensions'] = 'nullable|string';
        }

        $this->validate($rules);

        try {
            DB::transaction(function () use ($ignoreId) {
                $this->calculateExpressions();
                $slug = $this->generateUniqueSlug($this->name, $ignoreId);

                $baseData = [
                    'name' => $this->name,
                    'slug' => $slug,
                    'description' => $this->description,
                    'status' => $this->status,
                    'is_sellable' => (bool) $this->is_sellable,
                    'json_specifications' => !empty($this->schema) ? $this->json_specifications : null,
                ];

                $variantData = [
                    'status' => (bool) $this->variant_status,
                    
                    // FIX: Safely fallback to null or 0 if left blank
                    'price' => ($this->is_sellable && $this->price !== '' && $this->price !== null) ? $this->price : null,
                    'quantity' => ($this->is_sellable && $this->quantity !== '' && $this->quantity !== null) ? $this->quantity : 0,
                    
                    'sku' => ($this->is_sellable && $this->has_sku && trim($this->sku) !== '') ? $this->sku : null,
                    'barcode' => ($this->is_sellable && $this->has_barcode && trim($this->barcode) !== '') ? $this->barcode : null,
                    'compare_at_price' => ($this->is_sellable && $this->has_compare_at_price) ? $this->compare_at_price : null,
                    'cost_price' => ($this->is_sellable && $this->has_cost_price) ? $this->cost_price : null,
                    'commission_rate' => ($this->is_sellable && $this->has_commission_rate) ? $this->commission_rate : null,
                    'supplier_id' => ($this->is_sellable && $this->has_supplier_id) ? $this->supplier_id : null,
                    'shipping_weight' => ($this->is_sellable && $this->has_shipping_weight) ? $this->shipping_weight : null,
                    'shipping_dimensions' => ($this->is_sellable && $this->has_shipping_dimensions && trim($this->shipping_dimensions) !== '') ? json_decode($this->shipping_dimensions, true) ?? $this->shipping_dimensions : null,
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

<div class="max-w-3xl">
    <h1 class="text-lg font-medium mb-4">{{ $item ? 'Edit' : 'Create' }} {{ $parentGroup->name }} Item</h1>

    @error('form_error')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="save" class="flex flex-col gap-6 text-sm">
        
        <div class="flex flex-col gap-4 border border-gray-200 p-4 bg-gray-50">
            <h2 class="font-medium text-gray-700">Core Details</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1">Name</label>
                    <input type="text" wire:model="name" class="border border-gray-300 p-1.5 w-full bg-white">
                </div>
                <div>
                    <label class="block mb-1">Visibility Status</label>
                    <select wire:model="status" class="border border-gray-300 p-1.5 w-full bg-white">
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                        <option value="Archived">Archived</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block mb-1">Description</label>
                <textarea wire:model="description" rows="3" class="border border-gray-300 p-1.5 w-full bg-white"></textarea>
            </div>
        </div>

        @if(!empty($schema))
            <div class="flex flex-col gap-4 border border-gray-200 p-4 bg-gray-50">
                <h2 class="font-medium text-gray-700">Specifications</h2>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($schema as $field)
                        <div>
                            <label class="block mb-1 capitalize text-xs text-gray-600">{{ $field['name'] }}</label>
                            @if($field['type'] === 'int')
                                <input type="number" wire:model.live.debounce.300ms="json_specifications.{{ $field['name'] }}" class="border border-gray-300 p-1.5 w-full bg-white">
                            @elseif($field['type'] === 'boolean')
                                <label class="flex items-center gap-1.5 mt-2">
                                    <input type="checkbox" wire:model.live="json_specifications.{{ $field['name'] }}"> Yes
                                </label>
                            @elseif($field['type'] === 'expression')
                                <input type="text" disabled value="{{ $json_specifications[$field['name']] ?? '0' }}" title="Formula: {{ $field['default'] }}" class="border border-gray-200 bg-gray-100 text-gray-600 p-1.5 w-full italic cursor-not-allowed">
                            @else
                                <input type="text" wire:model.live.debounce.300ms="json_specifications.{{ $field['name'] }}" class="border border-gray-300 p-1.5 w-full bg-white">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($is_sellable)
            <div class="flex flex-col gap-4 border border-gray-200 p-4 bg-gray-50">
                <div class="flex justify-between items-center">
                    <h2 class="font-medium text-gray-700">Variant Settings & Commercials</h2>
                    <label class="flex items-center gap-1.5">
                        <input type="checkbox" wire:model="variant_status"> Active for Purchase
                    </label>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block mb-1">Price</label>
                        <input type="number" step="0.01" wire:model="price" class="border border-gray-300 p-1.5 w-full bg-white">
                    </div>
                    <div>
                        <label class="block mb-1">Quantity</label>
                        <input type="number" wire:model="quantity" class="border border-gray-300 p-1.5 w-full bg-white">
                    </div>
                    
                    @if($has_sku)
                        <div>
                            <label class="block mb-1">SKU</label>
                            <input type="text" wire:model="sku" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_barcode)
                        <div>
                            <label class="block mb-1">Barcode</label>
                            <input type="text" wire:model="barcode" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_compare_at_price)
                        <div>
                            <label class="block mb-1">Compare at Price</label>
                            <input type="number" step="0.01" wire:model="compare_at_price" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_cost_price)
                        <div>
                            <label class="block mb-1">Cost Price</label>
                            <input type="number" step="0.01" wire:model="cost_price" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_commission_rate)
                        <div>
                            <label class="block mb-1">Commission Rate (%)</label>
                            <input type="number" step="0.01" wire:model="commission_rate" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_supplier_id)
                        <div>
                            <label class="block mb-1">Supplier ID</label>
                            <input type="number" wire:model="supplier_id" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_shipping_weight)
                        <div>
                            <label class="block mb-1">Shipping Weight</label>
                            <input type="number" step="0.01" wire:model="shipping_weight" class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                    @if($has_shipping_dimensions)
                        <div>
                            <label class="block mb-1">Shipping Dimensions</label>
                            <input type="text" wire:model="shipping_dimensions" placeholder='e.g. {"L": 10, "W": 5}' class="border border-gray-300 p-1.5 w-full bg-white">
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($allowedDescriptorGroups->isNotEmpty())
            <div class="flex flex-col gap-4 border border-gray-200 p-4 bg-gray-50">
                <h2 class="font-medium text-gray-700">Descriptors</h2>
                <div class="flex flex-col gap-3">
                    @foreach($allowedDescriptorGroups as $group)
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">{{ $group->name }}</span>
                            @if($group->children->isEmpty())
                                <span class="text-xs italic text-gray-400">None available</span>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach($group->children as $child)
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" wire:model="selected_descriptors" value="{{ $child->id }}">
                                            {{ $child->name }}
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