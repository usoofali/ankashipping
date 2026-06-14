<flux:modal wire:model="showInvoiceStatusConfirmModal" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Change invoice status') }}</flux:heading>
            <flux:subheading>
                @if($pendingInvoiceStatus)
                                @php
                                    $pendingToStatus = \App\Enums\InvoiceStatus::from($pendingInvoiceStatus);
                                    $fromStatusLabel = ($shipment->invoice?->status ?? $shipment->invoice_status)?->name ?? __('None');
                                @endphp
                                {{ __('Change from :from to :to?', [
                        'from' => $fromStatusLabel,
                        'to' => $pendingToStatus->name,
                    ]) }}
                @else
                    {{ __('Confirm the new invoice status for this shipment.') }}
                @endif
            </flux:subheading>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="confirmInvoiceStatusChange" wire:loading.attr="disabled">
                {{ __('Confirm') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="showAssignDriverModal" class="max-w-xl min-h-[40vh] max-h-[65vh]">
    <form wire:submit="assignDriver" class="space-auto-y">
        <div class="mb-1">
            <flux:heading size="lg">{{ __('Assign Driver') }}</flux:heading>
            <flux:subheading>
                @if($selectedVehicleId)
                    @php $v = \App\Models\Vehicle::find($selectedVehicleId); @endphp
                    {{ __('Assign driver specifically for :v (VIN: :vin)', ['v' => ($v?->year . ' ' . $v?->make), 'vin' => $v?->vin]) }}
                @else
                    {{ __('Select an existing driver or add a new one, then assign to this shipment.') }}
                @endif
            </flux:subheading>
        </div>
        @can('drivers.create')
            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" icon="plus" wire:click="openCreateDriverModal">
                    {{ __('Add New Driver') }}
                </flux:button>
            </div>
        @endcan
        <div class="space-y-3">
            <x-select wire:model.live="driver_id" name="driver_id" :label="__('Driver')" :placeholder="__('Search and select driver')" option-value="id" option-label="name" :async-data="route('api.drivers.search')"
                searchable required />
            <flux:error name="driver_id" />

        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Assign Driver') }}</flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal wire:model="showCreateDriverModal" class="md:max-w-2xl">
    <form wire:submit="createDriver" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Create Driver') }}</flux:heading>
            <flux:subheading>{{ __('Add a driver and auto-select it for assignment.') }}</flux:subheading>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="new_driver_company" :label="__('Company')" icon="building-office"
                placeholder="e.g. Danmazari Transport LTD" />
            <flux:input wire:model="new_driver_phone" :label="__('Phone')" required mask="+1 (999) 999-9999"
                placeholder="+1 (234) 567-8901" />
            <flux:input wire:model="new_driver_email" :label="__('Email')" icon="envelope" type="email"
                placeholder="driver@example.com" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save Driver') }}</flux:button>
        </div>
    </form>
</flux:modal>

@can('documents.manage')
    <flux:modal wire:model.self="showAttachDocumentModal" class="max-w-lg md:w-[36rem]">
        <form wire:submit="saveAttachedDocuments" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Attach Shipping Document') }}</flux:heading>
                <flux:subheading>{{ __('Select type, add files, and optional notes.') }}</flux:subheading>
            </div>

            <flux:select wire:model.live="attachDocumentType" :label="__('Document type')" required>
                <flux:select.option value="">{{ __('Choose type…') }}</flux:select.option>
                @foreach($this->workflow()->allowedShipmentDocumentTypes($shipment) as $typeValue)
                    @php $type = \App\Enums\ShipmentDocumentType::from($typeValue); @endphp
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="attachDocumentType" />

            <flux:textarea wire:model="attachDocumentNotes" :label="__('Notes (optional)')" rows="2" />

            <div>
                <flux:text class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Files') }}
                </flux:text>
                <input type="file" wire:model="attachFiles" multiple
                    class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-zinc-400 dark:file:bg-indigo-950 dark:file:text-indigo-300" />
                <flux:error name="attachFiles" />
                <flux:error name="attachFiles.*" />
                <div wire:loading wire:target="attachFiles" class="mt-1 text-xs text-zinc-500">{{ __('Uploading…') }}
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
@endcan

@can('documents.manage')
    <flux:modal wire:model.self="showAttachVehicleDocumentModal" class="max-w-lg md:w-[36rem]">
        <form wire:submit="saveAttachedVehicleDocuments" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Attach Vehicle Document') }}</flux:heading>
                @if($selectedVehicleId)
                    @php $selectedV = $shipment->vehicles->firstWhere('id', $selectedVehicleId); @endphp
                    <flux:subheading>
                        {{ $selectedV?->year }} {{ $selectedV?->make }} {{ $selectedV?->model }}
                        <span class="font-mono text-xs text-zinc-500">({{ $selectedV?->vin }})</span>
                    </flux:subheading>
                @endif
            </div>

            <flux:select wire:model.live="attachVehicleDocumentType" :label="__('Document type')" required>
                <flux:select.option value="">{{ __('Choose type…') }}</flux:select.option>
                @if($selectedVehicleId)
                    @php $selectedV = $shipment->vehicles->firstWhere('id', $selectedVehicleId); @endphp
                    @if($selectedV)
                        @foreach($this->workflow()->allowedVehicleDocumentTypes($shipment, $selectedV) as $typeValue)
                            @php $type = \App\Enums\VehicleDocumentType::from($typeValue); @endphp
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    @endif
                @endif
            </flux:select>
            <flux:error name="attachVehicleDocumentType" />

            @if($attachVehicleDocumentType === \App\Enums\VehicleDocumentType::TitleDocument->value)
                <flux:radio.group wire:model="attachVehicleTitleVehicleIs" :label="__('Vehicle condition')" variant="segmented">
                    @foreach(\App\Enums\VehicleIs::cases() as $v)
                        <flux:radio :value="$v->value" :label="$v->label()" />
                    @endforeach
                </flux:radio.group>
                <flux:error name="attachVehicleTitleVehicleIs" />
            @endif

            <flux:textarea wire:model="attachVehicleDocumentNotes" :label="__('Notes (optional)')" rows="2" />

            <div>
                <flux:text class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Files') }}
                </flux:text>
                <input type="file" wire:model="attachVehicleFiles" multiple
                    class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-zinc-400 dark:file:bg-indigo-950 dark:file:text-indigo-300" />
                <flux:error name="attachVehicleFiles" />
                <flux:error name="attachVehicleFiles.*" />
                <div wire:loading wire:target="attachVehicleFiles" class="mt-1 text-xs text-zinc-500">
                    {{ __('Uploading…') }}
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
@endcan

@can('shipments.update')
    @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('Super Manager') || auth()->user()?->staff()->exists())
        <flux:modal wire:model.self="showToWorkshopModal" class="max-w-md">
            <form wire:submit="saveToWorkshop" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Send to workshop') }}</flux:heading>
                    <flux:subheading>
                        @if($selectedVehicleId)
                            @php $v = \App\Models\Vehicle::find($selectedVehicleId); @endphp
                            {{ __('Send :v (VIN: :vin) to workshop.', ['v' => ($v?->year . ' ' . $v?->make), 'vin' => $v?->vin]) }}
                        @else
                            {{ __('Choose the workshop. Current status will be saved and restored when you use “From workshop”.') }}
                        @endif
                    </flux:subheading>
                </div>
                <flux:select wire:model="toWorkshopWorkshopId" :label="__('Workshop')" required>
                    <flux:select.option value="">{{ __('Select workshop…') }}</flux:select.option>
                    @foreach($this->workshopsForSelect as $w)
                        <flux:select.option value="{{ $w->id }}">{{ $w->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="toWorkshopWorkshopId" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model.self="showFromWorkshopConfirmModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Return from workshop') }}</flux:heading>
                <flux:subheading>{{ __('Restore the shipment to its previous status and clear the workshop assignment.') }}
                </flux:subheading>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="fromWorkshop">{{ __('Confirm') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
@endcan

@if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('Super Manager') || auth()->user()?->staff()->exists())
    <flux:modal wire:model.self="showDeleteDocumentConfirmModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Remove attachment?') }}</flux:heading>
            <flux:subheading>{{ __('This deletes all files in this group from storage. This cannot be undone.') }}
            </flux:subheading>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteShipmentDocumentConfirmed">{{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteFileConfirmModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Remove file?') }}</flux:heading>
            <flux:subheading>
                {{ __('The file will be deleted from storage. If it was the last file, the attachment group is removed.') }}
            </flux:subheading>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteShipmentDocumentFileConfirmed">{{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
@endif

<flux:modal wire:model="showShipmentDocumentsModal" class="max-w-3xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Shipping Documents') }}</flux:heading>
            <flux:subheading>
                {{ $shipment->tracking_number ?: __('No tracking #') }} ·
                {{ $shipment->shipping_mode?->name ?? __('Unknown Mode') }}
            </flux:subheading>
        </div>

        @php
            $isStaffOrAdmin = auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('Super Manager') || auth()->user()?->staff()->exists();
            $documents = $shipment->documents()->with(['files.uploader'])->get()->sortByDesc(fn($d) => $d->created_at?->timestamp ?? 0);
        @endphp

        <div class="space-y-8 max-h-[65vh] overflow-y-auto pr-2 custom-scrollbar">
            {{-- Auction Receipt (Pinned) --}}
            @if($shipment->auction_receipt)
                <div class="space-y-3">
                    <flux:heading size="sm" class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                        <flux:icon.star class="size-4" />
                        {{ __('Priority Document') }}
                    </flux:heading>
                    <div
                        class="flex items-center gap-4 p-4 rounded-2xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-900/10">
                        <div
                            class="shrink-0 size-14 rounded-xl bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center border border-indigo-200 dark:border-indigo-800">
                            <flux:icon.document-arrow-down class="size-7 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:text size="sm" class="font-bold text-zinc-900 dark:text-zinc-100">
                                {{ __('Auction receipt') }}
                            </flux:text>
                            <flux:text size="xs" class="text-zinc-500 font-mono truncate"
                                :title="$shipment->auction_receipt">
                                {{ \Illuminate\Support\Str::limit($shipment->auction_receipt, 15) }}
                            </flux:text>
                        </div>
                    </div>
                </div>
            @endif

            @if($documents->isEmpty() && !$shipment->auction_receipt)
                <div
                    class="flex flex-col items-center justify-center py-20 bg-zinc-50 dark:bg-zinc-900/50 rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800">
                    <flux:icon.document-text class="size-16 text-zinc-300 dark:text-zinc-700 mb-4" />
                    <flux:text class="text-zinc-500 font-medium">{{ __('No documents attached yet.') }}</flux:text>
                </div>
            @else
                @foreach($documents as $doc)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="size-2 rounded-full bg-indigo-500"></div>
                                <flux:heading size="sm" class="uppercase tracking-wider font-bold text-zinc-500">
                                    {{ $doc->document_type?->label() ?? __('Other') }}
                                </flux:heading>
                            </div>
                            @if($isStaffOrAdmin)
                                <flux:button type="button" variant="ghost" size="sm" icon="trash"
                                    class="text-red-600 dark:text-red-400" wire:click="openDeleteDocumentConfirm({{ $doc->id }})"
                                    wire:key="del-ship-doc-{{ $doc->id }}" />
                            @endif
                        </div>

                        @if(filled($doc->notes))
                            <flux:text size="xs" class="text-zinc-500 -mt-2 block italic">
                                "{{ $doc->notes }}"
                            </flux:text>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($doc->files as $file)
                                @php
                                    $ext = strtolower(pathinfo($file->original_name ?? $file->path, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                                @endphp
                                <div
                                    class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 group hover:border-indigo-500/50 hover:shadow-lg hover:shadow-indigo-500/5 transition-all duration-200">
                                    <div
                                        class="shrink-0 size-14 rounded-xl bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center overflow-hidden border border-zinc-100 dark:border-zinc-700 group-hover:scale-105 transition-transform">
                                        @if($isImage)
                                            <flux:icon.photo class="size-7 text-indigo-500/70" />
                                        @elseif($ext === 'pdf')
                                            <flux:icon.document-text class="size-7 text-red-500/70" />
                                        @else
                                            <flux:icon.document class="size-7 text-zinc-400" />
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <flux:text size="sm"
                                            class="font-bold text-zinc-900 dark:text-zinc-100 truncate pr-2 group-hover:text-indigo-600 transition-colors"
                                            :title="$file->original_name ?? basename($file->path)">
                                            {{ \Illuminate\Support\Str::limit($file->original_name ?? basename($file->path), 30) }}
                                        </flux:text>
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
                                            <flux:text size="xs"
                                                class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-mono uppercase tracking-tighter">
                                                {{ $ext ?: '???' }}
                                            </flux:text>
                                            <flux:text size="xs" class="text-zinc-400">
                                                {{ $file->created_at->format('M d, Y') }}
                                            </flux:text>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray"
                                            class="shrink-0 text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                                            :href="\App\Support\ShipmentDocumentSignedDownloadUrl::for($shipment, $file)" />
                                        @if($isStaffOrAdmin)
                                            <flux:button type="button" size="sm" variant="ghost" icon="trash"
                                                class="text-red-600 dark:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                                                wire:click="openDeleteFileConfirm({{ $file->id }})"
                                                wire:key="del-ship-file-{{ $file->id }}" />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Close') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="showVehicleDocumentsModal" class="max-w-3xl">
    @if($selectedVehicleIdForDocs)
        @php
            $v = $shipment->vehicles->firstWhere('id', $selectedVehicleIdForDocs);
            $vDocs = $v?->vehicleDocuments()->with(['files.uploader'])->get() ?? collect();
        @endphp
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Vehicle Documents') }}</flux:heading>
                <flux:subheading>
                    @if($v)
                        {{ $v->year }} {{ $v->make }} {{ $v->model }} · {{ $v->vin }}
                    @endif
                </flux:subheading>
            </div>

            @if($vDocs->isEmpty() && !$v?->auction_receipt)
                <div
                    class="flex flex-col items-center justify-center py-20 bg-zinc-50 dark:bg-zinc-900/50 rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800">
                    <flux:icon.document-text class="size-16 text-zinc-300 dark:text-zinc-700 mb-4" />
                    <flux:text class="text-zinc-500 font-medium">{{ __('No documents attached to this vehicle.') }}
                    </flux:text>
                </div>
            @else
                <div class="space-y-8 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    {{-- Vehicle Auction Receipt (Pinned) --}}
                    @if($v?->auction_receipt)
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
                                <flux:icon.star class="size-4" />
                                <flux:heading size="sm" class="uppercase tracking-wider font-bold">
                                    {{ __('Priority Document') }}
                                </flux:heading>
                            </div>
                            @php
                                $receiptPath = $v->auction_receipt;
                                $ext = strtolower(pathinfo($receiptPath, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                            @endphp
                            <div
                                class="flex items-center gap-4 p-4 rounded-2xl border border-primary-200 dark:border-primary-900/50 bg-primary-50/30 dark:bg-primary-900/10 group hover:border-primary-500/50 transition-all">
                                <div
                                    class="shrink-0 size-14 rounded-xl bg-primary-100 dark:bg-primary-900 flex items-center justify-center border border-primary-200 dark:border-primary-800">
                                    @if($isImage)
                                        <flux:icon.photo class="size-7 text-primary-600 dark:text-primary-400" />
                                    @elseif($ext === 'pdf')
                                        <flux:icon.document-text class="size-7 text-red-500/70" />
                                    @else
                                        <flux:icon.document class="size-7 text-primary-400" />
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <flux:text size="sm" class="font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ __('Auction receipt') }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-zinc-500 font-mono truncate" :title="basename($receiptPath)">
                                        {{ \Illuminate\Support\Str::limit(basename($receiptPath), 15) }}
                                    </flux:text>
                                </div>
                                <flux:button variant="ghost" size="sm" icon="arrow-down-tray"
                                    class="shrink-0 text-zinc-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                                    target="_blank" :href="Storage::url($receiptPath)" />
                            </div>
                        </div>
                    @endif
                    @foreach($vDocs as $doc)
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <div class="size-2 rounded-full bg-primary-500"></div>
                                <flux:heading size="sm" class="uppercase tracking-wider font-bold text-zinc-500">
                                    {{ $doc->document_type?->label() ?? __('Other') }}
                                </flux:heading>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($doc->files as $file)
                                    @php
                                        $ext = strtolower(pathinfo($file->original_name ?? $file->path, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                                    @endphp
                                    <div
                                        class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 group hover:border-primary-500/50 hover:shadow-lg hover:shadow-primary-500/5 transition-all duration-200">
                                        <div
                                            class="shrink-0 size-14 rounded-xl bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center overflow-hidden border border-zinc-100 dark:border-zinc-700 group-hover:scale-105 transition-transform">
                                            @if($isImage)
                                                <flux:icon.photo class="size-7 text-primary-500/70" />
                                            @elseif($ext === 'pdf')
                                                <flux:icon.document-text class="size-7 text-red-500/70" />
                                            @else
                                                <flux:icon.document class="size-7 text-zinc-400" />
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <flux:text size="sm"
                                                class="font-bold text-zinc-900 dark:text-zinc-100 truncate pr-2 group-hover:text-primary-600 transition-colors"
                                                :title="$file->original_name ?? basename($file->path)">
                                                {{ \Illuminate\Support\Str::limit($file->original_name ?? basename($file->path), 30) }}
                                            </flux:text>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
                                                <flux:text size="xs"
                                                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-mono uppercase tracking-tighter">
                                                    {{ $ext ?: '???' }}
                                                </flux:text>
                                                <flux:text size="xs" class="text-zinc-400">
                                                    {{ $file->created_at->format('M d, Y') }}
                                                </flux:text>
                                            </div>
                                        </div>

                                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray"
                                            class="shrink-0 text-zinc-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                                            :href="\App\Support\VehicleDocumentFileSignedDownloadUrl::for($v, $file)" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    @endif
</flux:modal>

<flux:modal wire:model.self="showForceFillConfirmModal" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Force Fill Container') }}</flux:heading>
            <flux:subheading>
                {{ __('Are you sure you want to force mark this container as filled? This bypasses the minimum vehicle requirement.') }}
            </flux:subheading>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="markContainerFilled(true)" wire:loading.attr="disabled">
                {{ __('Confirm Force Fill') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="showLogisticsModal" class="md:max-w-4xl" variant="flyout">
    <form wire:submit="saveLogistics" class="space-y-8">
        <div>
            <flux:heading size="lg">{{ __('Logistics & Booking Details') }}</flux:heading>
            <flux:subheading>{{ __('Manage shipment-level identifiers and individual vehicle metrics.') }}
            </flux:subheading>
        </div>

        <div
            class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-4">
            <div class="flex items-center gap-2">
                <flux:icon.building-office class="size-5 text-zinc-400" />
                <flux:heading size="sm" class="font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-tight">
                    {{ __('Exporter Details') }}
                </flux:heading>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="logisticsForm.shipment.exporter_name" label="{{ __('Exporter Name') }}" required
                    icon="user" />
                <flux:input wire:model="logisticsForm.shipment.exporter_address" label="{{ __('Exporter Address') }}"
                    required icon="map-pin" />
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:input wire:model="logisticsForm.shipment.exporter_state" label="{{ __('State') }}" />
                    <flux:input wire:model="logisticsForm.shipment.exporter_country" label="{{ __('Country') }}" />
                    <flux:input wire:model="logisticsForm.shipment.exporter_zipcode" label="{{ __('Zipcode') }}" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Shipment Fields -->
            <flux:input wire:model="logisticsForm.shipment.vessel_name" label="{{ __('Vessel Name') }}" icon="ship" />
            <flux:input wire:model="logisticsForm.shipment.voyage_no" label="{{ __('Voyage #') }}" icon="hashtag" />
            <flux:input wire:model="logisticsForm.shipment.itn_number" label="{{ __('AES ITN #') }}"
                icon="finger-print" />

            <flux:input wire:model="logisticsForm.shipment.booking_number" label="{{ __('Booking #') }}"
                icon="hashtag" />
            <flux:input wire:model="logisticsForm.shipment.bill_of_lading_number" label="{{ __('BL #') }}"
                icon="document-text" />
            <flux:input wire:model="logisticsForm.shipment.container_no" label="{{ __('Container #') }}"
                icon="square-3-stack-3d" />

            <flux:input wire:model="logisticsForm.shipment.seal_no" label="{{ __('Seal #') }}" icon="lock-closed" />
            <flux:input wire:model="logisticsForm.shipment.container_type" label="{{ __('Container Type') }}"
                placeholder="e.g. 40' HIGH CUBE" />
            <flux:input type="date" wire:model="logisticsForm.shipment.cut_off_date" label="{{ __('Cut-off Date') }}" />

            <flux:input type="date" wire:model="logisticsForm.shipment.departure_date"
                label="{{ __('ETD (Departure)') }}" />
            <flux:input type="date" wire:model="logisticsForm.shipment.arrival_date"
                label="{{ __('ETA (Arrival)') }}" />
            <flux:input wire:model="logisticsForm.shipment.loading_pier" label="{{ __('Loading Pier(Terminal)') }}" />
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
            <div class="space-y-4">
                @foreach($shipment->vehicles as $v)
                    <div wire:key="cond-v-{{ $v->id }}">
                        <flux:radio.group wire:model="logisticsForm.vehicles.{{ $v->id }}.vehicle_is"
                            :label="$shipment->vehicles->count() > 1 ? __('Condition for :make :model (:vin)', ['make' => $v->make, 'model' => $v->model, 'vin' => substr($v->vin ?? '—', -6)]) : __('Vehicle condition')"
                            variant="segmented">
                            @foreach(\App\Enums\VehicleIs::cases() as $vIs)
                                <flux:radio :value="$vIs->value" :label="$vIs->label()" />
                            @endforeach
                        </flux:radio.group>
                        <flux:error name="logisticsForm.vehicles.{{ $v->id }}.vehicle_is" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-6 mt-4">
            <flux:heading size="md" class="mb-4">{{ __('Vehicle Measurements (Schedule B)') }}</flux:heading>
            <div class="space-y-4">
                @foreach($shipment->vehicles as $v)
                    <div wire:key="log-v-{{ $v->id }}"
                        class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800 grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div class="md:col-span-1">
                            <flux:text size="sm" class="font-bold truncate"
                                title="{{ $v->year }} {{ $v->make }} {{ $v->model }}">
                                {{ $v->year ?? '—' }} {{ $v->make ?? '—' }}
                            </flux:text>
                            <flux:text size="xs" class="font-mono text-zinc-500 uppercase">
                                {{ substr($v->vin ?? '—', -6) }}
                            </flux:text>
                        </div>
                        <flux:input type="number" step="0.01" wire:model="logisticsForm.vehicles.{{ $v->id }}.value"
                            label="{{ __('Value ($)') }}" />
                        <div class="flex gap-2 items-end md:col-span-full">
                            <flux:input type="number" step="0.01" wire:model="logisticsForm.vehicles.{{ $v->id }}.weight"
                                label="{{ __('Weight') }}" class="flex-1" />
                            <flux:select wire:model="logisticsForm.vehicles.{{ $v->id }}.weight_unit" class="w-20">
                                <flux:select.option value="KG">KG</flux:select.option>
                                <flux:select.option value="LBS">LBS</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="flex gap-2 items-end md:col-span-full">
                            <flux:input type="number" step="0.01"
                                wire:model="logisticsForm.vehicles.{{ $v->id }}.measurement" label="{{ __('Measure') }}"
                                class="flex-1" />
                            <flux:select wire:model="logisticsForm.vehicles.{{ $v->id }}.measurement_unit" class="w-24">
                                <flux:select.option value="CBM">CBM</flux:select.option>
                                <flux:select.option value="CFT">CFT</flux:select.option>
                            </flux:select>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="saveLogistics(true)" variant="outline" wire:loading.attr="disabled">
                {{ __('Save as Draft') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save Logistics') }}
            </flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal wire:model.self="showDeleteShipmentConfirmModal" class="max-w-md">
    <form wire:submit="deleteShipment" class="space-y-6">
        <div>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">
                {{ __('Permanently Delete Shipment?') }}
            </flux:heading>
            <flux:subheading>
                {{ __('This action is permanent and cannot be undone. All invoices, documents, and logs will be deleted. Associated vehicles will be detached.') }}
            </flux:subheading>
        </div>

        <div class="space-y-3">
            <flux:text size="sm">
                {{ __('Please type') }} <span
                    class="font-mono font-bold text-zinc-900 dark:text-zinc-100 select-all">{{ $shipment->reference_no }}</span>
                {{ __('to confirm.') }}
            </flux:text>
            <flux:input wire:model.live="deleteConfirmationReference" :placeholder="$shipment->reference_no" required />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger" icon="trash"
                :disabled="$deleteConfirmationReference !== $shipment->reference_no" wire:loading.attr="disabled">
                {{ __('Permanently Delete') }}
            </flux:button>
        </div>
    </form>
</flux:modal>