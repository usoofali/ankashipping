<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\State;
use App\Support\CsvImportReader;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('States')] class extends Component {
    use WithFileUploads;
    use WithPagination;
    use WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showImportModal = false;
    public ?int $statePendingDeleteId = null;
    public string $statePendingDeleteLabel = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'country')]
    public ?int $filterCountryId = null;

    // Fields for Create/Edit
    public ?int $editingStateId = null;
    public ?int $country_id = null;
    public string $name = '';
    public string $code = '';
    public mixed $importFile = null;

    public function mount(): void
    {
        $this->authorize('states.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCountryId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::select('id', 'name')->orderBy('name')->get();
    }

    #[Computed]
    public function states()
    {
        return State::query()
            ->with('country')
            ->withCount('cities')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->filterCountryId, fn ($q) => $q->where('country_id', $this->filterCountryId))
            ->orderBy('name')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('states.create');
        $this->reset(['name', 'code', 'country_id', 'editingStateId']);
        $this->showCreateModal = true;
    }

    public function saveNewState(): void
    {
        $this->authorize('states.create');

        $validated = $this->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
        ]);

        State::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('State created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('states.update');
        $state = State::findOrFail($id);
        
        $this->editingStateId = $state->id;
        $this->country_id = $state->country_id;
        $this->name = $state->name;
        $this->code = $state->code;

        $this->showEditModal = true;
    }

    public function saveState(): void
    {
        $this->authorize('states.update');

        $validated = $this->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
        ]);

        State::findOrFail($this->editingStateId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('State updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('states.delete');
        $state = State::findOrFail($id);
        $this->statePendingDeleteId = $state->id;
        $this->statePendingDeleteLabel = $state->name;
        $this->showDeleteModal = true;
    }

    public function deleteState(): void
    {
        $this->authorize('states.delete');

        if ($this->statePendingDeleteId) {
            $state = State::findOrFail($this->statePendingDeleteId);

            if ($state->cities()->exists() || $state->ports()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because it has associated cities or ports.', ['name' => $state->name]));
            } else {
                $state->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('State deleted successfully.'));
            }
        }

        $this->statePendingDeleteId = null;
        $this->statePendingDeleteLabel = '';
    }

    public function openImportModal(): void
    {
        $this->authorize('states.create');
        $this->authorize('states.update');
        $this->reset('importFile');
        $this->showImportModal = true;
    }

    public function importCsv(): void
    {
        $this->authorize('states.create');
        $this->authorize('states.update');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = CsvImportReader::read($this->importFile->getRealPath());

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($parsed['rows'] as $row) {
            $countryIso2 = strtoupper(trim((string) ($row['country_iso2'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            $code = strtoupper(trim((string) ($row['code'] ?? '')));

            if ($countryIso2 === '' || $name === '' || $code === '') {
                $errors++;
                continue;
            }

            $country = Country::query()->where('iso2', $countryIso2)->first();
            if (! $country) {
                $errors++;
                continue;
            }

            $existing = State::query()
                ->where('country_id', $country->id)
                ->where('code', $code)
                ->first();

            State::query()->updateOrCreate(
                ['country_id' => $country->id, 'code' => $code],
                ['name' => $name]
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->showImportModal = false;
        $this->reset('importFile');
        $this->notification()->success(
            __('Import completed. Created: :created, Updated: :updated, Errors: :errors', [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ])
        );
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('States')" :subheading="__('Manage regions and states within countries.')" icon="map" class="!mb-0" />
            <div class="flex items-center gap-2">
                @can('states.create')
                    <flux:button variant="outline" icon="arrow-down-tray" wire:click="openImportModal">{{ __('Import CSV') }}</flux:button>
                    <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create State') }}</flux:button>
                @endcan
            </div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or code...')" clearable />
            <flux:select wire:model.live="filterCountryId" icon="flag">
                <option value="">{{ __('All Countries') }}</option>
                @foreach($this->countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </flux:select>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->states">
                <flux:table.columns>
                    <flux:table.column icon="map">{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column icon="flag">{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Cities') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->states as $state)
                        <flux:table.row :key="$state->id">
                            <flux:table.cell class="font-medium">{{ $state->name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $state->code }}</flux:table.cell>
                            <flux:table.cell>{{ $state->country->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="left">{{ $state->cities_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('states.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $state->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('states.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $state->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewState" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="map" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create State') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new state/region.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="country_id" :label="__('Country')" icon="flag" required>
                    <option value="">{{ __('Select Country') }}</option>
                    @foreach ($this->countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('State Name')" icon="map" required placeholder="e.g. California" />
                    <flux:input wire:model="code" :label="__('State Code')" icon="qr-code" required placeholder="CA" class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save State') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveState" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit State') }}</flux:heading>
                    <flux:subheading>{{ __('Update state details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="country_id" :label="__('Country')" icon="flag" required>
                    <option value="">{{ __('Select Country') }}</option>
                    @foreach ($this->countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('State Name')" icon="map" required />
                    <flux:input wire:model="code" :label="__('State Code')" icon="qr-code" required class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update State') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteState" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete State') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $statePendingDeleteLabel]) }}
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

    <flux:modal wire:model="showImportModal" class="max-w-lg">
        <form wire:submit="importCsv" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import States CSV') }}</flux:heading>
                <flux:subheading>{{ __('Expected headers: country_iso2, name, code') }}</flux:subheading>
            </div>
            <div class="space-y-3">
                <input type="file" wire:model="importFile" accept=".csv,text/csv" class="block w-full text-sm" />
                <flux:error name="importFile" />
                <flux:link :href="route('import-templates.geo', 'states')" wire:navigate="false">
                    {{ __('Download Sample CSV') }}
                </flux:link>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Import') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
