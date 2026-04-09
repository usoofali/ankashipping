<?php

declare(strict_types=1);

use App\Models\Carrier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Carriers')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public ?int $carrierEditingId = null;
    public ?int $carrierPendingDeleteId = null;
    public string $carrierPendingDeleteLabel = '';

    public string $name = '';
    public string $description = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('carriers.view');
    }

    public function openCreateModal(): void
    {
        $this->authorize('carriers.create');
        $this->reset('name', 'description', 'carrierEditingId');
        $this->showCreateModal = true;
    }

    public function saveNewCarrier(): void
    {
        $this->authorize('carriers.create');

        $validated = Validator::make(
            [
                'name' => $this->name,
                'description' => $this->description,
            ],
            [
                'name' => ['required', 'string', 'max:255', 'unique:carriers,name'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]
        )->validate();

        Carrier::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Carrier created successfully.'));
    }

    public function openEditModal(int $carrierId): void
    {
        $this->authorize('carriers.update');
        $carrier = Carrier::findOrFail($carrierId);
        $this->carrierEditingId = $carrier->id;
        $this->name = $carrier->name;
        $this->description = $carrier->description ?? '';
        $this->showEditModal = true;
    }

    public function saveCarrier(): void
    {
        $this->authorize('carriers.update');
        if ($this->carrierEditingId === null) {
            return;
        }

        $carrier = Carrier::findOrFail($this->carrierEditingId);

        $validated = Validator::make(
            [
                'name' => $this->name,
                'description' => $this->description,
            ],
            [
                'name' => ['required', 'string', 'max:255', 'unique:carriers,name,' . $carrier->id],
                'description' => ['nullable', 'string', 'max:1000'],
            ]
        )->validate();

        $carrier->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Carrier updated successfully.'));
    }

    public function openDeleteModal(int $carrierId): void
    {
        $this->authorize('carriers.delete');
        $carrier = Carrier::findOrFail($carrierId);

        $this->carrierPendingDeleteId = $carrier->id;
        $this->carrierPendingDeleteLabel = $carrier->name;
        $this->showDeleteModal = true;
    }

    public function deleteCarrier(): void
    {
        $this->authorize('carriers.delete');
        if ($this->carrierPendingDeleteId === null) {
            return;
        }

        $carrier = Carrier::findOrFail($this->carrierPendingDeleteId);
        
        if ($carrier->shipments()->exists()) {
            $this->showDeleteModal = false;
            $this->notification()->warning(__('Cannot delete carrier because it is associated with one or more shipments.'));
            return;
        }

        $carrier->delete();

        $this->showDeleteModal = false;
        $this->carrierPendingDeleteId = null;
        $this->carrierPendingDeleteLabel = '';

        $this->notification()->success(__('Carrier deleted successfully.'));
    }

    #[Computed]
    public function carriers(): LengthAwarePaginator
    {
        return Carrier::query()
            ->withCount('shipments')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Carriers')" :subheading="__('Manage logistics and transport companies.')" icon="truck" class="!mb-0" />
            @can('carriers.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Carrier') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search carriers...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->carriers">
                <flux:table.columns>
                    <flux:table.column icon="building-office">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="document-text">{{ __('Description') }}</flux:table.column>
                    <flux:table.column icon="squares-2x2">{{ __('Shipments') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->carriers as $carrier)
                        <flux:table.row :key="$carrier->id">
                            <flux:table.cell class="font-medium">
                                {{ $carrier->name }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500 whitespace-normal">
                                {{ Str::limit($carrier->description, 50) ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $carrier->shipments_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown align="end" position="bottom">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        @can('carriers.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $carrier->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('carriers.delete')
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $carrier->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                                {{ __('No carriers found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewCarrier" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="truck" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Carrier') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new transport company.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Carrier Name')" icon="building-office" required />
                <flux:textarea wire:model="description" :label="__('Description')" icon="document-text" rows="3" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveCarrier" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Carrier') }}</flux:heading>
                    <flux:subheading>{{ __('Update carrier details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Carrier Name')" icon="building-office" required />
                <flux:textarea wire:model="description" :label="__('Description')" icon="document-text" rows="3" />
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
        <form wire:submit="deleteCarrier" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Carrier') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $carrierPendingDeleteLabel]) }}
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
