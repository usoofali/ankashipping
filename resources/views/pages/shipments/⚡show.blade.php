<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleIs;
use App\Enums\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\ChargeItem;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentFile;
use App\Services\Invoice\InvoiceLineAmountResolver;
use App\Models\ShipmentTracking;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Notifications\ShipmentDispatchedNotification;
use App\Notifications\ShipmentDocumentAttachedNotification;
use App\Support\ShipmentActivityLogPresenter;
use App\Support\ShipmentTrackingPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;
use WireUi\Traits\WireUiActions;

new #[Title('Shipment Details')] class extends Component {
    use WireUiActions;
    use WithFileUploads;

    public function workflow(): \App\ShippingWorkflow\ShippingWorkflow
    {
        return app(\App\ShippingWorkflow\ShippingWorkflow::class);
    }

    public Shipment $shipment;

    /** Invoice item form state */
    public ?int $invoiceItemId = null;
    public string $item_description = '';
    public string $item_amount = '0.00';
    public ?int $invoice_vehicle_id = null;

    public bool $showInvoiceStatusConfirmModal = false;

    public ?string $pendingInvoiceStatus = null;

    /** Driver assignment state */
    public bool $showAssignDriverModal = false;
    public bool $showCreateDriverModal = false;
    public ?int $driver_id = null;
    public string $new_driver_company = '';
    public string $new_driver_phone = '';
    public string $new_driver_email = '';

    /** Shipment-level documents */
    public bool $showAttachDocumentModal = false;

    public string $attachDocumentType = '';

    public string $attachDocumentNotes = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachFiles = [];

    public string $attachTitleVehicleIs = 'runner';

    /** Vehicle-level documents */
    public bool $showAttachVehicleDocumentModal = false;

    public string $attachVehicleDocumentType = '';

    public string $attachVehicleDocumentNotes = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachVehicleFiles = [];

    public string $attachVehicleTitleVehicleIs = 'runner';

    public bool $showToWorkshopModal = false;

    public ?int $toWorkshopWorkshopId = null;

    public bool $showFromWorkshopConfirmModal = false;

    public bool $showDeleteDocumentConfirmModal = false;

    public ?int $pendingDeleteShipmentDocumentId = null;

    public bool $showVehicleDocumentsModal = false;

    public ?int $selectedVehicleIdForDocs = null;

    public bool $showShipmentDocumentsModal = false;

    public function openShipmentDocumentsModal(): void
    {
        $this->showShipmentDocumentsModal = true;
    }

    public function openVehicleDocumentsModal(int $vehicleId): void
    {
        $this->selectedVehicleIdForDocs = $vehicleId;
        $this->showVehicleDocumentsModal = true;
    }

    public bool $showDeleteFileConfirmModal = false;

    public ?int $pendingDeleteShipmentDocumentFileId = null;

    /** Multi-vehicle selection for documents/tracking */
    public ?int $selectedVehicleId = null;
    public bool $applyToAllVehicles = false;

    /** Logistics Modal state */
    public bool $showLogisticsModal = false;
    public array $logisticsForm = [
        'shipment' => [],
        'vehicles' => [],
    ];

    public function mount(Shipment $shipment): void
    {
        $this->shipment = $shipment->load([
            'shipper',
            'shipper.user',
            'consignee',
            'vehicles.trackings' => static fn($query) => $query->orderByDesc('recorded_at'),
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'carrier',
            'paymentMethod',
            'driver',
            'workshop',
            'invoice.items',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn($query) => $query->orderByDesc('recorded_at'),
        ]);
    }

    public function updatedShowInvoiceStatusConfirmModal(bool $value): void
    {
        if (!$value) {
            $this->pendingInvoiceStatus = null;
        }
    }

    protected function getInvoice(): Invoice
    {
        if ($this->shipment->invoice) {
            return $this->shipment->invoice;
        }

        /** @var Invoice $invoice */
        $invoice = $this->shipment->invoice()->create([
            'invoice_number' => 'INV-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => $this->shipment->invoice_status?->value ?? InvoiceStatus::Draft->value,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);

        $this->shipment->setRelation('invoice', $invoice->load('items'));

        return $invoice;
    }

    public function updatedItemDescription(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->item_amount = '0.00';

            return;
        }

        $chargeItem = ChargeItem::query()->where('item', $value)->first();
        if ($chargeItem === null) {
            return;
        }

        $this->shipment->loadMissing('shipper');
        $resolver = app(InvoiceLineAmountResolver::class);

        if ($chargeItem->apply_customer_discount) {
            $resolved = $resolver->resolveDiscountLine($chargeItem, $this->shipment->shipper);
            $this->item_amount = $resolved['net'];
        } else {
            $this->item_amount = number_format((float) $chargeItem->default_amount, 2, '.', '');
        }
    }

    #[Computed]
    public function invoiceItemAmountReadonly(): bool
    {
        if ($this->item_description === '') {
            return false;
        }

        $chargeItem = ChargeItem::query()->where('item', $this->item_description)->first();

        return (bool) ($chargeItem?->apply_customer_discount);
    }

    public function addOrUpdateItem(): void
    {
        if (!$this->workflow()->canEditInvoice($this->shipment, auth()->user())) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to edit this invoice.'));

            return;
        }

        $invoice = $this->getInvoice();

        $validated = $this->validate([
            'item_description' => ['required', 'string', 'max:255', Rule::exists('charge_items', 'item')],
            'item_amount' => [
                Rule::requiredIf(fn() => !$this->chargeItemForInvoiceForm()?->apply_customer_discount),
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $chargeItem = ChargeItem::query()->where('item', $validated['item_description'])->firstOrFail();
        $this->shipment->loadMissing('shipper');
        $resolver = app(InvoiceLineAmountResolver::class);

        if ($chargeItem->apply_customer_discount) {
            $resolved = $resolver->resolveDiscountLine($chargeItem, $this->shipment->shipper);
        } else {
            $resolved = $resolver->resolveStandardLine((float) $validated['item_amount']);
        }

        $net = (float) $resolved['net'];
        $gross = (float) $resolved['gross'];
        $discount = (float) $resolved['discount'];

        $finalDescription = $validated['item_description'];
        if ($this->invoice_vehicle_id && $this->shipment->isContainer()) {
            $vehicle = $this->shipment->vehicles()->find($this->invoice_vehicle_id);
            if ($vehicle) {
                $yearMake = trim(($vehicle->year ?? '') . ' ' . ($vehicle->make ?? ''));
                $vinSuffix = $vehicle->vin ? substr($vehicle->vin, -6) : '—';
                $finalDescription .= " ({$yearMake} - {$vinSuffix})";
            }
        }

        $wasUpdating = (bool) $this->invoiceItemId;

        if ($wasUpdating) {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->whereKey($this->invoiceItemId)->firstOrFail();
            $fromDescription = (string) $item->description;
            $fromAmount = (float) $item->amount;
            $item->fill([
                'description' => $finalDescription,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'amount' => $net,
            ])->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_updated',
                'properties' => array_filter([
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $item->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                    'from_description' => $fromDescription,
                    'to_description' => $finalDescription,
                    'from_amount' => $fromAmount,
                    'to_amount' => $net,
                    'gross_amount' => $gross !== 0.0 ? $gross : null,
                    'discount_amount' => $discount !== 0.0 ? $discount : null,
                ], fn($v) => $v !== null),
            ]);
        } else {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->create([
                'description' => $finalDescription,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'amount' => $net,
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_added',
                'properties' => array_filter([
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $item->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                    'description' => $finalDescription,
                    'amount' => $net,
                    'gross_amount' => $gross !== 0.0 ? $gross : null,
                    'discount_amount' => $discount !== 0.0 ? $discount : null,
                ], fn($v) => $v !== null),
            ]);
        }

        $this->invoice_vehicle_id = null;
        $this->refreshInvoiceTotals($invoice);

        $this->shipment->load('activityLogs.user');

        $this->resetInvoiceItemForm();

        $this->notification()->success(
            $wasUpdating ? __('Invoice item updated.') : __('Invoice item added.')
        );
    }

    public function editItem(int $itemId): void
    {
        if (!$this->workflow()->canEditInvoice($this->shipment, auth()->user())) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to edit this invoice.'));

            return;
        }

        $invoice = $this->getInvoice();

        /** @var InvoiceItem $item */
        $item = $invoice->items()->whereKey($itemId)->firstOrFail();

        $description = (string) $item->description;
        $this->invoice_vehicle_id = null;

        // Auto-detect suffix for Container shipments (Detecting Year Make - VIN)
        if ($this->shipment->isContainer()) {
            foreach ($this->shipment->vehicles as $vehicle) {
                $yearMake = trim(($vehicle->year ?? '') . ' ' . ($vehicle->make ?? ''));
                $vinSuffix = $vehicle->vin ? substr($vehicle->vin, -6) : '—';
                $suffix = " ({$yearMake} - {$vinSuffix})";

                if (str_ends_with($description, $suffix)) {
                    $this->invoice_vehicle_id = $vehicle->id;
                    $description = substr($description, 0, -strlen($suffix));
                    break;
                }
            }
        }

        $this->invoiceItemId = $item->id;
        $this->item_description = $description;
        $this->item_amount = number_format((float) $item->amount, 2, '.', '');

        $chargeItem = ChargeItem::query()->where('item', $item->description)->first();
        $this->shipment->loadMissing('shipper');
        $resolver = app(InvoiceLineAmountResolver::class);

        if ($chargeItem?->apply_customer_discount) {
            $resolved = $resolver->resolveDiscountLine($chargeItem, $this->shipment->shipper);
            $this->item_amount = $resolved['net'];
        } else {
            $this->item_amount = number_format((float) $item->amount, 2, '.', '');
        }
    }

    private function chargeItemForInvoiceForm(): ?ChargeItem
    {
        if ($this->item_description === '') {
            return null;
        }

        return ChargeItem::query()->where('item', $this->item_description)->first();
    }

    public function deleteItem(int $itemId): void
    {
        if (!$this->workflow()->canEditInvoice($this->shipment, auth()->user())) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to edit this invoice.'));

            return;
        }

        $invoice = $this->getInvoice();

        /** @var InvoiceItem|null $item */
        $item = $invoice->items()->whereKey($itemId)->first();

        if ($item) {
            $properties = array_filter([
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'reference_no' => $this->shipment->reference_no,
                'source' => 'shipment_show',
                'description' => (string) $item->description,
                'amount' => (float) $item->amount,
                'gross_amount' => (float) $item->gross_amount !== 0.0 ? (float) $item->gross_amount : null,
                'discount_amount' => (float) $item->discount_amount !== 0.0 ? (float) $item->discount_amount : null,
            ], fn($v) => $v !== null);

            $item->delete();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_removed',
                'properties' => $properties,
            ]);

            $this->refreshInvoiceTotals($invoice);
            $this->notification()->success(__('Invoice item removed.'));
        }

        if ($this->invoiceItemId === $itemId) {
            $this->resetInvoiceItemForm();
        }

        $this->shipment->load(['invoice.items', 'activityLogs.user']);
    }

    public function openInvoiceStatusConfirm(string $value): void
    {
        $targetStatus = InvoiceStatus::from($value);
        $user = auth()->user();

        if ($targetStatus === InvoiceStatus::Cleared && !$this->workflow()->canClearInvoice($this->shipment, $user)) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to clear this invoice.'));

            return;
        }

        if ($targetStatus === InvoiceStatus::Completed && !$this->workflow()->canCompleteInvoice($this->shipment, $user)) {
            if ($this->shipment->shipment_status !== \App\Enums\ShipmentStatus::Loaded) {
                $this->notification()->error(__('Action Blocked'), __('Shipment must be LOADED before invoice completion.'));
            } else {
                $this->notification()->error(__('Access Denied'), __('You do not have permission to complete this invoice.'));
            }

            return;
        }

        $currentValue = $this->shipment->invoice?->status?->value ?? $this->shipment->invoice_status?->value;

        if ($currentValue === $value) {
            $this->notification()->info(__('No change'), __('The invoice is already in this status.'));

            return;
        }

        $this->pendingInvoiceStatus = $value;
        $this->showInvoiceStatusConfirmModal = true;
    }

    public function confirmInvoiceStatusChange(): void
    {
        $this->authorize('invoices.manage');

        $validated = $this->validate([
            'pendingInvoiceStatus' => ['required', 'string', Rule::enum(InvoiceStatus::class)],
        ]);

        $invoice = $this->getInvoice();
        $newStatus = InvoiceStatus::from($validated['pendingInvoiceStatus']);

        if ($invoice->status === $newStatus) {
            $this->showInvoiceStatusConfirmModal = false;
            $this->pendingInvoiceStatus = null;

            return;
        }

        $fromStatus = $invoice->status;

        DB::transaction(function () use ($invoice, $newStatus, $fromStatus): void {
            $invoice->status = $newStatus;

            // Side effect: AWAITING_BL -> AWAITING_PAYMENT when completed
            if ($newStatus === InvoiceStatus::Completed && $this->shipment->payment_status === \App\Enums\PaymentStatus::AwaitingBL) {
                $this->shipment->payment_status = \App\Enums\PaymentStatus::AwaitingPayment;
            }

            $invoice->save();

            $this->shipment->invoice_status = $newStatus;
            $this->shipment->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_status_changed',
                'properties' => [
                    'from' => $fromStatus->value,
                    'to' => $newStatus->value,
                    'from_label' => $fromStatus->name,
                    'to_label' => $newStatus->name,
                    'invoice_id' => $invoice->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            $invoice->refresh();
            $this->shipment->refresh();
            Notification::send(
                $recipients,
                new InvoiceStatusChangedNotification($this->shipment, $invoice, $fromStatus, $newStatus)
            );
        }

        $this->reloadShipmentPageData();

        $this->showInvoiceStatusConfirmModal = false;
        $this->pendingInvoiceStatus = null;

        $this->notification()->success(__('Invoice status updated.'));
    }

    /**
     * @return Collection<int, int>
     */
    protected function staffAndAdminNotificationRecipientIds(): Collection
    {
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        return User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->merge(User::query()->whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->pluck('id'))
            ->unique()
            ->values();
    }

    protected function refreshInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = (float) $invoice->items()->sum('amount');

        $invoice->subtotal = $subtotal;
        $invoice->tax_amount = $invoice->tax_amount ?? 0;
        $invoice->total_amount = $subtotal + (float) $invoice->tax_amount;
        $invoice->save();

        $this->shipment->load('invoice.items');
    }

    protected function resetInvoiceItemForm(): void
    {
        $this->invoiceItemId = null;
        $this->item_description = '';
        $this->item_amount = '0.00';
    }

    public function openAssignDriverModal(?int $vehicleId = null): void
    {
        $this->authorize('shipments.update');
        $this->selectedVehicleId = $vehicleId;

        if ($vehicleId) {
            $vehicle = \App\Models\Vehicle::query()->findOrFail($vehicleId);
            $this->driver_id = $vehicle->driver_id;
        } else {
            $this->driver_id = $this->shipment->driver_id;
        }

        $this->showAssignDriverModal = true;
    }

    public function assignDriver(): void
    {
        $this->authorize('shipments.update');

        $validated = $this->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'selectedVehicleId' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        $driverId = (int) $validated['driver_id'];
        $vehicleId = $validated['selectedVehicleId'];
        $vehicle = $vehicleId ? \App\Models\Vehicle::findOrFail($vehicleId) : null;

        if (!auth()->user()->can('workflow.assign_driver')) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to assign drivers.'));

            return;
        }

        if (!$this->workflow()->canAssignDriver($this->shipment, $vehicle)) {
            $this->notification()->error(__('Invalid Action'), __('Driver cannot be assigned in the current status.'));

            return;
        }

        $driver = Driver::query()->findOrFail($driverId);

        $this->shipment->loadMissing('shipper');

        DB::transaction(function () use ($driverId, $driver, $vehicleId): void {
            $driverLabel = filled($driver->company)
                ? (string) $driver->company
                : (filled($driver->phone) ? (string) $driver->phone : (string) $driver->id);

            if ($this->shipment->shipping_mode === \App\Enums\ShippingMode::Container) {
                // Container: individual vehicle driver assignment
                if (!$vehicleId) {
                    throw new \RuntimeException('Vehicle selection is required for driver assignment in container mode.');
                }

                $vehicle = \App\Models\Vehicle::query()->findOrFail($vehicleId);
                $vehicle->update(['driver_id' => $driverId]);
                $vehicle->updateStatus(\App\Enums\VehicleStatus::Dispatched, __('Driver assigned to vehicle: :d', ['d' => $driverLabel]));
            } else {
                // RoRo: shipment-level assignment
                $this->shipment->update([
                    'driver_id' => $driverId,
                    'shipment_status' => \App\Enums\ShipmentStatus::Dispatched,
                ]);

                // Also update the single vehicle status for RoRo
                $this->shipment->vehicles()->update(['driver_id' => $driverId, 'tracking_status' => \App\Enums\VehicleStatus::Dispatched]);

                \App\Models\ShipmentTracking::query()->create([
                    'shipment_id' => $this->shipment->id,
                    'status' => \App\Enums\ShipmentStatus::Dispatched,
                    'note' => __('Driver assigned to RoRo shipment; status moved to DISPATCHED.'),
                    'recorded_at' => now(),
                ]);
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'driver_assigned',
                'properties' => [
                    'driver_id' => $driverId,
                    'driver_label' => $driverLabel,
                    'vehicle_id' => $vehicleId,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->showAssignDriverModal = false;
        $this->notification()->success(__('Action successful.'));
    }

    public function openCreateDriverModal(): void
    {
        $this->authorize('drivers.create');
        $this->new_driver_company = '';
        $this->new_driver_phone = '';
        $this->new_driver_email = '';
        $this->showCreateDriverModal = true;
    }

    public function createDriver(): void
    {
        $this->authorize('drivers.create');

        $validated = $this->validate([
            'new_driver_company' => ['nullable', 'string', 'max:255'],
            'new_driver_phone' => ['required', 'string', 'max:50'],
            'new_driver_email' => ['nullable', 'email', 'max:255'],
        ]);

        $driver = Driver::query()->create([
            'company' => $validated['new_driver_company'] ?: null,
            'phone' => $validated['new_driver_phone'],
            'email' => $validated['new_driver_email'] ?: null,
        ]);

        $this->driver_id = $driver->id;
        $this->showCreateDriverModal = false;

        $this->notification()->success(__('Driver created. You can now assign it to this shipment.'));
    }

    #[Computed]
    public function workshopsForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Workshop::query()->orderBy('name')->get();
    }

    public function updatedAttachDocumentType(): void
    {
        // No longer tracking TitleDocument for shipment-level documents
    }

    public function openAttachDocumentModal(?int $vehicleId = null, ?string $documentType = null): void
    {
        $this->authorize('documents.manage');
        $this->attachDocumentType = $documentType ?? '';
        $this->attachDocumentNotes = '';
        $this->attachFiles = [];
        $this->attachTitleVehicleIs = 'runner';
        $this->selectedVehicleId = $vehicleId ?? $this->shipment->vehicles->first()?->id;
        $this->showAttachDocumentModal = true;
    }

    public function saveAttachedDocuments(): void
    {
        $this->authorize('documents.manage');

        $rules = [
            'attachDocumentType' => ['required', 'string', Rule::enum(ShipmentDocumentType::class)],
            'attachDocumentNotes' => ['nullable', 'string', 'max:2000'],
            'attachFiles' => ['required', 'array', 'min:1'],
            'attachFiles.*' => ['file', 'max:20480'],
            'attachTitleVehicleIs' => [
                'nullable',
                'string',
                Rule::enum(VehicleIs::class),
            ],
        ];

        $this->validate($rules);

        $documentType = ShipmentDocumentType::from($this->attachDocumentType);

        if ($documentType === ShipmentDocumentType::StampDockReceipt) {
            if (!auth()->user()->can('workflow.attach_dock_receipt')) {
                $this->notification()->error(__('Access Denied'), __('Insufficient permission for Dock Receipt.'));

                return;
            }
            if (!$this->workflow()->canAttachDockReceipt($this->shipment)) {
                $this->notification()->error(__('Invalid Action'), __('Dock Receipt can only be attached in INLAND status.'));

                return;
            }
        }

        if ($documentType === ShipmentDocumentType::BillOfLading) {
            if (!auth()->user()->can('workflow.attach_bl')) {
                $this->notification()->error(__('Access Denied'), __('Insufficient permission for Bill of Lading.'));

                return;
            }
            if (!$this->workflow()->canAttachBL($this->shipment)) {
                $this->notification()->error(__('Invalid Action'), __('Bill of Lading can only be attached in DELIVERED status.'));

                return;
            }
        }

        $fromShipmentStatus = $this->shipment->shipment_status;
        $document = null;
        $fileCount = 0;

        DB::transaction(function () use ($documentType, &$document, &$fileCount, $fromShipmentStatus): void {

            if ($documentType === ShipmentDocumentType::StampDockReceipt) {
                $this->shipment->update(['shipment_status' => \App\Enums\ShipmentStatus::Delivered]);
            }

            if ($documentType === ShipmentDocumentType::BillOfLading) {
                $this->shipment->update(['shipment_status' => \App\Enums\ShipmentStatus::Loaded]);
            }

            /** @var ShipmentDocument $document */
            $document = ShipmentDocument::query()->create([
                'shipment_id' => $this->shipment->id,
                'document_type' => $documentType,
                'notes' => $this->attachDocumentNotes !== '' ? $this->attachDocumentNotes : null,
            ]);

            $attachedFileIds = [];
            $attachedFileNames = [];

            foreach ($this->attachFiles as $uploaded) {
                $path = $uploaded->store('shipment-documents/' . $this->shipment->id, 'local');
                $createdFile = ShipmentDocumentFile::query()->create([
                    'shipment_document_id' => $document->id,
                    'path' => $path,
                    'original_name' => $uploaded->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                ]);
                $attachedFileIds[] = $createdFile->id;
                $attachedFileNames[] = $uploaded->getClientOriginalName();
                $fileCount++;
            }

            $currentStatus = $this->shipment->refresh()->shipment_status;

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'document_attached',
                'properties' => [
                    'shipment_document_id' => $document->id,
                    'document_type' => $documentType->value,
                    'document_type_label' => $documentType->label(),
                    'file_count' => $fileCount,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show_attach_document',
                ],
            ]);

            if ($fromShipmentStatus !== $currentStatus) {
                ShipmentTracking::query()->create([
                    'shipment_id' => $this->shipment->id,
                    'status' => $currentStatus,
                    'note' => __('Document attached: :type. Status moved to :s', ['type' => $documentType->label(), 's' => $currentStatus->name]),
                    'recorded_at' => now(),
                ]);
            }
        });

        $this->reloadShipmentPageData();
        $this->showAttachDocumentModal = false;
        $this->reset(['attachDocumentType', 'attachDocumentNotes', 'attachFiles']);
        $this->attachTitleVehicleIs = 'runner';

        $this->notification()->success(__('Document(s) attached.'));
    }

    public function openAttachVehicleDocumentModal(int $vehicleId, ?string $documentType = null): void
    {
        $this->authorize('documents.manage');
        $this->attachVehicleDocumentType = $documentType ?? '';
        $this->attachVehicleDocumentNotes = '';
        $this->attachVehicleFiles = [];
        $this->attachVehicleTitleVehicleIs = 'runner';
        $this->selectedVehicleId = $vehicleId;
        $this->showAttachVehicleDocumentModal = true;
    }

    public function saveAttachedVehicleDocuments(): void
    {
        $this->authorize('documents.manage');

        $this->validate([
            'attachVehicleDocumentType' => ['required', 'string', Rule::enum(VehicleDocumentType::class)],
            'attachVehicleDocumentNotes' => ['nullable', 'string', 'max:2000'],
            'attachVehicleFiles' => ['required', 'array', 'min:1'],
            'attachVehicleFiles.*' => ['file', 'max:20480'],
            'selectedVehicleId' => ['required', 'integer', 'exists:vehicles,id'],
            'attachVehicleTitleVehicleIs' => [
                Rule::requiredIf(fn() => $this->attachVehicleDocumentType === VehicleDocumentType::TitleDocument->value),
                'nullable',
                'string',
                Rule::enum(VehicleIs::class),
            ],
        ]);

        $documentType = VehicleDocumentType::from($this->attachVehicleDocumentType);
        $vehicle = \App\Models\Vehicle::query()->findOrFail($this->selectedVehicleId);

        if ($documentType === VehicleDocumentType::TitleDocument) {
            if (!auth()->user()->can('workflow.attach_title')) {
                $this->notification()->error(__('Access Denied'), __('Insufficient permission for Title Document.'));

                return;
            }
            if (!$this->workflow()->canAttachTitle($this->shipment, $vehicle)) {
                $this->notification()->error(__('Invalid Action'), __('Title Document can only be attached in DISPATCHED status.'));

                return;
            }
        }

        if ($documentType === VehicleDocumentType::PhotosAndVideos) {
            if (!auth()->user()->can('workflow.upload_photos')) {
                $this->notification()->error(__('Access Denied'), __('Insufficient permission for Photos.'));

                return;
            }
            if (!$this->workflow()->canAttachPhotos($this->shipment, $vehicle)) {
                $this->notification()->error(__('Invalid Action'), __('Photos can only be uploaded in INLAND status (or onwards for RoRo).'));

                return;
            }
        }

        if ($documentType === VehicleDocumentType::TitleDocument) {
            // No longer checking logistics info here, as we transition to BOOKING status first
        }

        DB::transaction(function () use ($documentType): void {
            $vehicle = \App\Models\Vehicle::query()->findOrFail($this->selectedVehicleId);

            abort_unless($vehicle->shipment_id === $this->shipment->id, 403);

            if ($documentType === VehicleDocumentType::TitleDocument) {
                $vehicle->vehicle_is = VehicleIs::from($this->attachVehicleTitleVehicleIs);
                $vehicle->save();
            }

            /** @var VehicleDocument $document */
            $document = $vehicle->vehicleDocuments()->create([
                'document_type' => $documentType,
                'notes' => $this->attachVehicleDocumentNotes !== '' ? $this->attachVehicleDocumentNotes : null,
            ]);

            foreach ($this->attachVehicleFiles as $uploaded) {
                $path = $uploaded->store('vehicle-documents/' . $vehicle->id, 'local');
                VehicleDocumentFile::query()->create([
                    'vehicle_document_id' => $document->id,
                    'path' => $path,
                    'original_name' => $uploaded->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                ]);
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'vehicle_document_attached',
                'properties' => [
                    'vehicle_id' => $vehicle->id,
                    'document_type' => $documentType->value,
                    'document_type_label' => $documentType->label(),
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);

            $statusNote = $documentType === VehicleDocumentType::TitleDocument
                ? __('Title document attached.')
                : ($documentType === VehicleDocumentType::PhotosAndVideos ? __('Vehicle photos uploaded.') : __('Vehicle document attached.'));

            if ($this->shipment->shipping_mode === ShippingMode::Container) {
                $vehicle->refresh();
                if ($documentType === VehicleDocumentType::TitleDocument) {
                    $vehicle->updateStatus(VehicleStatus::Inland, $statusNote);
                } elseif ($documentType === VehicleDocumentType::PhotosAndVideos) {
                    $vehicle->updateStatus(VehicleStatus::AtWarehouse, $statusNote);
                }
            } else {
                // RoRo Logic
                if ($documentType === VehicleDocumentType::TitleDocument) {
                    $this->shipment->update([
                        'shipment_status' => ShipmentStatus::Booking,
                    ]);

                    $this->shipment->vehicles()->update(['tracking_status' => VehicleStatus::Dispatched]);

                    ShipmentTracking::query()->create([
                        'shipment_id' => $this->shipment->id,
                        'status' => ShipmentStatus::Booking,
                        'note' => $statusNote,
                        'recorded_at' => now(),
                    ]);
                }
            }
        });

        $this->reloadShipmentPageData();

        $this->showAttachVehicleDocumentModal = false;
        $this->reset(['attachVehicleDocumentType', 'attachVehicleDocumentNotes', 'attachVehicleFiles']);
        $this->attachVehicleTitleVehicleIs = 'runner';
        $this->notification()->success(__('Vehicle document(s) attached.'));
    }

    public function markContainerFilled(bool $force = false): void
    {
        $this->authorize('shipments.update');

        if (!$force) {
            if (!$this->workflow()->canMarkFilled($this->shipment)) {
                $this->notification()->error(__('Requirement missed'), __('Normal container filling requires at least 4 vehicles at warehouse status and status must be OPEN.'));

                return;
            }
        } else {
            $this->authorizeStaffOrSuperAdmin();
            if (!auth()->user()->can('workflow.force_filled') || !$this->workflow()->canMarkFilled($this->shipment, true)) {
                $this->notification()->error(__('Invalid Action'), __('Cannot force fill in current status or insufficient permission.'));

                return;
            }
        }

        // Removed canTransitionToInland check here as we move to BOOKING status first
        // Logistics info will be required for the BOOKING -> INLAND transition instead

        DB::transaction(function () use ($force): void {
            $this->shipment->update([
                'shipment_status' => \App\Enums\ShipmentStatus::Booking,
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => \App\Enums\ShipmentStatus::Booking,
                'note' => $force ? __('Container forcefully filled by admin.') : __('Container marked as filled.'),
                'recorded_at' => now(),
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'container_filled',
                'properties' => [
                    'force' => $force,
                    'reference_no' => $this->shipment->reference_no,
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->notification()->success(__('Container status updated to BOOKING.'));
    }

    public function editLogistics(): void
    {
        if (!auth()->user()->can('workflow.manage_logistics')) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to manage logistics.'));
            return;
        }

        $this->authorize('shipments.update');

        $this->logisticsForm['shipment'] = [
            'bill_of_lading_number' => $this->shipment->bill_of_lading_number,
            'booking_number' => $this->shipment->booking_number,
            'itn_number' => $this->shipment->itn_number,
            'container_no' => $this->shipment->container_no,
            'seal_no' => $this->shipment->seal_no,
            'container_type' => $this->shipment->container_type,
            'vessel_name' => $this->shipment->vessel_name,
            'voyage_no' => $this->shipment->voyage_no,
            'cut_off_date' => $this->shipment->cut_off_date?->format('Y-m-d'),
            'departure_date' => $this->shipment->departure_date?->format('Y-m-d'),
            'arrival_date' => $this->shipment->arrival_date?->format('Y-m-d'),
            'domestic_routing' => $this->shipment->domestic_routing,
            'loading_pier' => $this->shipment->loading_pier,
        ];

        $this->logisticsForm['vehicles'] = [];
        foreach ($this->shipment->vehicles as $vehicle) {
            $this->logisticsForm['vehicles'][$vehicle->id] = [
                'value' => $vehicle->value,
                'weight' => $vehicle->weight,
                'weight_unit' => $vehicle->weight_unit ?? 'KG',
                'measurement' => $vehicle->measurement,
                'measurement_unit' => $vehicle->measurement_unit ?? 'CBM',
            ];
        }

        $this->showLogisticsModal = true;
    }

    public function saveLogistics(): void
    {
        if (!auth()->user()->can('workflow.manage_logistics')) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to manage logistics.'));
            return;
        }

        $this->authorize('shipments.update');

        $validated = $this->validate([
            'logisticsForm.shipment.bill_of_lading_number' => 'nullable|string|max:255',
            'logisticsForm.shipment.booking_number' => 'nullable|string|max:255',
            'logisticsForm.shipment.itn_number' => 'nullable|string|max:255',
            'logisticsForm.shipment.container_no' => 'nullable|string|max:255',
            'logisticsForm.shipment.seal_no' => 'nullable|string|max:255',
            'logisticsForm.shipment.container_type' => 'nullable|string|max:255',
            'logisticsForm.shipment.vessel_name' => 'nullable|string|max:255',
            'logisticsForm.shipment.voyage_no' => 'nullable|string|max:255',
            'logisticsForm.shipment.cut_off_date' => 'nullable|date',
            'logisticsForm.shipment.departure_date' => 'nullable|date',
            'logisticsForm.shipment.arrival_date' => 'nullable|date',
            'logisticsForm.shipment.domestic_routing' => 'nullable|string',
            'logisticsForm.shipment.loading_pier' => 'nullable|string',
            'logisticsForm.vehicles.*.value' => 'nullable|numeric|min:0',
            'logisticsForm.vehicles.*.weight' => 'nullable|numeric|min:0',
            'logisticsForm.vehicles.*.weight_unit' => 'required|string|in:KG,LBS',
            'logisticsForm.vehicles.*.measurement' => 'nullable|numeric|min:0',
            'logisticsForm.vehicles.*.measurement_unit' => 'required|string|in:CBM,CFT',
        ]);

        DB::transaction(function () use ($validated): void {
            $this->shipment->update($validated['logisticsForm']['shipment']);

            foreach ($validated['logisticsForm']['vehicles'] as $vehicleId => $data) {
                $vehicle = \App\Models\Vehicle::find($vehicleId);
                if ($vehicle) {
                    $vehicle->update($data);
                }
            }

            // AUTO-TRANSITION: BOOKING -> INLAND
            if ($this->shipment->shipment_status === ShipmentStatus::Booking && $this->workflow()->canTransitionToInland($this->shipment)) {
                $this->shipment->update(['shipment_status' => ShipmentStatus::Inland]);

                ShipmentTracking::query()->create([
                    'shipment_id' => $this->shipment->id,
                    'status' => ShipmentStatus::Inland,
                    'note' => __('Shipment transitioned to INLAND after logistics & booking update.'),
                    'recorded_at' => now(),
                ]);
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'logistics_updated',
                'properties' => [
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show_logistics_modal',
                    'transitioned_to_inland' => $this->shipment->shipment_status === ShipmentStatus::Inland,
                ],
            ]);
        });

        $this->showLogisticsModal = false;
        $this->notification()->success(__('Logistics updated successfully.'));
        $this->reloadShipmentPageData();
    }

    public function markShipmentDelivered(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        if (!$this->workflow()->canMarkDelivered($this->shipment)) {
            $this->notification()->error(__('Invalid Action'), __('Shipment cannot be marked as delivered in current status.'));

            return;
        }

        DB::transaction(function (): void {
            $this->shipment->update(['shipment_status' => ShipmentStatus::Delivered]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => ShipmentStatus::Delivered,
                'note' => __('Shipment marked as delivered.'),
                'recorded_at' => now(),
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'shipment_delivered',
                'properties' => [
                    'reference_no' => $this->shipment->reference_no,
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->notification()->success(__('Shipment status updated to DELIVERED.'));
    }

    public function markShipmentLoaded(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        if (!$this->workflow()->canMarkLoaded($this->shipment)) {
            $this->notification()->error(__('Invalid Action'), __('Shipment cannot be marked as loaded in current status.'));

            return;
        }

        DB::transaction(function (): void {
            $this->shipment->update(['shipment_status' => ShipmentStatus::Loaded]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => ShipmentStatus::Loaded,
                'note' => __('Shipment marked as loaded on vessel.'),
                'recorded_at' => now(),
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'shipment_loaded',
                'properties' => [
                    'reference_no' => $this->shipment->reference_no,
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->notification()->success(__('Shipment status updated to LOADED.'));
    }

    public function completeShipment(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        if (!auth()->user()->can('workflow.complete')) {
            $this->notification()->error(__('Access Denied'), __('You do not have permission to complete shipments.'));

            return;
        }

        if (!$this->workflow()->canCompleteShipment($this->shipment)) {
            $this->notification()->error(__('Invalid Action'), __('Shipment must be in LOADED status to complete.'));

            return;
        }

        DB::transaction(function (): void {
            $this->shipment->update([
                'shipment_status' => \App\Enums\ShipmentStatus::Completed,
                'completed_at' => now(),
                'seal_closed_at' => $this->shipment->isContainer() ? now() : null,
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => \App\Enums\ShipmentStatus::Completed,
                'note' => __('Shipment completed successfully.'),
                'recorded_at' => now(),
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'shipment_completed',
                'properties' => [
                    'reference_no' => $this->shipment->reference_no,
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->notification()->success(__('Shipment completed and locked.'));
    }

    public function openToWorkshopModal(?int $vehicleId = null): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $this->selectedVehicleId = $vehicleId;

        if ($vehicleId) {
            $vehicle = \App\Models\Vehicle::query()->findOrFail($vehicleId);
            $this->toWorkshopWorkshopId = $vehicle->workshop_id;
        } else {
            $this->toWorkshopWorkshopId = null;
        }

        $this->showToWorkshopModal = true;
    }

    public function saveToWorkshop(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $validated = $this->validate([
            'toWorkshopWorkshopId' => ['required', 'integer', 'exists:workshops,id'],
            'selectedVehicleId' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        $workshopId = (int) $validated['toWorkshopWorkshopId'];
        $vehicleId = $validated['selectedVehicleId'];

        DB::transaction(function () use ($workshopId, $vehicleId): void {
            $workshop = Workshop::query()->findOrFail($workshopId);

            if ($vehicleId) {
                $vehicle = \App\Models\Vehicle::query()->findOrFail($vehicleId);
                $vehicle->update([
                    'workshop_id' => $workshopId,
                    'is_at_workshop' => true,
                ]);

                \App\Models\VehicleTracking::create([
                    'vehicle_id' => $vehicleId,
                    'status' => \App\Enums\VehicleStatus::Inland,
                    'workshop_id' => $workshopId,
                    'note' => __('Vehicle sent to workshop: :w', ['w' => $workshop->name]),
                    'recorded_at' => now(),
                ]);
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'vehicle_sent_to_workshop',
                'properties' => [
                    'workshop_id' => $workshopId,
                    'workshop_name' => $workshop->name,
                    'vehicle_id' => $vehicleId,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->showToWorkshopModal = false;
        $this->notification()->success(__('Action successful.'));
    }

    public function openFromWorkshopConfirmModal(?int $vehicleId = null): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $this->selectedVehicleId = $vehicleId;
        $this->showFromWorkshopConfirmModal = true;
    }

    public function fromWorkshop(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $vehicleId = $this->selectedVehicleId;

        DB::transaction(function () use ($vehicleId): void {
            if ($vehicleId) {
                $vehicle = \App\Models\Vehicle::query()->findOrFail($vehicleId);
                $stored = $vehicle->status_before_workshop ?? \App\Enums\VehicleStatus::Pending;

                $vehicle->update([
                    'is_at_workshop' => false,
                    'workshop_id' => null,
                ]);

                \App\Models\VehicleTracking::create([
                    'vehicle_id' => $vehicleId,
                    'status' => $stored instanceof \App\Enums\VehicleStatus ? $stored : \App\Enums\VehicleStatus::Pending,
                    'note' => __('Vehicle returned from workshop.'),
                    'recorded_at' => now(),
                ]);
            } else {
                // If no vehicle selected, restore all vehicles in shipment
                $this->shipment->vehicles()->update([
                    'is_at_workshop' => false,
                    'workshop_id' => null,
                ]);
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'vehicle_returned_from_workshop',
                'properties' => [
                    'vehicle_id' => $vehicleId,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $this->reloadShipmentPageData();
        $this->showFromWorkshopConfirmModal = false;
        $this->notification()->success(__('Action successful.'));
    }

    public function openDeleteDocumentConfirm(int $shipmentDocumentId): void
    {
        $this->authorizeStaffOrSuperAdmin();
        $this->pendingDeleteShipmentDocumentId = $shipmentDocumentId;
        $this->showDeleteDocumentConfirmModal = true;
    }

    public function openDeleteFileConfirm(int $shipmentDocumentFileId): void
    {
        $this->authorizeStaffOrSuperAdmin();
        $this->pendingDeleteShipmentDocumentFileId = $shipmentDocumentFileId;
        $this->showDeleteFileConfirmModal = true;
    }

    public function deleteShipmentDocumentConfirmed(): void
    {
        $this->authorizeStaffOrSuperAdmin();

        if ($this->pendingDeleteShipmentDocumentId === null) {
            $this->showDeleteDocumentConfirmModal = false;

            return;
        }

        $document = ShipmentDocument::query()
            ->where('shipment_id', $this->shipment->id)
            ->whereKey($this->pendingDeleteShipmentDocumentId)
            ->with('files')
            ->firstOrFail();

        DB::transaction(function () use ($document): void {
            $docTypeLabel = $document->document_type?->label();
            $docTypeValue = $document->document_type?->value;
            $documentId = $document->id;

            foreach ($document->files as $file) {
                Storage::disk('local')->delete($file->path);
                $file->delete();
            }

            $document->delete();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'document_removed',
                'properties' => [
                    'shipment_document_id' => $documentId,
                    'document_type' => $docTypeValue,
                    'document_type_label' => $docTypeLabel,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $this->pendingDeleteShipmentDocumentId = null;
        $this->showDeleteDocumentConfirmModal = false;
        $this->reloadShipmentPageData();
        $this->notification()->success(__('Attachment removed.'));
    }

    public function deleteShipmentDocumentFileConfirmed(): void
    {
        $this->authorizeStaffOrSuperAdmin();

        if ($this->pendingDeleteShipmentDocumentFileId === null) {
            $this->showDeleteFileConfirmModal = false;

            return;
        }

        $file = ShipmentDocumentFile::query()
            ->whereKey($this->pendingDeleteShipmentDocumentFileId)
            ->with('shipmentDocument')
            ->firstOrFail();

        if ($file->shipmentDocument === null || $file->shipmentDocument->shipment_id !== $this->shipment->id) {
            abort(404);
        }

        DB::transaction(function () use ($file): void {
            $fileId = $file->id;
            $originalName = $file->original_name;
            $path = $file->path;
            $document = $file->shipmentDocument;

            Storage::disk('local')->delete($path);
            $file->delete();

            if ($document->files()->count() === 0) {
                $document->delete();
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'document_file_removed',
                'properties' => [
                    'shipment_document_file_id' => $fileId,
                    'original_name' => $originalName,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $this->pendingDeleteShipmentDocumentFileId = null;
        $this->showDeleteFileConfirmModal = false;
        $this->reloadShipmentPageData();
        $this->notification()->success(__('File removed.'));
    }

    protected function reloadShipmentPageData(): void
    {
        $this->shipment->refresh()->load([
            'shipper.user',
            'consignee',
            'vehicles',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'carrier',
            'paymentMethod',
            'driver',
            'workshop',
            'invoice.items',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn($query) => $query->orderByDesc('recorded_at'),
        ]);
    }

    protected function authorizeStaffOrSuperAdmin(): void
    {
        $user = Auth::user();
        if ($user === null || (!$user->hasRole('super_admin') && !$user->staff()->exists())) {
            abort(403);
        }
    }

    /**
     * @param  ?ShipmentStatus  $toStatus  Null when status unchanged
     */
    protected function sendDocumentAttachedNotifications(
        ShipmentDocument $document,
        int $fileCount,
        ?ShipmentStatus $fromStatus,
        ?ShipmentStatus $toStatus,
    ): void {
        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($this->shipment->shipper?->user_id !== null) {
            $recipientIds = $recipientIds->push($this->shipment->shipper->user_id);
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->unique()->values())
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new ShipmentDocumentAttachedNotification(
                $this->shipment,
                $document,
                $fileCount,
                $fromStatus,
                $toStatus,
            ),
        );
    }
}; ?>

<x-crud.page-shell>
    <div class="space-y-6">
        {{-- Header & Summary --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3 sm:items-center">
                <div class="min-w-0 flex-1">
                    <x-crud.page-header :heading="__('SHIPMENT #') . $shipment->reference_no" :subheading="__('View full shipment, tracking, and financial details.')" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if($shipment->shipment_status)
                            <flux:badge color="indigo" variant="subtle" size="sm"
                                :icon="$shipment->isContainer() ? 'container' : 'truck'">
                                {{ $shipment->shipmentStatusDisplay() }}
                            </flux:badge>
                        @endif
                        @if($shipment->invoice_status)
                            <flux:badge color="amber" variant="subtle" size="sm" icon="document-text">
                                {{ $shipment->invoice_status->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->payment_status)
                            <flux:badge color="emerald" variant="subtle" size="sm" icon="banknotes">
                                {{ $shipment->payment_status->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->paymentMethod)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="credit-card">
                                {{ $shipment->paymentMethod->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->logistics_service)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="ship">
                                {{ $shipment->logistics_service->name ?? $shipment->logistics_service }}
                            </flux:badge>
                        @endif
                        @if($shipment->shipping_mode)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="cube">
                                {{ $shipment->shipping_mode->name ?? $shipment->shipping_mode }}
                            </flux:badge>
                        @endif
                        @if($shipment->isContainer())
                            <flux:badge color="{{ $shipment->isSealed() ? 'emerald' : 'amber' }}" variant="subtle" size="sm"
                                icon="lock-closed">
                                {{ $shipment->isSealed() ? __('Container Sealed') : __('Container Open') }}
                            </flux:badge>
                            <flux:badge color="zinc" variant="outline" size="sm" icon="users">
                                {{ __('Capacity: :c/:m', ['c' => $shipment->vehicles->count(), 'm' => $shipment->capacity]) }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                @if(!$shipment->isLocked())
                    @if($shipment->shipment_status === \App\Enums\ShipmentStatus::Loaded)
                        <flux:button variant="filled" color="emerald" icon="flag" wire:click="completeShipment">
                            {{ __('Complete Shipment') }}
                        </flux:button>
                    @endif
                @endif

                <flux:dropdown align="end" position="bottom">
                    <flux:button variant="outline" icon="ellipsis-horizontal">
                        {{ __('Actions') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="arrow-left" :href="route('shipments.index')" wire:navigate>
                            {{ __('Back to Shipments') }}
                        </flux:menu.item>

                        @can('shipments.update')
                            <flux:menu.item icon="pencil-square" :href="route('shipments.edit', $shipment)" wire:navigate>
                                {{ __('Edit Shipment') }}
                            </flux:menu.item>
                        @endcan

                        @can('workflow.download_invoice')
                            <flux:menu.item icon="document-arrow-down"
                                :href="route('shipments.invoice.download', $shipment)">
                                {{ __('Download Invoice') }}
                            </flux:menu.item>
                        @endcan

                        @can('workflow.manage_logistics')
                            <flux:menu.item icon="truck" wire:click="editLogistics">
                                {{ __('Booking & Logistics') }}
                            </flux:menu.item>
                        @endcan

                        @if($this->workflow()->canDownloadDockReceipt($shipment))
                            @can('workflow.download_dock_receipt')
                                <flux:menu.item icon="document-duplicate"
                                    :href="route('shipments.dock-receipt.download', $shipment)">
                                    {{ __('Download Dock Receipt') }}
                                </flux:menu.item>
                            @endcan
                        @endif

                        <flux:menu.item icon="eye" wire:click="openShipmentDocumentsModal">
                            {{ __('View Documents') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        @if(!$shipment->isLocked())
                            @if($shipment->isContainer() && $this->workflow()->canMarkFilled($shipment))
                                <flux:menu.item icon="check-circle" wire:click="markContainerFilled(false)">
                                    {{ __('Mark Filled') }}
                                </flux:menu.item>
                                @if(auth()->user()?->hasRole('super_admin') && $this->workflow()->canMarkFilled($shipment, true))
                                    <flux:menu.item icon="exclamation-triangle" wire:click="markContainerFilled(true)"
                                        variant="danger">
                                        {{ __('Force Fill') }}
                                    </flux:menu.item>
                                @endif
                            @endif
                            @can('documents.manage')
                                <flux:menu.item icon="paper-clip" wire:click="openAttachDocumentModal">
                                    {{ __('Attach documents') }}
                                </flux:menu.item>
                            @endcan

                            @can('shipments.update')
                                @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                                    @if($this->workflow()->canMarkDelivered($shipment))
                                        <flux:menu.item icon="truck" wire:click="markShipmentDelivered">
                                            {{ __('Mark Delivered') }}
                                        </flux:menu.item>
                                    @endif

                                    @if($this->workflow()->canMarkLoaded($shipment))
                                        <flux:menu.item icon="archive-box" wire:click="markShipmentLoaded">
                                            {{ __('Mark Loaded') }}
                                        </flux:menu.item>
                                    @endif
                                @endif
                            @endcan


                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        {{-- At-a-glance row --}}
        <x-crud.panel class="p-4">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                <div class="md:col-span-3">
                    <div class="space-y-3">
                        @if(!$shipment->isContainer())
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('VIN') }}
                                </flux:text>
                                <flux:text class="font-mono">
                                    {{ $shipment->vehicles->first()?->vin ?? '—' }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Lot number') }}
                                </flux:text>
                                <flux:text class="font-mono">
                                    {{ $shipment->vehicles->first()?->lot_number ?? '—' }}
                                </flux:text>
                            </div>
                        @else
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Container Mode') }}
                                </flux:text>
                                <flux:text>
                                    {{ __('Multi-Vehicle Logistics') }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Sealing Date') }}
                                </flux:text>
                                <flux:text>
                                    {{ $shipment->sealed_at?->format('M d, Y') ?: '—' }}
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="md:col-span-9">
                    <div class="space-y-4">
                        @if($shipment->shipper)
                            <div class="flex items-start gap-3">
                                <div>
                                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                        {{ __('Shipper') }}
                                    </flux:text>
                                    <flux:text size="sm" class="font-semibold">
                                        {{ $shipment->shipper->company_name ?? $shipment->shipper->user?->name }}
                                    </flux:text>
                                    <div class="flex flex-col gap-1 mt-1 text-zinc-500">
                                        @if($shipment->shipper->user?->email)
                                            <div class="flex items-center gap-1.5">
                                                <flux:icon.envelope class="size-3.5" />
                                                <flux:text size="xs">{{ $shipment->shipper->user->email }}</flux:text>
                                            </div>
                                        @endif
                                        @if($shipment->shipper->phone)
                                            <div class="flex items-center gap-1.5">
                                                <flux:icon.phone class="size-3.5" />
                                                <flux:text size="xs">{{ $shipment->shipper->phone }}</flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($shipment->consignee)
                            <div class="@if($shipment->shipper) border-t border-zinc-100 dark:border-zinc-800 pt-3 @endif">
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Consignee') }}
                                </flux:text>
                                <flux:text size="sm" class="font-medium">
                                    {{ $shipment->consignee->name }}@if(filled($shipment->consignee->address))
                                        <span
                                            class="font-normal text-zinc-600 dark:text-zinc-400">({{ $shipment->consignee->address }})</span>
                                    @endif
                                </flux:text>
                            </div>
                        @endif

                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Notify Party') }}
                            </flux:text>
                            @if($shipment->notifyParty)
                                <flux:text size="sm" class="font-medium">
                                    {{ $shipment->notifyParty->name }}@if(filled($shipment->notifyParty->address))
                                        <span
                                            class="font-normal text-zinc-600 dark:text-zinc-400">({{ $shipment->notifyParty->address }})</span>
                                    @endif
                                </flux:text>
                            @else
                                <flux:text size="xs" class="font-bold text-zinc-400 uppercase tracking-widest">
                                    {{ __('SAME AS ABOVE') }}
                                </flux:text>
                            @endif
                        </div>

                        @if(!$shipment->shipper && !$shipment->consignee)
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('No shipper or consignee on this shipment.') }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            </div>
        </x-crud.panel>

        <div class="grid grid-cols-1 gap-8 mt-8">
            {{-- Left rail --}}
            <div class="space-y-6">
                {{-- Logistics & Routing --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.map class="size-5 text-indigo-500" />
                        {{ __('Logistics & Routing') }}
                    </flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Origin Port') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                @if($shipment->originPort)
                                    {{ $shipment->originPort->name }}
                                    ({{ $shipment->originPort->state?->code ?? '—' }} -
                                    {{ $shipment->originPort->country?->iso2 ?? '—' }})
                                @else
                                    —
                                @endif
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Destination Port') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                @if($shipment->destinationPort)
                                    {{ $shipment->destinationPort->name }}
                                    ({{ $shipment->destinationPort->state?->code ?? '—' }} -
                                    {{ $shipment->destinationPort->country?->iso2 ?? '—' }})
                                @else
                                    —
                                @endif
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Carrier') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                {{ $shipment->carrier?->name ?? '—' }}
                            </flux:text>
                        </div>

                        @if($shipment->bill_of_lading_number)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Bill of Lading #') }}
                                </flux:text>
                                <flux:text class="font-bold">{{ $shipment->bill_of_lading_number }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->booking_number)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Booking #') }}
                                </flux:text>
                                <flux:text class="font-bold">{{ $shipment->booking_number }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->itn_number)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('ITN #') }}
                                </flux:text>
                                <flux:text class="font-mono">{{ $shipment->itn_number }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->container_no)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Container #') }}
                                </flux:text>
                                <flux:text class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ $shipment->container_no }}
                                </flux:text>
                            </div>
                        @endif

                        @if($shipment->seal_no)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Seal #') }}
                                </flux:text>
                                <flux:text class="font-medium">{{ $shipment->seal_no }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->container_type)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Container Type') }}
                                </flux:text>
                                <flux:text class="font-medium">{{ $shipment->container_type }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->vessel_name)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Vessel') }}
                                </flux:text>
                                <flux:text class="font-medium">{{ $shipment->vessel_name }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->voyage_no)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Voyage #') }}
                                </flux:text>
                                <flux:text class="font-medium">{{ $shipment->voyage_no }}</flux:text>
                            </div>
                        @endif

                        @if($shipment->cut_off_date)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Cut-off Date') }}
                                </flux:text>
                                <flux:text class="font-medium text-amber-600 dark:text-amber-400">
                                    {{ $shipment->cut_off_date->format('M d, Y') }}
                                </flux:text>
                            </div>
                        @endif

                        @if($shipment->departure_date)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Departure Date') }}
                                </flux:text>
                                <flux:text class="font-medium">
                                    {{ $shipment->departure_date->format('M d, Y') }}
                                </flux:text>
                            </div>
                        @endif

                        @if($shipment->arrival_date)
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Arrival Date') }}
                                </flux:text>
                                <flux:text class="font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ $shipment->arrival_date->format('M d, Y') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </x-crud.panel>


                {{-- Vehicles Section --}}
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg" class="flex items-center gap-2">
                            <flux:icon.truck class="size-5 text-indigo-500" />
                            {{ __('Vehicles in Shipment') }} ({{ count($shipment->vehicles) }})
                        </flux:heading>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        @foreach($shipment->vehicles as $vehicle)
                            <x-crud.panel wire:key="v-{{ $vehicle->id }}" class="p-0 overflow-hidden">
                                <div class="flex flex-col lg:flex-row">
                                    {{-- Photo Thumb --}}
                                    <div class="lg:w-48 lg:h-auto h-40 bg-zinc-100 dark:bg-zinc-900 shrink-0">
                                        @php $photos = $vehicle->copartCarPhotoUrls(); @endphp
                                        @if(count($photos) > 0)
                                            <img src="{{ $photos[0] }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.photo class="size-8" />
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Details --}}
                                    <div class="flex-1 p-6">
                                        <div class="flex flex-col md:flex-row justify-between gap-4">
                                            <div>
                                                <h4
                                                    class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                                    {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                                    @if($vehicle->workshop_id)
                                                        <flux:badge color="amber" size="sm" variant="subtle">
                                                            {{ __('At Workshop') }}
                                                        </flux:badge>
                                                    @endif
                                                </h4>
                                                <div class="flex flex-col">
                                                    <flux:text size="sm" class="font-mono text-zinc-500 uppercase mt-1">
                                                        {{ $vehicle->vin }}
                                                    </flux:text>
                                                    @if($vehicle->tracking_status)
                                                        <div class="mt-2">
                                                            <flux:badge size="xs" color="zinc" variant="outline" icon="truck">
                                                                {{ $vehicle->tracking_status->name }}
                                                            </flux:badge>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex w-full items-start justify-end gap-2 md:w-auto">
                                                @if(!$shipment->isLocked())
                                                    <flux:dropdown>
                                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="outline">
                                                            {{ __('Actions') }}
                                                        </flux:button>
                                                        <flux:menu>
                                                            @if($this->workflow()->canAssignDriver($shipment, $vehicle))
                                                                <flux:menu.item icon="user-plus"
                                                                    wire:click="openAssignDriverModal({{ $vehicle->id }})">
                                                                    {{ __('Assign Driver') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            @if($this->workflow()->canAttachTitle($shipment, $vehicle))
                                                                <flux:menu.item icon="document-text"
                                                                    wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::TitleDocument->value }}')">
                                                                    {{ __('Attach Title') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            @if($this->workflow()->canAttachPhotos($shipment, $vehicle))
                                                                <flux:menu.item icon="photo"
                                                                    wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::PhotosAndVideos->value }}')">
                                                                    {{ __('Upload Photos') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            <flux:menu.item icon="paper-clip"
                                                                wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::Other->value }}')">
                                                                {{ __('Other Document') }}
                                                            </flux:menu.item>

                                                            <flux:menu.separator />
                                                            @if($vehicle->workshop_id)
                                                                <flux:menu.item icon="arrow-uturn-left"
                                                                    wire:click="openFromWorkshopConfirmModal({{ $vehicle->id }})">
                                                                    {{ __('From Workshop') }}
                                                                </flux:menu.item>
                                                            @else
                                                                <flux:menu.item icon="wrench"
                                                                    wire:click="openToWorkshopModal({{ $vehicle->id }})">
                                                                    {{ __('Send to Workshop') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            <flux:menu.separator />

                                                            <flux:menu.item icon="eye"
                                                                wire:click="openVehicleDocumentsModal({{ $vehicle->id }})">
                                                                {{ __('View Documents') }}
                                                            </flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-6">
                                            @if($vehicle->lot_number)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Lot #') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="font-semibold">{{ $vehicle->lot_number }}
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @if($vehicle->vehicle_is)
                                                <div>
                                                    <flux:text size="sm"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Condition') }}
                                                    </flux:text>
                                                    <flux:badge color="indigo" size="sm">
                                                        {{ $vehicle->vehicle_is->label() }}
                                                    </flux:badge>
                                                </div>
                                            @endif

                                            @if($vehicle->workshop)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Workshop') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="uppercase font-medium">
                                                        {{ $vehicle->workshop->name }}
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @if($vehicle->driver)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Driver') }}
                                                    </flux:text>
                                                    <div class="flex flex-col">
                                                        <flux:text size="xs" class="text-zinc-500">
                                                            {{ $vehicle->driver->company ?: __('Partner Driver') }}
                                                        </flux:text>
                                                        <flux:button variant="ghost" size="sm" x-data
                                                            x-on:click.stop="window.navigator.clipboard.writeText('{{ $vehicle->driver->phone }}'); $dispatch('ui-toast', { type: 'success', message: '{{ __('Copied') }}' })">
                                                            {{ $vehicle->driver->phone }}
                                                            <flux:icon.clipboard-document class="size-3.5" />
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            @endif

                                            @if($vehicle->gatepass_pin)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Gatepass PIN') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="font-mono font-bold text-emerald-600">
                                                        {{ $vehicle->gatepass_pin }}
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @if($vehicle->location)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Location') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="uppercase font-medium">{{ $vehicle->location }}
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @if($vehicle->auction_name)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Auction') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="uppercase font-medium">
                                                        {{ $vehicle->auction_name }}
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @php
                                                $w = (float) $vehicle->weight;
                                                $wu = strtoupper($vehicle->weight_unit ?? 'LB');
                                                $wLb = in_array($wu, ['LB', 'LBS']) ? $w : $w * 2.20462262;
                                                $wKg = in_array($wu, ['LB', 'LBS']) ? $w / 2.20462262 : $w;

                                                $m = (float) $vehicle->measurement;
                                                $mFt3 = ($m < 100 && strtoupper($vehicle->measurement_unit ?? '') === 'CBM')
                                                    ? $m * 35.3146667
                                                    : $m;
                                                $mVlb = $mFt3 * (1728 / 166);
                                            @endphp

                                            @if($w > 0)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Weight') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="font-semibold">
                                                        {{ number_format($wKg, 2) }} {{ __('Kg') }}<br>
                                                        <span
                                                            class="text-zinc-500 font-normal text-xs">{{ number_format($wLb, 2) }}
                                                            {{ __('Lb') }}</span>
                                                    </flux:text>
                                                </div>
                                            @endif

                                            @if($m > 0)
                                                <div>
                                                    <flux:text size="xs"
                                                        class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                                        {{ __('Measurement') }}
                                                    </flux:text>
                                                    <flux:text size="sm" class="font-semibold">
                                                        {{ number_format($mFt3, 2) }} {{ __('ft³') }}<br>
                                                        <span
                                                            class="text-zinc-500 font-normal text-xs">{{ number_format($mVlb, 2) }}
                                                            {{ __('Vlb') }}</span>
                                                    </flux:text>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </x-crud.panel>
                        @endforeach
                    </div>
                </div>

                {{-- Invoice & Items --}}
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
                                        <flux:text size="xs"
                                            class="uppercase tracking-widest font-bold text-zinc-400 mb-1 block">
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
                                            @foreach(InvoiceStatus::cases() as $status)
                                                @php
                                                    $currentStatus = $shipment->invoice?->status?->value ?? $shipment->invoice_status?->value;
                                                    $isAllowed = match ($status) {
                                                        InvoiceStatus::Draft => false, // Cannot go back to draft from UI currently
                                                        InvoiceStatus::Cleared => $canClear,
                                                        InvoiceStatus::Completed => $canComplete,
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
                                    <flux:select wire:model="invoice_vehicle_id" label="{{ __('Vehicle (Optional)') }}"
                                        icon="truck">
                                        <flux:select.option value="">{{ __('Container') }}</flux:select.option>
                                        @foreach($shipment->vehicles as $v)
                                            <flux:select.option :value="$v->id">
                                                {{ $v->year ?: '' }} {{ $v->make ?: '' }} {{ $v->model ?: '' }}
                                                ({{ substr($v->vin ?: '—', -6) }})
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif

                                <flux:select wire:model.live="item_description" label="{{ __('Invoice item') }}"
                                    icon="document-text">
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
                                        <flux:button type="button" variant="ghost" class="flex-none"
                                            wire:click="$set('invoiceItemId', null)">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </form>
                        @endif
                    </x-crud.panel>
                @endif

                {{-- Tracking Dual-Timeline --}}
                <div class="grid grid-cols-1 gap-8">
                    {{-- Left Track: Shipment Journey --}}
                    <x-crud.panel class="p-6">
                        <flux:heading size="lg" class="mb-6 flex items-center gap-2">
                            <flux:icon.globe-americas class="size-5 text-indigo-500" />
                            {{ __('Shipment Journey') }}
                        </flux:heading>

                        @if($shipment->trackings->isEmpty())
                            <flux:text class="text-zinc-500">{{ __('No tracking recorded for this shipment.') }}</flux:text>
                        @else
                            <div
                                class="space-y-6 relative before:absolute before:inset-y-0 before:left-3 before:w-px before:bg-zinc-200 dark:before:bg-zinc-800">
                                @foreach($shipment->trackings as $tracking)
                                    <div class="pl-8 relative">
                                        <div
                                            class="absolute left-1.5 top-1.5 size-3 rounded-full bg-indigo-500 border-2 border-white dark:border-zinc-900 shadow-sm">
                                        </div>
                                        <div class="flex items-center justify-between gap-4 mb-2">
                                            <flux:badge color="indigo" size="sm" variant="subtle">{{ $tracking->status->name }}
                                            </flux:badge>
                                            <flux:text size="xs" class="text-zinc-400 font-mono">
                                                {{ $tracking->recorded_at?->format('M d, Y H:i') }}
                                            </flux:text>
                                        </div>
                                        <flux:text size="sm">{{ $tracking->note }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-crud.panel>
                </div>

                {{-- Activity Log --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.list-bullet class="size-5 text-indigo-500" />
                        {{ __('Activity Log') }}
                    </flux:heading>

                    @if($shipment->activityLogs->isEmpty())
                        <flux:text class="text-zinc-500">
                            {{ __('No activity has been recorded for this shipment yet.') }}
                        </flux:text>
                    @else
                        @php
                            $activityLogPresenter = app(ShipmentActivityLogPresenter::class);
                        @endphp
                        <div class="space-y-3">
                            @foreach($shipment->activityLogs->sortByDesc('created_at') as $log)
                                <div
                                    class="flex items-start gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                                    <flux:avatar :name="$log->user?->name ?? 'System'" size="xs"
                                        class="bg-zinc-100! text-zinc-700!" />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <flux:text size="sm" class="font-medium">
                                                {{ $log->user?->name ?? __('System') }}
                                            </flux:text>
                                            <flux:text size="xs" class="text-zinc-500">
                                                {{ $log->created_at?->diffForHumans() }}
                                            </flux:text>
                                        </div>
                                        <flux:text size="sm" class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">
                                            {{ $activityLogPresenter->title($log) }}
                                        </flux:text>
                                        @php
                                            $activityBadges = $activityLogPresenter->badges($log);
                                        @endphp
                                        @if(count($activityBadges) > 0)
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach($activityBadges as $ab)
                                                    <flux:badge size="sm" color="zinc" variant="{{ $ab['variant'] ?? 'subtle' }}">
                                                        {{ $ab['text'] }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-crud.panel>
            </div>


        </div>
    </div>

    <flux:modal wire:model="showInvoiceStatusConfirmModal" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Change invoice status') }}</flux:heading>
                <flux:subheading>
                    @if($pendingInvoiceStatus)
                                        @php
                                            $pendingToStatus = InvoiceStatus::from($pendingInvoiceStatus);
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
                <flux:input wire:model="new_driver_phone" :label="__('Phone')" icon="phone" required
                    placeholder="+2348167768410" />
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
                    <flux:radio.group wire:model="attachVehicleTitleVehicleIs" :label="__('Vehicle condition')"
                        variant="segmented">
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
        @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
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

    @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
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
                    $isStaffOrAdmin = auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists();
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
                                            class="text-red-600 dark:text-red-400"
                                            wire:click="openDeleteDocumentConfirm({{ $doc->id }})"
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
                                            <flux:text size="xs" class="text-zinc-500 font-mono truncate"
                                                :title="basename($receiptPath)">
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
    @endif

    <flux:modal wire:model="showLogisticsModal" class="md:max-w-4xl" variant="flyout">
        <form wire:submit="saveLogistics" class="space-y-8">
            <div>
                <flux:heading size="lg">{{ __('Logistics & Booking Details') }}</flux:heading>
                <flux:subheading>{{ __('Manage shipment-level identifiers and individual vehicle metrics.') }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Shipment Fields -->
                <flux:input wire:model="logisticsForm.shipment.vessel_name" label="{{ __('Vessel Name') }}"
                    icon="ship" />
                <flux:input wire:model="logisticsForm.shipment.voyage_no" label="{{ __('Voyage #') }}" icon="hashtag" />
                <flux:input wire:model="logisticsForm.shipment.itn_number" label="{{ __('ASL ITN #') }}"
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
                <flux:input type="date" wire:model="logisticsForm.shipment.cut_off_date"
                    label="{{ __('Cut-off Date') }}" />

                <flux:input type="date" wire:model="logisticsForm.shipment.departure_date"
                    label="{{ __('ETD (Departure)') }}" />
                <flux:input type="date" wire:model="logisticsForm.shipment.arrival_date"
                    label="{{ __('ETA (Arrival)') }}" />
                <flux:input wire:model="logisticsForm.shipment.loading_pier"
                    label="{{ __('Loading Pier(Terminal)') }}" />

                <div class="md:col-span-3">
                    <flux:textarea wire:model="logisticsForm.shipment.domestic_routing"
                        label="{{ __('Domestic Routing') }}" rows="2" />
                </div>
            </div>

            <div class="border-t border-zinc-100 dark:border-zinc-800 pt-6">
                <flux:heading size="md" class="mb-4">{{ __('Vehicle Measurements (Schedule B)') }}</flux:heading>
                <div class="space-y-4">
                    @foreach($shipment->vehicles as $v)
                        <div wire:key="log-v-{{ $v->id }}"
                            class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
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
                            <div class="flex gap-2 items-end">
                                <flux:input type="number" step="0.01"
                                    wire:model="logisticsForm.vehicles.{{ $v->id }}.weight" label="{{ __('Weight') }}"
                                    class="flex-1" />
                                <flux:select wire:model="logisticsForm.vehicles.{{ $v->id }}.weight_unit" class="w-20">
                                    <flux:select.option value="KG">KG</flux:select.option>
                                    <flux:select.option value="LBS">LBS</flux:select.option>
                                </flux:select>
                            </div>
                            <div class="flex gap-2 items-end md:col-span-1">
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
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save Logistics') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-crud.page-shell>