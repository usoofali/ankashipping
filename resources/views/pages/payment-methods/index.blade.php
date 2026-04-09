<?php

declare(strict_types=1);

use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payment Methods')] class extends Component {
    use WithPagination;
    use \WireUi\Traits\WireUiActions;

    public string $search = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    // Form fields
    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorize('payment_methods.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('paymentMethodsPage');
    }

    public function updatedName($value): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    #[Computed]
    public function paymentMethods()
    {
        return PaymentMethod::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                      ->orWhere('slug', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15, ['*'], 'paymentMethodsPage');
    }

    public function openCreateModal(): void
    {
        $this->authorize('payment_methods.create');
        $this->resetValidation();
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('payment_methods.update');
        $this->resetValidation();
        $paymentMethod = PaymentMethod::findOrFail($id);
        
        $this->editingId = $paymentMethod->id;
        $this->name = $paymentMethod->name;
        $this->slug = $paymentMethod->slug;
        
        $this->showFormModal = true;
    }

    public function savePaymentMethod(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:payment_methods,slug,' . ($this->editingId ?? 'NULL')],
        ]);

        if ($this->editingId) {
            $this->authorize('payment_methods.update');
            $paymentMethod = PaymentMethod::findOrFail($this->editingId);
            $paymentMethod->update([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
            $this->notification()->success('Payment Method updated successfully.');
        } else {
            $this->authorize('payment_methods.create');
            PaymentMethod::create([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
            $this->notification()->success('Payment Method created successfully.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('payment_methods.delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deletePaymentMethod(): void
    {
        $this->authorize('payment_methods.delete');
        $paymentMethod = PaymentMethod::findOrFail($this->deletingId);
        $paymentMethod->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->notification()->success('Payment Method deleted successfully.');
        $this->resetPage('paymentMethodsPage');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
    }


};
?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <x-crud.page-header :heading="__('Payment Methods')" :subheading="__('Manage transaction types for system payments.')" icon="credit-card" class="!mb-0" />
            
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search methods...')" clearable class="w-64" />
                @can('payment_methods.create')
                    <flux:button wire:click="openCreateModal" variant="primary" icon="plus">{{ __('Create Method') }}</flux:button>
                @endcan
            </div>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->paymentMethods">
                <flux:table.columns>
                    <flux:table.column icon="tag">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="link">{{ __('Identifier (Slug)') }}</flux:table.column>
                    <flux:table.column icon="clock">{{ __('Last Updated') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->paymentMethods as $method)
                        <flux:table.row :key="$method->id">
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $method->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm" inset="top bottom">{{ $method->slug }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-zinc-500 text-sm" title="{{ $method->updated_at }}">{{ $method->updated_at->diffForHumans() }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('payment_methods.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $method->id }})" wire:key="edit-open-{{ $method->id }}">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('payment_methods.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $method->id }})" wire:key="delete-open-{{ $method->id }}">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                                {{ __('No payment methods found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    <!-- Create/Edit Form Modal -->
    <flux:modal wire:model.self="showFormModal" class="md:w-[32rem]">
        <div class="mb-6 flex items-start gap-4 border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="rounded-xl bg-indigo-50 p-3 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400">
                <flux:icon.credit-card class="size-6" />
            </div>
            <div>
                <flux:heading size="lg" weight="semibold">{{ $editingId ? __('Edit Payment Method') : __('Create Payment Method') }}</flux:heading>
                <flux:subheading>{{ __('Define types of payments accepted by the system.') }}</flux:subheading>
            </div>
        </div>

        <form wire:submit="savePaymentMethod" class="space-y-6">
            <flux:input wire:model.live="name" id="name" :label="__('Method Name')" placeholder="e.g. Bank Transfer" required />
            
            <flux:input wire:model="slug" id="slug" :label="__('Slug')" placeholder="bank-transfer" required />

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Method') }}</flux:button>
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
                <flux:heading size="lg" weight="semibold">{{ __('Delete Payment Method') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to permanently delete this payment method?') }}</flux:subheading>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button wire:click="deletePaymentMethod" variant="danger">{{ __('Yes, Delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
