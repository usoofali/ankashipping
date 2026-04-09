<?php

declare(strict_types=1);

use App\Models\Driver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Drivers')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingDriverId = null;
    public ?int $driverPendingDeleteId = null;
    public string $driverPendingDeleteLabel = '';

    public string $phone = '';
    public string $email = '';
    public string $company = '';

    public function mount(): void
    {
        $this->authorize('drivers.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function drivers()
    {
        return Driver::query()
            ->withCount('shipments')
            ->when($this->search, fn ($q) => $q->where('phone', 'like', "%{$this->search}%")
                ->orWhere('company', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->orderBy('company')
            ->orderBy('id')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('drivers.create');
        $this->reset(['phone', 'email', 'company', 'editingDriverId']);
        $this->showCreateModal = true;
    }

    public function saveNewDriver(): void
    {
        $this->authorize('drivers.create');

        $validated = $this->validate([
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        Driver::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Driver created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('drivers.update');
        $driver = Driver::findOrFail($id);

        $this->editingDriverId = $driver->id;
        $this->phone = $driver->phone ?? '';
        $this->email = $driver->email ?? '';
        $this->company = $driver->company ?? '';

        $this->showEditModal = true;
    }

    public function saveDriver(): void
    {
        $this->authorize('drivers.update');

        $validated = $this->validate([
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        Driver::findOrFail($this->editingDriverId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Driver updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('drivers.delete');
        $driver = Driver::findOrFail($id);
        $this->driverPendingDeleteId = $driver->id;
        $this->driverPendingDeleteLabel = $driver->company ?: ($driver->phone ?: ('Driver #' . $driver->id));
        $this->showDeleteModal = true;
    }

    public function deleteDriver(): void
    {
        $this->authorize('drivers.delete');

        if ($this->driverPendingDeleteId) {
            $driver = Driver::findOrFail($this->driverPendingDeleteId);
            $driverLabel = $driver->company ?: ($driver->phone ?: ('Driver #' . $driver->id));

            if ($driver->shipments()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because they have associated shipments.', ['name' => $driverLabel]));
            } else {
                $driver->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('Driver deleted successfully.'));
            }
        }

        $this->driverPendingDeleteId = null;
        $this->driverPendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Drivers')" :subheading="__('Manage delivery drivers.')" icon="identification" class="!mb-0" />
            @can('drivers.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Driver') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by company, phone or email...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->drivers">
                <flux:table.columns>
                    <flux:table.column icon="building-office">{{ __('Company') }}</flux:table.column>
                    <flux:table.column icon="phone">{{ __('Phone') }}</flux:table.column>
                    <flux:table.column icon="envelope">{{ __('Email') }}</flux:table.column>
                    <flux:table.column icon="squares-2x2">{{ __('Shipments') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->drivers as $driver)
                        <flux:table.row :key="$driver->id">
                            <flux:table.cell>{{ $driver->company ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $driver->phone ?: '—' }}</flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">{{ $driver->email ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $driver->shipments_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('drivers.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $driver->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('drivers.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $driver->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ __('No drivers found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewDriver" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="identification" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Driver') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new driver to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" icon="phone" required placeholder="+1 555 000 0000" />
                <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" placeholder="john@example.com" />
                <flux:input wire:model="company" :label="__('Company')" icon="building-office" placeholder="e.g. Fast Delivery Co." />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Driver') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveDriver" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Driver') }}</flux:heading>
                    <flux:subheading>{{ __('Update driver details.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" icon="phone" required />
                <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" />
                <flux:input wire:model="company" :label="__('Company')" icon="building-office" />
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
        <form wire:submit="deleteDriver" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Driver') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $driverPendingDeleteLabel]) }}
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
