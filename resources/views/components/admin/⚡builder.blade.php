<?php

use App\Models\ItemBase;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.admin')] class extends Component {
    public ?Template $template = null;

    public string $name = '';

    public ?string $slug = null;

    public bool $slugIsCustom = false;

    public string $type = 'page';

    public bool $is_active = true;

    public ?int $group_id = null;

    public ?string $return_to = null;

    /**
     * @var array<string, mixed>
     */
    public array $schema = [
        'blocks' => [],
    ];

    public function mount(?Template $template = null): void
    {
        if ($template && $template->exists) {
            $this->template = $template;
            $this->name = $template->name;
            $this->slug = $template->slug;
            $this->slugIsCustom = filled($template->slug);
            $this->type = $template->type;
            $this->is_active = (bool) $template->is_active;
            $this->schema = $template->schema ?? ['blocks' => []];
        } else {
            $typeQuery = request()->query('type');
            if ($typeQuery && in_array($typeQuery, ['page', 'item_detail', 'catalog', 'catalog_card'])) {
                $this->type = $typeQuery;
            }

            $groupId = request()->query('group_id');
            if ($groupId) {
                $this->group_id = (int) $groupId;
            }

            $returnTo = request()->query('return_to');
            if ($returnTo) {
                $this->return_to = $returnTo;
            }
        }
    }

    public function updatedName($value): void
    {
        if ($this->type === 'page' && !$this->slugIsCustom) {
            $this->slug = str()->slug($value);
        }
    }

    public function updatedSlug($value): void
    {
        $this->slugIsCustom = filled(trim((string) $value));
    }

    public function updatedType($value): void
    {
        if ($value === 'page' && !$this->slugIsCustom && filled($this->name)) {
            $this->slug = str()->slug($this->name);
        }
    }

    public function save(): void
    {
        if ($this->type === 'page' && empty($this->slug) && filled($this->name)) {
            $this->slug = str()->slug($this->name);
        }

        $ignoreId = $this->template?->id ?? 'NULL';

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:page,item_detail,catalog,catalog_card',
            'is_active' => 'boolean',
            'schema' => 'array',
        ];

        if ($this->type === 'page') {
            $rules['slug'] = "required|string|max:255|unique:templates,slug,{$ignoreId}";
        } else {
            $rules['slug'] = 'nullable|string|max:255';
        }

        $validated = $this->validate($rules);

        if ($this->type !== 'page' && empty($validated['slug'])) {
            $validated['slug'] = null;
        }

        if ($this->template) {
            $this->template->update($validated);
        } else {
            $this->template = Template::create($validated);

            if ($this->group_id) {
                $group = ItemBase::find($this->group_id);
                if ($group) {
                    $group->update(['template_id' => $this->template->id]);
                }
            }
        }

        session()->flash('message', 'Template saved successfully.');

        if ($this->return_to) {
            $this->redirect($this->return_to, navigate: true);
        }
    }
};
?>

<div x-data="{
    schema: @entangle('schema'),
    selectedBlockId: null,
    activePlane: 'foreground',
    init() {
        if (!this.schema || typeof this.schema !== 'object') {
            this.schema = { blocks: [] };
        }
        if (!Array.isArray(this.schema.blocks)) {
            this.schema.blocks = [];
        }
    }
}" class="flex flex-col gap-6">
    <!-- Top Bar -->
    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
        <div class="flex items-center gap-4 flex-wrap">
            <div>
                <input type="text" wire:model.live.debounce.250ms="name" placeholder="Template Name..."
                    class="text-xl font-medium text-gray-900 border border-gray-300 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                @error('name')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>

            @if ($type === 'page')
                <div>
                    <div class="flex items-center">
                        <span
                            class="text-sm text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l px-2.5 py-1.5 font-mono">/</span>
                        <input type="text" wire:model.live.debounce.250ms="slug" placeholder="page-slug"
                            class="text-sm font-mono border border-gray-300 rounded-r px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    @error('slug')
                        <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                    @enderror
                </div>
            @endif

            <div class="flex items-center gap-2 text-sm text-gray-600">
                <label for="template-type" class="text-xs font-semibold uppercase text-gray-500">Type:</label>
                <select id="template-type" wire:model.live="type"
                    class="border border-gray-300 rounded px-2.5 py-1.5 bg-white text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="page">Page</option>
                    <option value="item_detail">Item Detail</option>
                    <option value="catalog">Catalog</option>
                    <option value="catalog_card">Catalog Card</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if (session()->has('message'))
                <span class="text-sm text-green-600 font-medium">{{ session('message') }}</span>
            @endif

            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                <input type="checkbox" wire:model="is_active"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                Active
            </label>

            @if ($return_to)
                <a href="{{ $return_to }}" wire:navigate
                    class="px-3 py-1.5 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            @else
                <a href="/admin/templates" wire:navigate
                    class="px-3 py-1.5 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Templates
                </a>
            @endif

            <button type="button" wire:click="save"
                class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded hover:bg-black transition-colors">
                Save Template
            </button>
        </div>
    </div>

    <!-- Builder Workspace Skeleton -->
    <div class="grid grid-cols-12 gap-6 min-h-[600px]">
        <!-- Component Palette (Sidebar) -->
        <aside class="col-span-3 border border-gray-200 bg-white rounded p-4 flex flex-col gap-4">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Elements Palette</h3>
                <p class="text-xs text-gray-400 mt-0.5">Drag-and-drop elements</p>
            </div>

            <!-- Palette Category Placeholders -->
            <div class="flex flex-col gap-3 text-xs">
                <div>
                    <span class="font-medium text-gray-700 block mb-1">Layout & Structure</span>
                    <div class="p-2 bg-gray-50 border border-gray-200 rounded text-gray-500 text-center">
                        Container / Grid Column
                    </div>
                </div>

                <div>
                    <span class="font-medium text-gray-700 block mb-1">Static Elements</span>
                    <div class="p-2 bg-gray-50 border border-gray-200 rounded text-gray-500 text-center">
                        Rich Text / Button / Image
                    </div>
                </div>

                <div>
                    <span class="font-medium text-gray-700 block mb-1">Dynamic Data</span>
                    <div class="p-2 bg-gray-50 border border-dashed border-gray-300 rounded text-gray-400 text-center">
                        Dynamic Field (Context-Aware)
                    </div>
                </div>

                <div>
                    <span class="font-medium text-gray-700 block mb-1">Functional</span>
                    <div class="p-2 bg-gray-50 border border-gray-200 rounded text-gray-500 text-center">
                        Catalog Loop / Form
                    </div>
                </div>
            </div>
        </aside>

        <!-- Canvas Area -->
        <main
            class="col-span-6 border border-dashed border-gray-300 bg-gray-50 rounded p-6 flex flex-col items-center justify-center min-h-[500px]">
            <template x-if="!schema || !schema.blocks || schema.blocks.length === 0">
                <div class="text-center text-gray-400">
                    <p class="text-sm font-medium">Canvas Empty</p>
                    <p class="text-xs mt-1">Blocks dropped here will be stored in reactive Alpine JSON state</p>
                </div>
            </template>

            <template x-if="schema && schema.blocks && schema.blocks.length > 0">
                <div class="w-full flex flex-col gap-4">
                    <p class="text-xs text-gray-500">
                        Configured Blocks: <span class="font-semibold text-gray-800"
                            x-text="schema.blocks.length"></span>
                    </p>
                </div>
            </template>
        </main>

        <!-- Inspector / Settings Panel -->
        <aside class="col-span-3 border border-gray-200 bg-white rounded p-4 flex flex-col gap-3">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Block Settings</h3>
                <p class="text-xs text-gray-400 mt-0.5">Context & styling properties</p>
            </div>

            <div class="text-xs text-gray-400 italic">
                Select a block on the canvas to configure multi-plane layers and data binding.
            </div>

            <!-- Reactive State Preview (Collapsed Debug Info) -->
            <details class="mt-auto border-t border-gray-100 pt-3 text-xs text-gray-500">
                <summary class="cursor-pointer font-mono text-[11px] hover:text-gray-700">JSON State Preview</summary>
                <pre class="mt-2 p-2 bg-gray-900 text-green-400 rounded text-[10px] overflow-x-auto max-h-48"
                    x-text="JSON.stringify(schema, null, 2)"></pre>
            </details>
        </aside>
    </div>
</div>
