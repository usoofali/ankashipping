<?php

declare(strict_types=1);

use App\Models\Country;
use App\Support\CsvImportReader;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Countries')] class extends Component {
    use WithFileUploads;
    use WithPagination;
    use WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showImportModal = false;
    public ?int $countryPendingDeleteId = null;
    public string $countryPendingDeleteLabel = '';

    #[Url(as: 'q')]
    public string $search = '';

    // Fields for Create/Edit
    public ?int $editingCountryId = null;
    public string $name = '';
    public string $iso2 = '';
    public string $iso3 = '';
    public mixed $importFile = null;

    public function mount(): void
    {
        $this->authorize('countries.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::query()
            ->withCount('states')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('iso2', 'like', "%{$this->search}%")
                ->orWhere('iso3', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('countries.create');
        $this->reset(['name', 'iso2', 'iso3', 'editingCountryId']);
        $this->showCreateModal = true;
    }

    public function saveNewCountry(): void
    {
        $this->authorize('countries.create');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'iso2' => 'required|string|size:2|unique:countries,iso2',
            'iso3' => 'nullable|string|size:3|unique:countries,iso3',
        ]);

        $validated['iso3'] = $validated['iso3'] ?: null;

        Country::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Country created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('countries.update');
        $country = Country::findOrFail($id);
        
        $this->editingCountryId = $country->id;
        $this->name = $country->name;
        $this->iso2 = $country->iso2;
        $this->iso3 = $country->iso3 ?? '';

        $this->showEditModal = true;
    }

    public function saveCountry(): void
    {
        $this->authorize('countries.update');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:countries,name,' . $this->editingCountryId,
            'iso2' => 'required|string|size:2|unique:countries,iso2,' . $this->editingCountryId,
            'iso3' => 'nullable|string|size:3|unique:countries,iso3,' . $this->editingCountryId,
        ]);

        $validated['iso3'] = $validated['iso3'] ?: null;

        Country::findOrFail($this->editingCountryId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Country updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('countries.delete');
        $country = Country::findOrFail($id);
        $this->countryPendingDeleteId = $country->id;
        $this->countryPendingDeleteLabel = $country->name;
        $this->showDeleteModal = true;
    }

    public function deleteCountry(): void
    {
        $this->authorize('countries.delete');

        if ($this->countryPendingDeleteId) {
            $country = Country::findOrFail($this->countryPendingDeleteId);

            if ($country->states()->exists()) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete ":name" because it has associated states.', ['name' => $country->name]));
            } else {
                $country->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('Country deleted successfully.'));
            }
        }

        $this->countryPendingDeleteId = null;
        $this->countryPendingDeleteLabel = '';
    }

    public function openImportModal(): void
    {
        $this->authorize('countries.create');
        $this->authorize('countries.update');
        $this->reset('importFile');
        $this->showImportModal = true;
    }

    public function importCsv(): void
    {
        $this->authorize('countries.create');
        $this->authorize('countries.update');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = CsvImportReader::read($this->importFile->getRealPath());

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($parsed['rows'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $iso2 = strtoupper(trim((string) ($row['iso2'] ?? '')));
            $iso3 = strtoupper(trim((string) ($row['iso3'] ?? '')));

            if ($name === '' || strlen($iso2) !== 2 || ($iso3 !== '' && strlen($iso3) !== 3)) {
                $errors++;
                continue;
            }

            $existing = Country::query()->where('iso2', $iso2)->first();
            Country::query()->updateOrCreate(
                ['iso2' => $iso2],
                ['name' => $name, 'iso3' => $iso3 !== '' ? $iso3 : null]
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
            <x-crud.page-header :heading="__('Countries')" :subheading="__('Manage global countries and ISO codes.')" icon="flag" class="!mb-0" />
            <div class="flex items-center gap-2">
                @can('countries.create')
                    <flux:button variant="outline" icon="arrow-down-tray" wire:click="openImportModal">{{ __('Import CSV') }}</flux:button>
                    <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Country') }}</flux:button>
                @endcan
            </div>
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or ISO code...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->countries">
                <flux:table.columns>
                    <flux:table.column icon="flag">{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO2') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO3') }}</flux:table.column>
                    <flux:table.column>{{ __('States') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->countries as $country)
                        <flux:table.row :key="$country->id">
                            <flux:table.cell class="font-medium">{{ $country->name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $country->iso2 }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $country->iso3 ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="left">{{ $country->states_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('countries.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $country->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('countries.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $country->id }})">{{ __('Delete') }}</flux:menu.item>
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
        <form wire:submit="saveNewCountry" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="flag" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Country') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new country to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Country Name')" icon="flag" required placeholder="e.g. United States" />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="iso2" :label="__('ISO2 Code')" icon="qr-code" required placeholder="US" maxlength="2" class="uppercase font-mono" />
                    <flux:input wire:model="iso3" :label="__('ISO3 Code')" icon="qr-code" placeholder="USA" maxlength="3" class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Country') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveCountry" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Country') }}</flux:heading>
                    <flux:subheading>{{ __('Update country details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Country Name')" icon="flag" required />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="iso2" :label="__('ISO2 Code')" icon="qr-code" required maxlength="2" class="uppercase font-mono" />
                    <flux:input wire:model="iso3" :label="__('ISO3 Code')" icon="qr-code" maxlength="3" class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update Country') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteCountry" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Country') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $countryPendingDeleteLabel]) }}
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
                <flux:heading size="lg">{{ __('Import Countries CSV') }}</flux:heading>
                <flux:subheading>{{ __('Expected headers: name, iso2, iso3') }}</flux:subheading>
            </div>
            <div class="space-y-3">
                <input type="file" wire:model="importFile" accept=".csv,text/csv" class="block w-full text-sm" />
                <flux:error name="importFile" />
                <flux:link :href="route('import-templates.geo', 'countries')" wire:navigate="false">
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
