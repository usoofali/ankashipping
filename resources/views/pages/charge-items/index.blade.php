<?php

declare(strict_types=1);

use App\Models\ChargeItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Charge Items')] class extends Component {
    use WithPagination;
    use \WireUi\Traits\WireUiActions;

    public string $search = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    // Form fields
    public ?int $editingId = null;
    public string $item = '';

    public string $description = '';

    public string $default_amount = '0.00';

    public bool $apply_customer_discount = false;

    public ?int $deletingId = null;

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
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('charge_items.update');
        $this->resetValidation();
        $chargeItem = ChargeItem::findOrFail($id);
        
        $this->editingId = $chargeItem->id;
        $this->item = $chargeItem->item;
        $this->description = $chargeItem->description ?? '';
        $this->default_amount = number_format((float) $chargeItem->default_amount, 2, '.', '');
        $this->apply_customer_discount = (bool) $chargeItem->apply_customer_discount;
        
        $this->showFormModal = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'item' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'apply_customer_discount' => ['boolean'],
        ]);

        if ($this->editingId) {
            $this->authorize('charge_items.update');
            $chargeItem = ChargeItem::findOrFail($this->editingId);
            $chargeItem->update([
                'item' => $this->item,
                'description' => $this->description,
                'default_amount' => $this->default_amount,
                'apply_customer_discount' => $this->apply_customer_discount,
            ]);
            $this->notification()->success('Charge Item updated successfully.');
        } else {
            $this->authorize('charge_items.create');
            ChargeItem::create([
                'item' => $this->item,
                'description' => $this->description,
                'default_amount' => $this->default_amount,
                'apply_customer_discount' => $this->apply_customer_discount,
            ]);
            $this->notification()->success('Charge Item created successfully.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('charge_items.delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteItem(): void
    {
        $this->authorize('charge_items.delete');
        $chargeItem = ChargeItem::findOrFail($this->deletingId);
        $chargeItem->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->notification()->success('Charge Item deleted successfully.');
        $this->resetPage('chargeItemsPage');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->item = '';
        $this->description = '';
        $this->default_amount = '0.00';
        $this->apply_customer_discount = false;
    }


};
?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <x-crud.page-header :heading="__('Charge Items')" :subheading="__('Manage line items and charge descriptions for billing.')" icon="ticket" class="!mb-0" />
            
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search items...')" clearable class="w-64" />
                @can('charge_items.create')
                    <flux:button wire:click="openCreateModal" variant="primary" icon="plus">{{ __('Create Item') }}</flux:button>
                @endcan
            </div>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->chargeItems">
                <flux:table.columns>
                    <flux:table.column icon="tag">{{ __('Item Name') }}</flux:table.column>
                    <flux:table.column icon="document-text">{{ __('Description') }}</flux:table.column>
                    <flux:table.column align="end" icon="currency-dollar">{{ __('Default amount (USD)') }}</flux:table.column>
                    <flux:table.column icon="tag">{{ __('Discount status') }}</flux:table.column>
                    <flux:table.column icon="clock">{{ __('Last Updated') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->chargeItems as $chargeItem)
                        <flux:table.row :key="$chargeItem->id">
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $chargeItem->item }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-zinc-600 dark:text-zinc-400">{{ Str::limit($chargeItem->description, 50) ?: '—' }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="font-mono text-sm">
                                ${{ number_format((float) $chargeItem->default_amount, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($chargeItem->apply_customer_discount)
                                    <flux:badge color="emerald" variant="subtle" size="sm">{{ __('On') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" variant="subtle" size="sm">{{ __('Off') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-zinc-500 text-sm" title="{{ $chargeItem->updated_at }}">{{ $chargeItem->updated_at->diffForHumans() }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('charge_items.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $chargeItem->id }})" wire:key="edit-open-{{ $chargeItem->id }}">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('charge_items.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $chargeItem->id }})" wire:key="delete-open-{{ $chargeItem->id }}">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                                {{ __('No charge items found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    <!-- Create/Edit Form Modal -->
    <flux:modal wire:model.self="showFormModal" class="md:w-[36rem]">
        <div class="mb-6 flex items-start gap-4 border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="rounded-xl bg-amber-50 p-3 text-amber-600 dark:bg-amber-950/20 dark:text-amber-400">
                <flux:icon.ticket class="size-6" />
            </div>
            <div>
                <flux:heading size="lg" weight="semibold">{{ $editingId ? __('Edit Charge Item') : __('Create Charge Item') }}</flux:heading>
                <flux:subheading>{{ __('Define billing items to be used in invoices.') }}</flux:subheading>
            </div>
        </div>

        <form wire:submit="saveItem" class="space-y-6">
            <flux:input wire:model="item" id="item" :label="__('Item Name')" placeholder="e.g. Ocean Freight" required />

            <flux:textarea wire:model="description" id="description" :label="__('Description')" placeholder="Optional standard description" rows="3" />

            <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                <flux:heading size="sm" class="font-semibold uppercase tracking-wider text-zinc-500">
                    {{ __('Pricing & discount') }}
                </flux:heading>

                <flux:input
                    wire:model="default_amount"
                    id="default_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    :label="__('Default amount (USD)')"
                    icon="currency-dollar"
                    required
                />

                <flux:field>
                    <flux:checkbox wire:model="apply_customer_discount" :label="__('Shipper discount')" />
                    <flux:description>
                        {{ __('When enabled, the line amount is the default amount minus the shipper’s per-line discount, and the amount is read-only on the shipment invoice form.') }}
                    </flux:description>
                </flux:field>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Item') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model.self="showDeleteModal" class="md:w-[32rem]">
        <div class="mb-6 flex items-start gap-4 border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="rounded-xl bg-red-50 p-3 text-red-600 dark:bg-red-950/20 dark:text-red-400">
                <flux:icon.trash class="size-6" />
            </div>
            <div>
                <flux:heading size="lg" weight="semibold">{{ __('Delete Charge Item') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to permanently delete this item?') }}</flux:subheading>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button wire:click="deleteItem" variant="danger">{{ __('Yes, Delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
