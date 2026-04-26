<?php

declare(strict_types=1);

use App\Modules\WhatsApp\Models\WhatsAppCategory;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('WhatsApp Categories')] class extends Component {
    use WireUiActions;

    public bool $showCategoryModal = false;
    public ?WhatsAppCategory $editingCategory = null;

    public string $name = '';
    public string $hashtag = '';
    public string $description = '';
    public bool $is_default = false;

    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsAppCategory::latest()->get();
    }

    public function openCategoryModal(?int $id = null): void
    {
        if ($id) {
            $this->editingCategory = WhatsAppCategory::findOrFail($id);
            $this->name = $this->editingCategory->name;
            $this->hashtag = $this->editingCategory->hashtag;
            $this->description = $this->editingCategory->description ?? '';
            $this->is_default = $this->editingCategory->is_default;
        } else {
            $this->reset(['editingCategory', 'name', 'hashtag', 'description', 'is_default']);
        }

        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'hashtag' => 'required|string|max:50|unique:whatsapp_categories,hashtag,' . ($this->editingCategory->id ?? 'NULL'),
        ]);

        if ($this->is_default) {
            WhatsAppCategory::where('is_default', true)->update(['is_default' => false]);
        }

        WhatsAppCategory::updateOrCreate(
            ['id' => $this->editingCategory->id ?? null],
            [
                'name' => $this->name,
                'hashtag' => ltrim($this->hashtag, '#'),
                'description' => $this->description,
                'is_default' => $this->is_default,
            ]
        );

        $this->showCategoryModal = false;
        $this->notification()->success(__('Category saved successfully.'));
    }

    public function deleteCategory(int $id): void
    {
        $category = WhatsAppCategory::findOrFail($id);
        
        if ($category->is_default) {
            $this->notification()->error(__('Cannot delete the default category.'));
            return;
        }

        $category->delete();
        $this->notification()->success(__('Category deleted successfully.'));
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                <flux:icon.hashtag class="size-6 text-zinc-600 dark:text-zinc-400" />
            </div>
            <x-crud.page-header :heading="__('WhatsApp Categories')" :subheading="__('Manage dynamic message categories and hashtags.')" class="mb-0!" />
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCategoryModal()">
            {{ __('New Category') }}
        </flux:button>
    </div>

    <x-crud.panel class="p-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Hashtag') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Default') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->categories() as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell class="font-bold">{{ $category->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="subtle" color="indigo">#{{ $category->hashtag }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $category->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($category->is_default)
                                <flux:badge size="sm" variant="outline" color="emerald">{{ __('Yes') }}</flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openCategoryModal({{ $category->id }})" />
                                <flux:button size="xs" variant="ghost" color="danger" icon="trash" 
                                    wire:confirm="{{ __('Are you sure you want to delete this category?') }}"
                                    wire:click="deleteCategory({{ $category->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>

    <flux:modal name="category-modal" wire:model="showCategoryModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategory ? __('Edit Category') : __('New Category') }}</flux:heading>
                <flux:subheading>{{ __('Define a category and its routing hashtag.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input label="{{ __('Name') }}" wire:model="name" placeholder="e.g. Booking" />
                <flux:input label="{{ __('Hashtag') }}" wire:model="hashtag" placeholder="e.g. booking" prefix="#" />
                <flux:textarea label="{{ __('Description') }}" wire:model="description" placeholder="{{ __('Optional details...') }}" />
                <flux:switch label="{{ __('Set as Default for Shippers') }}" wire:model="is_default" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showCategoryModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveCategory">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-crud.page-shell>
