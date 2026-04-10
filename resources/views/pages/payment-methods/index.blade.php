<?php

declare(strict_types=1);

use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Payment Methods')] class extends Component {
    use WireUiActions;
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    // Form fields
    public ?int $editingPaymentMethodId = null;
    public string $name = '';
    public string $slug = '';
    public ?int $paymentMethodPendingDeleteId = null;
    public string $paymentMethodPendingDeleteLabel = '';

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
        if (!$this->editingPaymentMethodId) {
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
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('payment_methods.update');
        $this->resetValidation();
        $paymentMethod = PaymentMethod::findOrFail($id);

        $this->editingPaymentMethodId = $paymentMethod->id;
        $this->name = $paymentMethod->name;
        $this->slug = $paymentMethod->slug;

        $this->showEditModal = true;
    }

    public function savePaymentMethod(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:payment_methods,slug,' . ($this->editingPaymentMethodId ?? 'NULL')],
        ]);

        if ($this->editingPaymentMethodId) {
            $this->authorize('payment_methods.update');
            $paymentMethod = PaymentMethod::findOrFail($this->editingPaymentMethodId);
            $paymentMethod->update([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
            $this->showEditModal = false;
            $this->notification()->success(__('Payment method updated successfully.'));
        } else {
            $this->authorize('payment_methods.create');
            PaymentMethod::create([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
            $this->showCreateModal = false;
            $this->notification()->success(__('Payment method created successfully.'));
        }

        $this->resetForm();
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('payment_methods.delete');
        $paymentMethod = PaymentMethod::findOrFail($id);
        $this->paymentMethodPendingDeleteId = $paymentMethod->id;
        $this->paymentMethodPendingDeleteLabel = $paymentMethod->name;
        $this->showDeleteModal = true;
    }

    public function deletePaymentMethod(): void
    {
        $this->authorize('payment_methods.delete');

        if ($this->paymentMethodPendingDeleteId) {
            $paymentMethod = PaymentMethod::findOrFail($this->paymentMethodPendingDeleteId);
            $paymentMethod->delete();

            $this->showDeleteModal = false;
            $this->notification()->success(__('Payment method deleted successfully.'));
            $this->resetPage('paymentMethodsPage');
        }

        $this->paymentMethodPendingDeleteId = null;
        $this->paymentMethodPendingDeleteLabel = '';
    }

    private function resetForm(): void
    {
        $this->editingPaymentMethodId = null;
        $this->name = '';
        $this->slug = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
            <x-crud.page-header :heading="__('Payment Methods')" :subheading="__('Manage payment options.')"
                icon="credit-card" class="!mb-0" />
            @can('payment_methods.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Method') }}
                </flux:button>
            @endcan
        </div>

        <div class="mb-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search methods...')" clearable />
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
                            <flux:table.cell>{{ $method->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm" inset="top bottom">{{ $method->slug }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                <span title="{{ $method->updated_at }}">{{ $method->updated_at->diffForHumans() }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('payment_methods.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $method->id }})">
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('payment_methods.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger"
                                                wire:click="openDeleteModal({{ $method->id }})">{{ __('Delete') }}
                                            </flux:menu.item>
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

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="savePaymentMethod" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="credit-card" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Payment Method') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new payment method to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model.live="name" :label="__('Method Name')" placeholder="e.g. Bank Transfer"
                    required />
                <flux:input wire:model="slug" :label="__('Slug')" placeholder="bank-transfer" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Payment Method') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="savePaymentMethod" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Payment Method') }}</flux:heading>
                    <flux:subheading>{{ __('Update payment method details.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model.live="name" :label="__('Method Name')" placeholder="e.g. Bank Transfer"
                    required />
                <flux:input wire:model="slug" :label="__('Slug')" placeholder="bank-transfer" required />
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
        <form wire:submit="deletePaymentMethod" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Payment Method') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $this->paymentMethodPendingDeleteLabel]) }}
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