@if($this->workflow()->canViewInvoice($shipment, auth()->user()))
    <x-crud.panel class="p-6 bg-zinc-50 dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.receipt-percent class="size-5 text-indigo-500" />
                    {{ __('Invoice') }}
                </flux:heading>
                <flux:text size="sm" class="text-zinc-500 mt-1">
                    {{ $shipment->invoice?->invoice_number ?? __('No invoice number assigned yet.') }}
                </flux:text>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                @php
                    $effectiveInvoiceStatus = $shipment->invoice?->status ?? $shipment->invoice_status;
                @endphp
                <div class="text-right">
                    @if($effectiveInvoiceStatus)
                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1 block">
                            {{ __('Status') }}
                        </flux:text>
                        <flux:badge color="amber" variant="subtle" size="sm" icon="document-text">
                            {{ $effectiveInvoiceStatus->name }}
                        </flux:badge>
                    @else
                        <flux:text size="xs" class="text-zinc-500">
                            {{ __('No invoice status') }}
                        </flux:text>
                    @endif
                </div>
                @php
                    $user = auth()->user();
                    $canClear = $this->workflow()->canClearInvoice($shipment, $user);
                    $canComplete = $this->workflow()->canCompleteInvoice($shipment, $user);
                    // Show dropdown if user can transition to any status or is super_admin
                    $showDropdown = $user->hasRole('super_admin') || $canClear || $canComplete;
                @endphp

                @if($showDropdown && !$shipment->isLocked())
                    <flux:dropdown align="end" position="bottom">
                        <flux:button icon="ellipsis-vertical" size="sm" variant="ghost" />
                        <flux:menu>
                            @foreach(\App\Enums\InvoiceStatus::cases() as $status)
                                @php
                                    $currentStatus = $shipment->invoice?->status?->value ?? $shipment->invoice_status?->value;
                                    $isAllowed = match ($status) {
                                        \App\Enums\InvoiceStatus::Draft => false, // Cannot go back to draft from UI currently
                                        \App\Enums\InvoiceStatus::Cleared => $canClear,
                                        \App\Enums\InvoiceStatus::Completed => $canComplete,
                                    } || $user->hasRole('super_admin');
                                @endphp

                                @if($isAllowed && $currentStatus !== $status->value)
                                    <flux:menu.item wire:click="openInvoiceStatusConfirm('{{ $status->value }}')">
                                        {{ $status->name }}
                                    </flux:menu.item>
                                @endif
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        </div>

        <div class="mb-1">
            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                {{ __('Total') }}
            </flux:text>
            <flux:text class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                {{ '$' . number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
            </flux:text>
        </div>

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden mb-4">
            <div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-2 flex items-center justify-between">
                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-500">
                    {{ __('Invoice Items') }}
                </flux:text>
            </div>
            <div class="max-h-56 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($shipment->invoice?->items ?? collect() as $item)
                    <div class="px-3 py-2 flex items-center justify-between gap-3">
                        <div class="flex-1">
                            <flux:text size="sm" class="font-medium">
                                {{ $item->description }}
                            </flux:text>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col items-end gap-0.5">
                                @if((float) $item->discount_amount > 0)
                                    <flux:text size="xs" class="text-zinc-500 line-through font-mono">
                                        {{ '$' . number_format((float) $item->gross_amount, 2) }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400">
                                        −{{ '$' . number_format((float) $item->discount_amount, 2) }}
                                    </flux:text>
                                @endif
                                <flux:text size="sm" class="font-mono font-semibold">
                                    {{ '$' . number_format((float) $item->amount, 2) }}
                                </flux:text>
                            </div>
                            @if($this->workflow()->canEditInvoice($shipment, auth()->user()))
                                <div class="flex items-center gap-2">
                                    <flux:button icon="pencil-square" size="xs" variant="ghost"
                                        wire:click="editItem({{ $item->id }})" />
                                    <flux:button icon="trash" size="xs" variant="ghost"
                                        class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-950/40"
                                        wire:click="deleteItem({{ $item->id }})" />
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-3 py-4">
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('No invoice items yet. Add the first charge below.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </div>

        @if($this->workflow()->canEditInvoice($shipment, auth()->user()))
            <form wire:submit.prevent="addOrUpdateItem" class="space-y-3">
                @if($shipment->isContainer())
                    <flux:select wire:model="invoice_vehicle_id" label="{{ __('Vehicle (Optional)') }}" icon="car-front">
                        <flux:select.option value="">{{ __('Container') }}</flux:select.option>
                        @foreach($shipment->vehicles as $v)
                            <flux:select.option :value="$v->id">
                                {{ $v->year ?: '' }} {{ $v->make ?: '' }} {{ $v->model ?: '' }}
                                ({{ substr($v->vin ?: '—', -6) }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model.live="item_description" label="{{ __('Invoice item') }}" icon="document-text">
                    <flux:select.option value="">{{ __('Select invoice item') }}</flux:select.option>
                    @foreach(\App\Models\ChargeItem::query()->whereNotNull('item')->orderBy('item')->get() as $chargeItem)
                        <flux:select.option :value="$chargeItem->item">
                            {{ $chargeItem->item }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" min="0" step="0.01" wire:model="item_amount" :label="__('Amount')"
                    icon="currency-dollar" :readonly="$this->invoiceItemAmountReadonly" />
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" icon="plus-circle" class="flex-1">
                        {{ $invoiceItemId ? __('Update Item') : __('Add Item') }}
                    </flux:button>
                    @if($invoiceItemId)
                        <flux:button type="button" variant="ghost" class="flex-none" wire:click="$set('invoiceItemId', null)">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif
                </div>
            </form>
        @endif
    </x-crud.panel>
@endif