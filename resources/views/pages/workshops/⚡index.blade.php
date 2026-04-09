<?php

declare(strict_types=1);

use App\Models\Workshop;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Workshops')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingWorkshopId = null;
    public ?int $workshopPendingDeleteId = null;
    public string $workshopPendingDeleteLabel = '';

    public string $name = '';
    public string $phone = '';
    public string $address = '';

    public function mount(): void
    {
        $this->authorize('workshops.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function workshops()
    {
        return Workshop::query()
            ->withCount('shipmentTrackings')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('address', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('workshops.create');
        $this->reset(['name', 'phone', 'address', 'editingWorkshopId']);
        $this->showCreateModal = true;
    }

    public function saveNewWorkshop(): void
    {
        $this->authorize('workshops.create');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:workshops,name',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        Workshop::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Workshop created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('workshops.update');
        $workshop = Workshop::findOrFail($id);

        $this->editingWorkshopId = $workshop->id;
        $this->name = $workshop->name;
        $this->phone = $workshop->phone ?? '';
        $this->address = $workshop->address ?? '';

        $this->showEditModal = true;
    }

    public function saveWorkshop(): void
    {
        $this->authorize('workshops.update');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:workshops,name,' . $this->editingWorkshopId,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        Workshop::findOrFail($this->editingWorkshopId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Workshop updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('workshops.delete');
        $workshop = Workshop::findOrFail($id);
        $this->workshopPendingDeleteId = $workshop->id;
        $this->workshopPendingDeleteLabel = $workshop->name;
        $this->showDeleteModal = true;
    }

    public function deleteWorkshop(): void
    {
        $this->authorize('workshops.delete');

        if ($this->workshopPendingDeleteId) {
            $workshop = Workshop::findOrFail($this->workshopPendingDeleteId);

            if ($workshop->shipmentTrackings()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because it has associated shipment tracking events.', ['name' => $workshop->name]));
            } else {
                $workshop->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('Workshop deleted successfully.'));
            }
        }

        $this->workshopPendingDeleteId = null;
        $this->workshopPendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Workshops')" :subheading="__('Manage vehicle workshops and processing facilities.')" icon="wrench-screwdriver" class="!mb-0" />
            @can('workshops.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Workshop') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, phone or address...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->workshops">
                <flux:table.columns>
                    <flux:table.column icon="wrench-screwdriver">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="phone">{{ __('Phone') }}</flux:table.column>
                    <flux:table.column icon="map-pin">{{ __('Address') }}</flux:table.column>
                    <flux:table.column icon="squares-2x2">{{ __('Tracking Events') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->workshops as $workshop)
                        <flux:table.row :key="$workshop->id">
                            <flux:table.cell class="font-medium">{{ $workshop->name }}</flux:table.cell>
                            <flux:table.cell>{{ $workshop->phone ?: '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate text-sm text-zinc-500">{{ $workshop->address ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $workshop->shipment_trackings_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('workshops.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $workshop->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('workshops.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $workshop->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ __('No workshops found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewWorkshop" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="wrench-screwdriver" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Workshop') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new workshop facility.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Workshop Name')" icon="wrench-screwdriver" required placeholder="e.g. Downtown Auto Center" />
                    <flux:input wire:model="phone" :label="__('Phone')" icon="phone" placeholder="+1 555 000 0000" />
                </div>
                <flux:textarea wire:model="address" :label="__('Address')" placeholder="123 Workshop Lane, City, State" rows="2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Workshop') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveWorkshop" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Workshop') }}</flux:heading>
                    <flux:subheading>{{ __('Update workshop details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Workshop Name')" icon="wrench-screwdriver" required />
                    <flux:input wire:model="phone" :label="__('Phone')" icon="phone" />
                </div>
                <flux:textarea wire:model="address" :label="__('Address')" rows="2" />
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
        <form wire:submit="deleteWorkshop" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Workshop') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $workshopPendingDeleteLabel]) }}
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
