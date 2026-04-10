<?php

declare(strict_types=1);

use App\Models\ChargeItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Charge Items')] class extends Component {
    use WireUiActions;
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingChargeItemId = null;
    public ?int $chargeItemPendingDeleteId = null;
    public string $chargeItemPendingDeleteLabel = '';

    public string $item = '';
    public string $description = '';
    public string $default_amount = '0.00';
    public bool $apply_customer_discount = false;

    public function mount(): void
    {
        $this->authorize('charge_items.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('chargeItemsPage');
    }

    #[Computed]
    public function chargeItems()
    {
        return ChargeItem::query()
            ->when($this->search, function ($query) {
                $query->where('item', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15, ['*'], 'chargeItemsPage');
    }

    public function openCreateModal(): void
    {
        $this->authorize('charge_items.create');
        $this->resetValidation();
        $this->reset(['item', 'description', 'default_amount', 'apply_customer_discount', 'editingChargeItemId']);
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('charge_items.update');
        $this->resetValidation();
        $chargeItem = ChargeItem::findOrFail($id);

        $this->editingChargeItemId = $chargeItem->id;
        $this->item = $chargeItem->item;
        $this->description = $chargeItem->description ?? '';
        $this->default_amount = number_format((float) $chargeItem->default_amount, 2, '.', '');
        $this->apply_customer_discount = (bool) $chargeItem->apply_customer_discount;

        $this->showEditModal = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'item' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'apply_customer_discount' => ['boolean'],
        ]);

        if ($this->editingChargeItemId) {
            $this->authorize('charge_items.update');
            ChargeItem::findOrFail($this->editingChargeItemId)->update([
                'item' => $this->item,
                'description' => $this->description,
                'default_amount' => $this->default_amount,
                'apply_customer_discount' => $this->apply_customer_discount,
            ]);
            $this->showEditModal = false;
            $this->notification()->success(__('Charge item updated successfully.'));
        } else {
            $this->authorize('charge_items.create');
            ChargeItem::create([
                'item' => $this->item,
                'description' => $this->description,
                'default_amount' => $this->default_amount,
                'apply_customer_discount' => $this->apply_customer_discount,
            ]);
            $this->showCreateModal = false;
            $this->notification()->success(__('Charge item created successfully.'));
        }

        $this->reset(['item', 'description', 'default_amount', 'apply_customer_discount', 'editingChargeItemId']);
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('charge_items.delete');
        $chargeItem = ChargeItem::findOrFail($id);
        $this->chargeItemPendingDeleteId = $chargeItem->id;
        $this->chargeItemPendingDeleteLabel = $chargeItem->item;
        $this->showDeleteModal = true;
    }

    public function deleteItem(): void
    {
        $this->authorize('charge_items.delete');

        if ($this->chargeItemPendingDeleteId) {
            $chargeItem = ChargeItem::findOrFail($this->chargeItemPendingDeleteId);
            $chargeItem->delete();

            $this->showDeleteModal = false;
            $this->notification()->success(__('Charge item deleted successfully.'));
            $this->resetPage('chargeItemsPage');
        }

        $this->chargeItemPendingDeleteId = null;
        $this->chargeItemPendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
            <x-crud.page-header :heading="__('Charge Items')" :subheading="__('Manage charge items.')" icon="ticket"
                class="!mb-0" />
            @can('charge_items.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Item') }}
                </flux:button>
            @endcan
        </div>

        <div class="mb-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search items...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->chargeItems">
                <flux:table.columns>
                    <flux:table.column icon="tag">{{ __('Item Name') }}</flux:table.column>
                    <flux:table.column icon="document-text">{{ __('Description') }}</flux:table.column>
                    <flux:table.column align="right" icon="currency-dollar">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column icon="tag">{{ __('Discount') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->chargeItems as $chargeItem)
                        <flux:table.row :key="$chargeItem->id">
                            <flux:table.cell>{{ $chargeItem->item }}</flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ Str::limit($chargeItem->description, 50) ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="right" class="font-mono text-sm text-zinc-500">
                                ${{ number_format((float) $chargeItem->default_amount, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($chargeItem->apply_customer_discount)
                                    <flux:badge color="emerald" variant="subtle" size="sm">{{ __('On') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" variant="subtle" size="sm">{{ __('Off') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('charge_items.update')
                                            <flux:menu.item icon="pencil-square"
                                                wire:click="openEditModal({{ $chargeItem->id }})">{{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('charge_items.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger"
                                                wire:click="openDeleteModal({{ $chargeItem->id }})">{{ __('Delete') }}
                                            </flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ __('No charge items found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveItem" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="ticket" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Charge Item') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new charge item to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-6">
                <flux:input wire:model="item" :label="__('Item Name')" placeholder="e.g. Ocean Freight" required />

                <flux:textarea wire:model="description" :label="__('Description')"
                    placeholder="Optional standard description" rows="3" />

                <div
                    class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                    <flux:heading size="sm" class="font-semibold uppercase tracking-wider text-zinc-500">
                        {{ __('Pricing & discount') }}
                    </flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="default_amount" type="number" min="0" step="0.01"
                            :label="__('Default amount (USD)')" icon="currency-dollar" required />

                        <flux:field>
                            <flux:checkbox wire:model="apply_customer_discount" :label="__('Shipper discount')" />
                            <flux:description>
                                {{ __('When enabled, the line amount is the default amount minus the shipper’s per-line discount.') }}
                            </flux:description>
                        </flux:field>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Item') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveItem" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Charge Item') }}</flux:heading>
                    <flux:subheading>{{ __('Update charge item details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-6">
                <flux:input wire:model="item" :label="__('Item Name')" placeholder="e.g. Ocean Freight" required />

                <flux:textarea wire:model="description" :label="__('Description')"
                    placeholder="Optional standard description" rows="3" />

                <div
                    class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                    <flux:heading size="sm" class="font-semibold uppercase tracking-wider text-zinc-500">
                        {{ __('Pricing & discount') }}
                    </flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="default_amount" type="number" min="0" step="0.01"
                            :label="__('Default amount (USD)')" icon="currency-dollar" required />

                        <flux:field>
                            <flux:checkbox wire:model="apply_customer_discount" :label="__('Shipper discount')" />
                            <flux:description>
                                {{ __('When enabled, the line amount is the default amount minus the shipper’s per-line discount.') }}
                            </flux:description>
                        </flux:field>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteItem" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Charge Item') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $chargeItemPendingDeleteLabel]) }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>