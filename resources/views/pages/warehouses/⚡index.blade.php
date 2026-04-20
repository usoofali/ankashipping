<?php

declare(strict_types=1);

use App\Models\Warehouse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Warehouses')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingWarehouseId = null;
    public ?int $warehousePendingDeleteId = null;
    public string $warehousePendingDeleteLabel = '';

    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public function mount(): void
    {
        $this->authorize('warehouses.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function warehouses()
    {
        return Warehouse::query()
            ->withCount('ports')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('warehouses.create');
        $this->reset(['name', 'email', 'phone', 'editingWarehouseId']);
        $this->showCreateModal = true;
    }

    public function saveNewWarehouse(): void
    {
        $this->authorize('warehouses.create');

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:warehouses,email',
            'phone' => 'nullable|string|max:50',
        ]);

        Warehouse::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Warehouse created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('warehouses.update');
        $warehouse = Warehouse::findOrFail($id);

        $this->editingWarehouseId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->email = $warehouse->email;
        $this->phone = $warehouse->phone ?? '';

        $this->showEditModal = true;
    }

    public function saveWarehouse(): void
    {
        $this->authorize('warehouses.update');

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:warehouses,email,' . $this->editingWarehouseId,
            'phone' => 'nullable|string|max:50',
        ]);

        Warehouse::findOrFail($this->editingWarehouseId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Warehouse updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('warehouses.delete');
        $warehouse = Warehouse::findOrFail($id);
        $this->warehousePendingDeleteId = $warehouse->id;
        $this->warehousePendingDeleteLabel = $warehouse->name;
        $this->showDeleteModal = true;
    }

    public function deleteWarehouse(): void
    {
        $this->authorize('warehouses.delete');

        if ($this->warehousePendingDeleteId) {
            $warehouse = Warehouse::findOrFail($this->warehousePendingDeleteId);

            if ($warehouse->ports()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because it is associated with one or more ports.', ['name' => $warehouse->name]));
            } else {
                $warehouse->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('Warehouse deleted successfully.'));
            }
        }

        $this->warehousePendingDeleteId = null;
        $this->warehousePendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
            <x-crud.page-header :heading="__('Warehouses')" :subheading="__('Manage shipping warehouses.')" icon="building-library"
                class="!mb-0" />
            <div class="flex items-center gap-2">
                @can('warehouses.create')
                    <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Warehouse') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        <div class="mb-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search by name, email or phone...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->warehouses">
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                    <flux:table.column icon="building-library">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="envelope">{{ __('Email') }}</flux:table.column>
                    <flux:table.column icon="phone">{{ __('Phone') }}</flux:table.column>
                    <flux:table.column icon="map-pin">{{ __('Associated Ports') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->warehouses as $warehouse)
                        <flux:table.row :key="$warehouse->id">
                            <flux:table.cell class="font-medium">{{ $warehouse->name }}</flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">{{ $warehouse->email }}</flux:table.cell>
                            <flux:table.cell>{{ $warehouse->phone ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $warehouse->ports_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('warehouses.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $warehouse->id }})">
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('warehouses.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger"
                                                wire:click="openDeleteModal({{ $warehouse->id }})">{{ __('Delete') }}
                                            </flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ __('No warehouses found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewWarehouse" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="building-library" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Warehouse') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new warehouse to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" icon="building-library" required
                    placeholder="e.g. Gotham Warehouse" />
                <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" required
                    placeholder="warehouse@example.com" />
                <flux:input wire:model="phone" :label="__('Phone')" icon="phone"
                    placeholder="+1 555 000 0000" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Warehouse') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveWarehouse" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Warehouse') }}</flux:heading>
                    <flux:subheading>{{ __('Update warehouse details.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" icon="building-library" required />
                <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" required />
                <flux:input wire:model="phone" :label="__('Phone')" icon="phone" />
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
        <form wire:submit="deleteWarehouse" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Warehouse') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $warehousePendingDeleteLabel]) }}
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
