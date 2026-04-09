<?php

declare(strict_types=1);

use App\Models\City;
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

new #[Title('Cities')] class extends Component {
    use WithFileUploads;
    use WithPagination;
    use WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showImportModal = false;
    public ?int $cityPendingDeleteId = null;
    public string $cityPendingDeleteLabel = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'country')]
    public ?int $filterCountryId = null;

    #[Url(as: 'state')]
    public ?int $filterStateId = null;

    // Fields for Create/Edit
    public ?int $editingCityId = null;
    public ?int $country_id = null;
    public ?int $state_id = null;
    public string $name = '';
    public mixed $importFile = null;

    public function mount(): void
    {
        $this->authorize('cities.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCountryId(): void
    {
        $this->filterStateId = null;
        $this->resetPage();
    }

    public function updatedFilterStateId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function filterStates()
    {
        if (!$this->filterCountryId) {
            return collect();
        }
        return State::where('country_id', $this->filterCountryId)->orderBy('name')->get();
    }

    #[Computed]
    public function countries()
    {
        return Country::select('id', 'name')->orderBy('name')->get();
    }

    #[Computed(cache: false)]
    public function states()
    {
        if (!$this->country_id) {
            return collect();
        }
        return State::where('country_id', $this->country_id)->orderBy('name')->get();
    }

    #[Computed]
    public function cities()
    {
        return City::query()
            ->with(['state', 'state.country'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStateId, fn ($q) => $q->where('state_id', $this->filterStateId))
            ->when($this->filterCountryId && !$this->filterStateId, fn ($q) => $q->whereHas('state', fn ($sq) => $sq->where('country_id', $this->filterCountryId)))
            ->orderBy('name')
            ->paginate(20);
    }

    public function updatedCountryId(): void
    {
        $this->state_id = null;
    }

    public function openCreateModal(): void
    {
        $this->authorize('cities.create');
        $this->reset(['name', 'country_id', 'state_id', 'editingCityId']);
        $this->showCreateModal = true;
    }

    public function openImportModal(): void
    {
        $this->authorize('cities.create');
        $this->authorize('cities.update');
        $this->reset('importFile');
        $this->showImportModal = true;
    }

    public function importCsv(): void
    {
        $this->authorize('cities.create');
        $this->authorize('cities.update');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = CsvImportReader::read($this->importFile->getRealPath());

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($parsed['rows'] as $row) {
            $countryIso2 = strtoupper(trim((string) ($row['country_iso2'] ?? '')));
            $stateCode = strtoupper(trim((string) ($row['state_code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));

            if ($countryIso2 === '' || $stateCode === '' || $name === '') {
                $errors++;
                continue;
            }

            $country = Country::query()->where('iso2', $countryIso2)->first();
            if (! $country) {
                $errors++;
                continue;
            }

            $state = State::query()
                ->where('country_id', $country->id)
                ->where('code', $stateCode)
                ->first();
            if (! $state) {
                $errors++;
                continue;
            }

            $existing = City::query()
                ->where('state_id', $state->id)
                ->where('name', $name)
                ->first();

            City::query()->updateOrCreate(
                ['state_id' => $state->id, 'name' => $name],
                []
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

    public function saveNewCity(): void
    {
        $this->authorize('cities.create');

        $validated = $this->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:255',
        ]);

        City::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('City created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('cities.update');
        $city = City::with('state')->findOrFail($id);
        
        $this->editingCityId = $city->id;
        $this->country_id = $city->state->country_id;
        $this->state_id = $city->state_id;
        $this->name = $city->name;

        $this->showEditModal = true;
    }

    public function saveCity(): void
    {
        $this->authorize('cities.update');

        $validated = $this->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:255',
        ]);

        City::findOrFail($this->editingCityId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('City updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('cities.delete');
        $city = City::findOrFail($id);
        $this->cityPendingDeleteId = $city->id;
        $this->cityPendingDeleteLabel = $city->name;
        $this->showDeleteModal = true;
    }

    public function deleteCity(): void
    {
        $this->authorize('cities.delete');

        if ($this->cityPendingDeleteId) {
            $city = City::findOrFail($this->cityPendingDeleteId);

            if ($city->ports()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because it has associated ports.', ['name' => $city->name]));
            } else {
                $city->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('City deleted successfully.'));
            }
        }

        $this->cityPendingDeleteId = null;
        $this->cityPendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Cities')" :subheading="__('Manage cities and locations.')" icon="map-pin" class="!mb-0" />
            <div class="flex items-center gap-2">
                @can('cities.create')
                    <flux:button variant="outline" icon="arrow-down-tray" wire:click="openImportModal">{{ __('Import CSV') }}</flux:button>
                    <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create City') }}</flux:button>
                @endcan
            </div>
        </div>

        <div class="mb-4 space-y-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search cities...')" clearable />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="filterCountryId" icon="flag">
                    <option value="">{{ __('All Countries') }}</option>
                    @foreach($this->countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStateId" icon="map" :disabled="!$filterCountryId">
                    <option value="">{{ __('All States') }}</option>
                    @foreach($this->filterStates as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->cities">
                <flux:table.columns>
                    <flux:table.column icon="map-pin">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="map">{{ __('State') }}</flux:table.column>
                    <flux:table.column icon="flag">{{ __('Country') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->cities as $city)
                        <flux:table.row :key="$city->id">
                            <flux:table.cell class="font-medium">{{ $city->name }}</flux:table.cell>
                            <flux:table.cell>{{ $city->state->name }}</flux:table.cell>
                            <flux:table.cell>{{ $city->state->country->name }}</flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('cities.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $city->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('cities.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $city->id }})">{{ __('Delete') }}</flux:menu.item>
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
    <flux:modal wire:model="showCreateModal" class="md:max-w-3xl">
        <form wire:submit="saveNewCity" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="map-pin" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create City') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new city.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="country_id" :label="__('Country')" icon="flag" required>
                        <option value="">{{ __('Select Country') }}</option>
                        @foreach ($this->countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="state_id" :label="__('State')" icon="map" required :disabled="!$country_id">
                        <option value="">{{ __('Select State') }}</option>
                        @foreach ($this->states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="name" :label="__('City Name')" icon="map-pin" required placeholder="e.g. Los Angeles" :disabled="!$state_id" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save City') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-3xl">
        <form wire:submit="saveCity" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit City') }}</flux:heading>
                    <flux:subheading>{{ __('Update city details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="country_id" :label="__('Country')" icon="flag" required>
                        <option value="">{{ __('Select Country') }}</option>
                        @foreach ($this->countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="state_id" :label="__('State')" icon="map" required :disabled="!$country_id">
                        <option value="">{{ __('Select State') }}</option>
                        @foreach ($this->states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="name" :label="__('City Name')" icon="map-pin" required :disabled="!$state_id" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update City') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteCity" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete City') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $cityPendingDeleteLabel]) }}
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
                <flux:heading size="lg">{{ __('Import Cities CSV') }}</flux:heading>
                <flux:subheading>{{ __('Expected headers: country_iso2, state_code, name') }}</flux:subheading>
            </div>
            <div class="space-y-3">
                <input type="file" wire:model="importFile" accept=".csv,text/csv" class="block w-full text-sm" />
                <flux:error name="importFile" />
                <flux:link :href="route('import-templates.geo', 'cities')" wire:navigate="false">
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
