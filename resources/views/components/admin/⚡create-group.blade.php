<?php

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ItemBase;

new #[Layout('components.layouts.admin')] class extends Component {
    public ?ItemBase $group = null;

    public $base_title = '';
    public $description = '';

    public $is_sellable = 1;

    public $schema = [];
    public $descriptor_groups = [];

    public function mount($id = null)
    {
        if ($id) {
            $this->group = ItemBase::with(['variants', 'describedBy'])->findOrFail($id);
            
            $this->base_title = $this->group->base_title;
            $this->description = $this->group->description;

            $variant = $this->group->variants->first();
            $config = $variant->json_attributes ?? [];

            $this->is_sellable = (int) ($config['is_sellable'] ?? 1);
            $this->schema = $config['child_schema'] ?? [];

            $this->descriptor_groups = $this->group->describedBy->pluck('id')->toArray();
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
    
    public function save()
    {
        $ignoreId = $this->group ? $this->group->id : null;
        
        $this->validate([
            'base_title' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $filteredSchema = array_values(array_filter($this->schema, function ($row) {
            return !empty(trim($row['name'] ?? ''));
        }));

        $expressionLanguage = new ExpressionLanguage();
        $availableVars = [];

        // 1st Pass: Collect all valid variables globally from the schema
        foreach ($filteredSchema as $field) {
            if (in_array($field['type'], ['int', 'boolean']) && !empty(trim($field['name']))) {
                $availableVars[] = $field['name'];
            }
        }

        // 2nd Pass: Validate defaults and expressions
        foreach ($filteredSchema as $index => $field) {
            if ($field['type'] === 'int' && $field['default'] !== '' && !is_numeric($field['default'])) {
                $this->addError("schema.{$index}.default", "Default value for '{$field['name']}' must be a number.");
                return;
            }
            if ($field['type'] === 'boolean' && $field['default'] !== '' && !in_array(strtolower((string)$field['default']), ['1', '0', 'true', 'false', 'yes', 'no'])) {
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
        // 2. Database Transaction
        try {
            DB::transaction(function () use ($ignoreId, $filteredSchema) {
                $jsonAttributes = [
                    'is_sellable' => $this->is_sellable ? 1 : 0,
                    'child_schema' => $filteredSchema,
                ];

                $slug = $this->generateUniqueSlug($this->base_title, $ignoreId);

                if ($this->group) {
                    $this->group->update([
                        'base_title' => $this->base_title,
                        'slug' => $slug,
                        'description' => $this->description,
                    ]);

                    $this->group->variants()->first()->update([
                        'title' => $this->base_title,
                        'json_attributes' => $jsonAttributes,
                    ]);

                    $this->group->describedBy()->sync($this->descriptor_groups);
                } else {
                    $base = ItemBase::create([
                        'base_title' => $this->base_title,
                        'slug' => $slug,
                        'description' => $this->description,
                        'parent_id' => null,
                        'base_price' => 0,
                    ]);

                    $base->variants()->create([
                        'title' => $this->base_title,
                        'price' => 0,
                        'quantity' => 0,
                        'json_attributes' => $jsonAttributes,
                    ]);

                    if (!empty($this->descriptor_groups)) {
                        $base->describedBy()->sync($this->descriptor_groups);
                    }
                    
                    // Reassign to property to route properly below
                    $this->group = $base;
                }
            });

            $this->redirect('/admin/groups/' . $this->group->id, navigate: true);

        } catch (\Exception $e) {
            $this->addError('form_error', 'Failed to save: ' . $e->getMessage());
        }
    }

    public function with()
    {
        $query = ItemBase::whereNull('parent_id');
        if ($this->group) {
            $query->where('id', '!=', $this->group->id);
        }
        
        return ['existingGroups' => $query->get()];
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
            <label>Title</label>
            <input type="text" wire:model="base_title" class="border border-gray-300 p-1.5">

            <label>Description</label>
            <textarea wire:model="description" rows="3" class="border border-gray-300 p-1.5"></textarea>
        </div>

        <div class="border-t pt-4">
            <h2 class="text-sm font-medium mb-2">Group Type</h2>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" wire:model="is_sellable" value="1"> 
                    Sellable Items (Enables Price, SKU, and Quantity for children)
                </label>
            </div>
        </div>

        <div class="border-t pt-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-medium">Child Custom JSON Attributes</h2>
                <button type="button" wire:click="addSchemaRow" class="text-xs border px-2 py-1 bg-gray-50 hover:bg-gray-100">
                    + Add Attribute
                </button>
            </div>

            @if(empty($schema))
                <p class="text-xs text-gray-500">No custom properties defined.</p>
            @endif

            <div class="flex flex-col gap-2">
                @foreach($schema as $index => $row)
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2 items-center text-sm">
                            <input type="text" wire:model.live.debounce.300ms="schema.{{ $index }}.name" placeholder="Name" class="border border-gray-300 p-1 flex-1">
                            
                            <select wire:model.live="schema.{{ $index }}.type" class="border border-gray-300 p-1">
                                <option value="string">String</option>
                                <option value="int">Integer</option>
                                <option value="boolean">Boolean</option>
                                <option value="expression">Expression (Math)</option>
                            </select>

                            @if($schema[$index]['type'] === 'expression')
                                @php
                                    $validVars = collect($schema)
                                        ->filter(fn($f) => !empty($f['name']) && in_array($f['type'], ['int', 'boolean']))
                                        ->pluck('name')->values()->toJson();
                                @endphp
                                <div wire:key="expr-{{ $index }}-{{ md5($validVars) }}" 
                                    x-data="{
                                        text: @entangle('schema.'.$index.'.default').live,
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
                                    
                                    <div x-ref="backdrop" class="absolute inset-0 p-1 border border-transparent whitespace-pre text-gray-600 pointer-events-none overflow-hidden" x-html="highlighted"></div>
                                    
                                    <input type="text" x-model="text" @scroll="$refs.backdrop.scrollLeft = $el.scrollLeft" placeholder="e.g. width * height" class="w-full h-full border border-gray-300 p-1 bg-transparent text-transparent caret-black focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono">
                                </div>
                            @else
                                <input type="text" wire:model.live.debounce.300ms="schema.{{ $index }}.default" placeholder="Default" class="border border-gray-300 p-1 flex-1 text-gray-900 bg-white font-sans text-sm">
                            @endif

                            <button type="button" wire:click="removeSchemaRow({{ $index }})" class="text-red-600 text-xs px-1">Remove</button>
                        </div>
                        
                        @error("schema.{$index}.default")
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="border-t pt-4">
            <h2 class="text-sm font-medium mb-2">Allowed Descriptors</h2>
            @if($existingGroups->isEmpty())
                <p class="text-xs text-gray-500">No other groups exist.</p>
            @else
                <div class="flex flex-col gap-1 text-sm">
                    @foreach($existingGroups as $groupOption)
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" wire:model="descriptor_groups" value="{{ $groupOption->id }}">
                            {{ $groupOption->base_title }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <button type="submit" class="border bg-gray-800 text-white text-sm p-2 hover:bg-black mt-2">
            {{ $group ? 'Update Group' : 'Save Group' }}
        </button>
    </form>
</div>