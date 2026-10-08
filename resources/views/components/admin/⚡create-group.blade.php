<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use App\Models\ItemBase;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.admin')] class extends Component {
    public ?ItemBase $group = null;

    public ?int $template_id = null;

    public $name = '';
    public $description = '';
    public $status = 'Published';
    public $is_sellable = 0;

    public $has_sku = 1;
    public $has_barcode = 0;
    public $has_compare_at_price = 0;
    public $has_cost_price = 0;
    public $has_commission_rate = 0;
    public $has_supplier_id = 0;
    public $has_shipping_weight = 0;
    public $has_shipping_dimensions = 0;

    public $schema = [];
    public $descriptor_groups = [];

    public function mount($id = null)
    {
        if ($id) {
            $this->group = ItemBase::with(['describedBy'])->findOrFail($id);

            $this->name = $this->group->name;
            $this->description = $this->group->description;
            $this->status = $this->group->status;
            $this->is_sellable = (int) $this->group->is_sellable;

            $config = $this->group->json_specifications ?? [];
            $sellableConfig = $config['sellable_fields'] ?? [];

            $this->has_sku = (int) ($sellableConfig['has_sku'] ?? 1);
            $this->has_barcode = (int) ($sellableConfig['has_barcode'] ?? 0);
            $this->has_compare_at_price = (int) ($sellableConfig['has_compare_at_price'] ?? 0);
            $this->has_cost_price = (int) ($sellableConfig['has_cost_price'] ?? 0);
            $this->has_commission_rate = (int) ($sellableConfig['has_commission_rate'] ?? 0);
            $this->has_supplier_id = (int) ($sellableConfig['has_supplier_id'] ?? 0);
            $this->has_shipping_weight = (int) ($sellableConfig['has_shipping_weight'] ?? 0);
            $this->has_shipping_dimensions = (int) ($sellableConfig['has_shipping_dimensions'] ?? 0);

            $this->schema = $config['child_schema'] ?? [];
            $this->descriptor_groups = $this->group->describedBy->pluck('id')->toArray();
            $this->template_id = $this->group->template_id;
        }
    }

    public function addSchemaRow()
    {
        $this->schema[] = ['name' => '', 'type' => 'string', 'default' => ''];
    }

    public function removeSchemaRow($index)
    {
        unset($this->schema[$index]);
        $this->schema = array_values($this->schema);
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

    public function save(bool $andDesign = false): void
    {
        $ignoreId = $this->group ? $this->group->id : null;

        $this->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'required|in:Published,Draft,Archived',
            'template_id' => 'nullable|exists:templates,id',
            'is_sellable' => 'boolean',
            'has_sku' => 'boolean',
            'has_barcode' => 'boolean',
            'has_compare_at_price' => 'boolean',
            'has_cost_price' => 'boolean',
            'has_commission_rate' => 'boolean',
            'has_supplier_id' => 'boolean',
            'has_shipping_weight' => 'boolean',
            'has_shipping_dimensions' => 'boolean',
            'descriptor_groups' => 'array',
        ]);
        $filteredSchema = array_values(
            array_filter($this->schema, function ($row) {
                return !empty(trim($row['name'] ?? ''));
            }),
        );

        $expressionLanguage = new ExpressionLanguage();
        $availableVars = [];

        foreach ($filteredSchema as $field) {
            if (in_array($field['type'], ['int', 'boolean']) && !empty(trim($field['name']))) {
                $availableVars[] = $field['name'];
            }
        }

        foreach ($filteredSchema as $index => $field) {
            if ($field['type'] === 'int' && $field['default'] !== '' && !is_numeric($field['default'])) {
                $this->addError("schema.{$index}.default", "Default value for '{$field['name']}' must be a number.");
                return;
            }
            if ($field['type'] === 'boolean' && $field['default'] !== '' && !in_array(strtolower((string) $field['default']), ['1', '0', 'true', 'false', 'yes', 'no'])) {
                $this->addError("schema.{$index}.default", "Default value for '{$field['name']}' must be boolean (true/false/1/0).");
                return;
            }

            if ($field['type'] === 'expression') {
                try {
                    $expressionLanguage->parse($field['default'], $availableVars);
                } catch (\Exception $e) {
                    $this->addError('form_error', "Expression error in '{$field['name']}': " . $e->getMessage());
                    return;
                }
            }
        }

        try {
            $targetGroupId = null;

            DB::transaction(function () use ($ignoreId, $filteredSchema, &$targetGroupId) {
                $jsonSpecifications = [
                    'child_schema' => $filteredSchema,
                    'sellable_fields' => $this->is_sellable
                        ? [
                            'has_sku' => $this->has_sku ? 1 : 0,
                            'has_barcode' => $this->has_barcode ? 1 : 0,
                            'has_compare_at_price' => $this->has_compare_at_price ? 1 : 0,
                            'has_cost_price' => $this->has_cost_price ? 1 : 0,
                            'has_commission_rate' => $this->has_commission_rate ? 1 : 0,
                            'has_supplier_id' => $this->has_supplier_id ? 1 : 0,
                            'has_shipping_weight' => $this->has_shipping_weight ? 1 : 0,
                            'has_shipping_dimensions' => $this->has_shipping_dimensions ? 1 : 0,
                        ]
                        : null,
                ];

                $slug = $this->generateUniqueSlug($this->name, $ignoreId);

                $baseData = [
                    'name' => $this->name,
                    'slug' => $slug,
                    'description' => $this->description,
                    'status' => $this->status,
                    'template_id' => $this->template_id ?: null,
                    'is_sellable' => (bool) $this->is_sellable,
                    'json_specifications' => $jsonSpecifications,
                ];

                if ($this->group) {
                    $this->group->update($baseData);
                    $this->group->describedBy()->sync($this->descriptor_groups);
                    $targetGroupId = $this->group->id;
                } else {
                    $baseData['parent_id'] = null;

                    $base = ItemBase::create($baseData);

                    if (!empty($this->descriptor_groups)) {
                        $base->describedBy()->sync($this->descriptor_groups);
                    }

                    $targetGroupId = $base->id;
                }
            });

            if ($andDesign && $targetGroupId) {
                $this->redirect("/admin/templates/create?type=item_detail&group_id={$targetGroupId}&return_to=/admin/groups/{$targetGroupId}", navigate: true);
                return;
            }

            if ($this->group) {
                $this->redirect('/admin/groups/' . $this->group->id, navigate: true);
            } else {
                $this->redirect('/admin', navigate: true);
            }
        } catch (\Exception $e) {
            $this->addError('form_error', 'Failed to save: ' . $e->getMessage());
        }
    }

    public function saveAndDesign(): void
    {
        $this->save(andDesign: true);
    }

    public function with()
    {
        $query = ItemBase::whereNull('parent_id');
        if ($this->group) {
            $query->where('id', '!=', $this->group->id);
        }

        return [
            'existingGroups' => $query->get(),
            'detailTemplates' => Template::where('type', 'item_detail')->get(),
        ];
    }
};
?>

<div class="max-w-xl">
    <h1 class="text-lg font-medium mb-4">{{ $group ? 'Edit' : 'Create' }} Item Group</h1>

    @error('form_error')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="save" class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 text-sm">
            <div>
                <label>Name</label>
                <input type="text" wire:model="name" class="border border-gray-300 p-1.5 w-full">
                @error('name')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label>Description</label>
                <textarea wire:model="description" rows="3" class="border border-gray-300 p-1.5 w-full"></textarea>
                @error('description')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label>Visibility Status</label>
                <select wire:model="status" class="border border-gray-300 p-1.5 w-full bg-white">
                    <option value="Published">Published</option>
                    <option value="Draft">Draft</option>
                    <option value="Archived">Archived</option>
                </select>
                @error('status')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label>Detail Page Template</label>
                <select wire:model="template_id" class="border border-gray-300 p-1.5 w-full bg-white">
                    <option value="">Default (No Template Assigned)</option>
                    @foreach ($detailTemplates as $templateOption)
                        <option value="{{ $templateOption->id }}">{{ $templateOption->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">To design a custom layout tied to this group's custom schema, save
                    the group first or select "Save &amp; Design Layout".</p>
                @error('template_id')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="border-t pt-4">
            <h2 class="text-sm font-medium mb-2">Group Type</h2>
            <div class="flex flex-col gap-1 mb-4">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input type="checkbox" wire:model.live="is_sellable" value="1">
                        Sellable Items (Enables purchasing attributes for children)
                    </label>
                </div>
                @error('is_sellable')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>

            @if ($is_sellable)
                <div class="p-3 bg-gray-50 border border-gray-200 text-sm">
                    <p class="mb-3 text-gray-600">Select the commercial fields required for items in this group. Price
                        and Quantity are always required.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-1.5 text-gray-400">
                            <input type="checkbox" checked disabled> Price
                        </label>
                        <label class="flex items-center gap-1.5 text-gray-400">
                            <input type="checkbox" checked disabled> Quantity
                        </label>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_sku" value="1"> SKU
                            </label>
                            @error('has_sku')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_barcode" value="1"> Barcode
                            </label>
                            @error('has_barcode')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_compare_at_price" value="1"> Compare at
                                Price
                            </label>
                            @error('has_compare_at_price')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_cost_price" value="1"> Cost Price
                            </label>
                            @error('has_cost_price')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_commission_rate" value="1"> Commission Rate
                            </label>
                            @error('has_commission_rate')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_supplier_id" value="1"> Supplier ID
                            </label>
                            @error('has_supplier_id')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_shipping_weight" value="1"> Shipping Weight
                            </label>
                            @error('has_shipping_weight')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="has_shipping_dimensions" value="1"> Shipping
                                Dimensions
                            </label>
                            @error('has_shipping_dimensions')
                                <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="border-t pt-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-medium">Child Custom Specifications</h2>
                <button type="button" wire:click="addSchemaRow"
                    class="text-xs border px-2 py-1 bg-gray-50 hover:bg-gray-100">
                    + Add Attribute
                </button>
            </div>

            @if (empty($schema))
                <p class="text-xs text-gray-500">No custom properties defined.</p>
            @endif

            <div class="flex flex-col gap-2">
                @foreach ($schema as $index => $row)
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2 items-center text-sm">
                            <input type="text" wire:model.live.debounce.300ms="schema.{{ $index }}.name"
                                placeholder="Name" class="border border-gray-300 p-1 flex-1">

                            <select wire:model.live="schema.{{ $index }}.type"
                                class="border border-gray-300 p-1">
                                <option value="string">String</option>
                                <option value="int">Integer</option>
                                <option value="boolean">Boolean</option>
                                <option value="expression">Expression (Math)</option>
                            </select>

                            @if ($schema[$index]['type'] === 'expression')
                                @php
                                    $validVars = collect($schema)
                                        ->filter(
                                            fn($f) => !empty($f['name']) && in_array($f['type'], ['int', 'boolean']),
                                        )
                                        ->pluck('name')
                                        ->values()
                                        ->toJson();
                                @endphp
                                <div wire:key="expr-{{ $index }}-{{ md5($validVars) }}"
                                    x-data="{
                                        text: @entangle('schema.' . $index . '.default').live,
                                        vars: {{ $validVars }},
                                        get highlighted() {
                                            let val = (typeof this.text === 'string') ? this.text : '';
                                            let escaped = val.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                            if (this.vars.length > 0) {
                                                let regex = new RegExp('\\b(' + this.vars.join('|') + ')\\b', 'g');
                                                escaped = escaped.replace(regex, '<span class=\'text-blue-500 font-semibold\'>$1</span>');
                                            }
                                            return escaped;
                                        }
                                    }" class="relative flex-1 font-mono text-sm">

                                    <div x-ref="backdrop"
                                        class="absolute inset-0 p-1 border border-transparent whitespace-pre text-gray-600 pointer-events-none overflow-hidden"
                                        x-html="highlighted"></div>

                                    <input type="text" x-model="text"
                                        @scroll="$refs.backdrop.scrollLeft = $el.scrollLeft"
                                        placeholder="e.g. width * height"
                                        class="w-full h-full border border-gray-300 p-1 bg-transparent text-transparent caret-black focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono">
                                </div>
                            @else
                                <input type="text"
                                    wire:model.live.debounce.300ms="schema.{{ $index }}.default"
                                    placeholder="Default"
                                    class="border border-gray-300 p-1 flex-1 text-gray-900 bg-white font-sans text-sm">
                            @endif

                            <button type="button" wire:click="removeSchemaRow({{ $index }})"
                                class="text-red-600 text-xs px-1">Remove</button>
                        </div>

                        <!-- Consolidated Schema Errors -->
                        <div class="flex flex-col gap-1">
                            @error("schema.{$index}.name")
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                            @error("schema.{$index}.type")
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                            @error("schema.{$index}.default")
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="border-t pt-4">
            <h2 class="text-sm font-medium mb-2">Allowed Descriptors</h2>
            @if ($existingGroups->isEmpty())
                <p class="text-xs text-gray-500">No other groups exist.</p>
            @else
                <div class="flex flex-col gap-1 text-sm">
                    @foreach ($existingGroups as $groupOption)
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" wire:model="descriptor_groups" value="{{ $groupOption->id }}">
                            {{ $groupOption->name }}
                        </label>
                    @endforeach
                    @error('descriptor_groups')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3 mt-4">
            <button type="submit" class="border bg-gray-800 text-white text-sm px-4 py-2 hover:bg-black">
                {{ $group ? 'Update Group' : 'Save Group' }}
            </button>
            <button type="button" wire:click="saveAndDesign"
                class="border border-blue-600 bg-blue-600 text-white text-sm px-4 py-2 hover:bg-blue-700">
                Save &amp; Design Layout
            </button>
        </div>
    </form>
</div>
