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
use App\Notifications\DriverAssignedNotification;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Notifications\LogisticsBookingNotification;
use App\Notifications\ShipmentDispatchedNotification;
use App\Notifications\ShipmentDocumentAttachedNotification;
use App\Notifications\ShipmentLoadedNotification;
use App\Notifications\StampedDockReceiptNotification;
use App\Notifications\TitleDocumentAttachedNotification;
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
use App\Concerns\HandlesShipmentPayments;
use WireUi\Traits\WireUiActions;

new #[Title('Shipment Details')] class extends Component {
    use WireUiActions;
    use HandlesShipmentPayments;
    use WithFileUploads;

    public bool $showMakePaymentModal = false;

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

    #[Computed]
    public function dueShipmentDocumentType(): ?ShipmentDocumentType
    {
        $hasDockReceipt = $this->shipment->documents()->where('document_type', ShipmentDocumentType::StampDockReceipt)->exists();
        $hasBL = $this->shipment->documents()->where('document_type', ShipmentDocumentType::BillOfLading)->exists();

        if (!$hasDockReceipt && auth()->user()->can('workflow.attach_dock_receipt') && $this->workflow()->canAttachDockReceipt($this->shipment)) {
            return ShipmentDocumentType::StampDockReceipt;
        }

        if (!$hasBL && auth()->user()->can('workflow.attach_bl') && $this->workflow()->canAttachBL($this->shipment)) {
            return ShipmentDocumentType::BillOfLading;
        }

        return null;
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

            if ($newStatus === InvoiceStatus::Completed) {
                $this->shipment->payment_status = \App\Enums\PaymentStatus::AwaitingPayment;
            } elseif ($fromStatus === InvoiceStatus::Completed) {
                // Reversal: If it was completed and now it's not
                $this->shipment->payment_status = \App\Enums\PaymentStatus::AwaitingBL;
            }

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
        if ($this->shipment->shipper?->user_id !== null) {
            $recipientIds->push($this->shipment->shipper->user_id);
        }

        $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();

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

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($this->shipment->shipper?->user_id !== null) {
            $recipientIds->push($this->shipment->shipper->user_id);
        }

        $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DriverAssignedNotification($this->shipment, $driver));
        }

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

        if ($documentType === null) {
            $documentType = $this->dueShipmentDocumentType?->value;
        }

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

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();

        if ($documentType === ShipmentDocumentType::StampDockReceipt) {
            if ($this->shipment->shipper?->user_id !== null) {
                $recipientIds->push($this->shipment->shipper->user_id);
            }
            $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new StampedDockReceiptNotification($this->shipment, $document));
            }
        } else {
            // Notify only staff for other document types (like BL)
            $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShipmentDocumentAttachedNotification(
                    $this->shipment,
                    $document,
                    $fileCount,
                    $fromShipmentStatus,
                    $this->shipment->shipment_status
                ));
            }
        }

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

            if ($documentType === VehicleDocumentType::TitleDocument) {
                $recipientIds = $this->staffAndAdminNotificationRecipientIds();
                if ($this->shipment->shipper?->user_id !== null) {
                    $recipientIds->push($this->shipment->shipper->user_id);
                }

                $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new TitleDocumentAttachedNotification($vehicle, $document));
                }
            }
            if ($this->shipment->isContainer()) {
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

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($this->shipment->shipper?->user_id !== null) {
            $recipientIds = $recipientIds->push($this->shipment->shipper->user_id);
        }

        $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LogisticsBookingNotification($this->shipment));
        }

        if ($this->shipment->shipper?->default_driver_id) {
            $this->shipment->shipper->defaultDriver->notify(new LogisticsBookingNotification($this->shipment));
        }
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

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($this->shipment->shipper?->user_id !== null) {
            $recipientIds = $recipientIds->push($this->shipment->shipper->user_id);
        }

        $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ShipmentLoadedNotification($this->shipment));
        }

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
            'shipper.wallet',
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

    public function payViaWallet(): void
    {
        if ($this->processShipmentPayment($this->shipment)) {
            $this->showMakePaymentModal = false;
            $this->shipment->refresh();
        }
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
                                :icon="$shipment->isContainer() ? 'container' : 'car-front'">
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

                        @if($this->workflow()->canDownloadInvoice($shipment, auth()->user()))
                            @can('workflow.download_invoice')
                                <flux:menu.item icon="document-arrow-down"
                                    :href="route('shipments.invoice.download', $shipment)">
                                    {{ __('Download Invoice') }}
                                </flux:menu.item>
                            @endcan
                        @endif

                        @if($shipment->shipment_status === \App\Enums\ShipmentStatus::Booking)
                            @can('workflow.manage_logistics')
                                <flux:menu.item icon="car-front" wire:click="editLogistics">
                                    {{ __('Booking & Logistics') }}
                                </flux:menu.item>
                            @endcan
                        @endif

                        @if($this->workflow()->canDownloadDockReceipt($shipment, auth()->user()))
                            @can('workflow.download_dock_receipt')
                                <flux:menu.item icon="document-duplicate"
                                    :href="route('shipments.dock-receipt.download', $shipment)">
                                    {{ __('Download Dock Receipt') }}
                                </flux:menu.item>
                            @endcan
                        @endif

                        @can('documents.manage')
                            <flux:menu.item icon="paper-clip" wire:click="openAttachDocumentModal">
                                {{ $this->dueShipmentDocumentType ? __('Attach :type', ['type' => $this->dueShipmentDocumentType->label()]) : __('Attach documents') }}
                            </flux:menu.item>
                        @endcan
                        <flux:menu.item icon="eye" wire:click="openShipmentDocumentsModal">
                            {{ __('View Documents') }}
                        </flux:menu.item>


                        <flux:menu.separator />

                        @if(!$shipment->isLocked())
                            @if($shipment->isContainer())
                                @if($this->workflow()->canMarkFilled($shipment))
                                    <flux:menu.item icon="check-circle" wire:click="markContainerFilled(false)">
                                        {{ __('Mark Filled') }}
                                    </flux:menu.item>
                                @endif
                                @if(auth()->user()?->hasRole('super_admin') && $this->workflow()->canMarkFilled($shipment, true))
                                    <flux:menu.item icon="exclamation-triangle" wire:click="markContainerFilled(true)"
                                        variant="danger">
                                        {{ __('Force Fill') }}
                                    </flux:menu.item>
                                @endif
                            @endif

                        @endif
                    </flux:menu>
                </flux:dropdown>

                @if($shipment->payment_status === \App\Enums\PaymentStatus::AwaitingPayment && (auth()->user()->can('shipments.pay') || auth()->user()->hasRole('super_admin')))
                    <flux:button variant="primary" icon="wallet" wire:click="$set('showMakePaymentModal', true)">
                        {{ __('Make Payment') }}
                    </flux:button>
                @endif
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
                            <flux:icon.car-front class="size-5 text-indigo-500" />
                            {{ __('Vehicles in Shipment') }} ({{ count($shipment->vehicles) }})
                        </flux:heading>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        @foreach($shipment->vehicles as $vehicle)
                            @include('pages.shipments.partials.vehicle-card', ['vehicle' => $vehicle, 'shipment' => $shipment])
                        @endforeach
                    </div>
                </div>

                {{-- Invoice & Items --}}
                @if(!auth()->user()->hasRole('shipper'))
                    @include('pages.shipments.partials.invoice-panel', ['shipment' => $shipment])
                @endif

                @include('pages.shipments.partials.timeline', ['shipment' => $shipment])

                @can('shipments.view_activities')
                    @include('pages.shipments.partials.activity-feed', ['shipment' => $shipment])
                @endcan
            </div>


        </div>
    </div>

    @include('pages.shipments.partials.modals', ['shipment' => $shipment])

    {{-- Make Payment Modal --}}
    <flux:modal name="make-payment" wire:model="showMakePaymentModal" variant="filled" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Complete Payment') }}</flux:heading>
                <flux:subheading>{{ __('Pay for your shipment using your wallet balance.') }}</flux:subheading>
            </div>

            <div
                class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                <div class="flex justify-between items-center">
                    <flux:text>{{ __('Invoice Amount') }}</flux:text>
                    <flux:text class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                        {{ '$' . number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                    </flux:text>
                </div>
                <div class="flex justify-between items-center">
                    <flux:text>{{ __('Your Wallet Balance') }}</flux:text>
                    <flux:text class="font-mono font-semibold">
                        {{ '$' . number_format((float) ($shipment->shipper?->wallet?->balance ?? 0), 2) }}
                    </flux:text>
                </div>
            </div>

            @php
                $balance = (float) ($shipment->shipper?->wallet?->balance ?? 0);
                $total = (float) ($shipment->invoice?->total_amount ?? 0);
                $canPay = $balance >= $total;
            @endphp

            @if(!$canPay)
                <flux:callout variant="danger" icon="exclamation-circle">
                    {{ __('Your balance is insufficient. Please top up your wallet to proceed.') }}
                </flux:callout>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Funds will be deducted immediately from your wallet.') }}
                </flux:callout>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showMakePaymentModal', false)">{{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" icon="check" wire:click="payViaWallet" :disabled="!$canPay">
                    {{ __('Confirm & Pay') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-crud.page-shell>