<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\VehicleIs;
use App\Models\ActivityLog;
use App\Models\ChargeItem;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
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

    public Shipment $shipment;

    /** Invoice item form state */
    public ?int $invoiceItemId = null;
    public string $item_description = '';
    public string $item_amount = '0.00';

    public bool $showInvoiceStatusConfirmModal = false;

    public ?string $pendingInvoiceStatus = null;

    /** Driver assignment state */
    public bool $showAssignDriverModal = false;
    public bool $showCreateDriverModal = false;
    public ?int $driver_id = null;
    public string $new_driver_company = '';
    public string $new_driver_phone = '';
    public string $new_driver_email = '';

    /** Shipment documents */
    public bool $showAttachDocumentModal = false;

    public string $attachDocumentType = '';

    public string $attachDocumentNotes = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachFiles = [];

    public string $attachTitleVehicleIs = '';

    public bool $showToWorkshopModal = false;

    public ?int $toWorkshopWorkshopId = null;

    public bool $showFromWorkshopConfirmModal = false;

    public bool $showDeleteDocumentConfirmModal = false;

    public ?int $pendingDeleteShipmentDocumentId = null;

    public bool $showDeleteFileConfirmModal = false;

    public ?int $pendingDeleteShipmentDocumentFileId = null;

    public function mount(Shipment $shipment): void
    {
        $this->shipment = $shipment->load([
            'shipper',
            'shipper.user',
            'consignee',
            'vehicle',
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
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);
    }

    public function updatedShowInvoiceStatusConfirmModal(bool $value): void
    {
        if (! $value) {
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
        $invoice = $this->getInvoice();

        $validated = $this->validate([
            'item_description' => ['required', 'string', 'max:255', Rule::exists('charge_items', 'item')],
            'item_amount' => [
                Rule::requiredIf(fn () => ! $this->chargeItemForInvoiceForm()?->apply_customer_discount),
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

        $wasUpdating = (bool) $this->invoiceItemId;

        if ($wasUpdating) {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->whereKey($this->invoiceItemId)->firstOrFail();
            $fromDescription = (string) $item->description;
            $fromAmount = (float) $item->amount;
            $item->fill([
                'description' => $validated['item_description'],
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
                    'to_description' => $validated['item_description'],
                    'from_amount' => $fromAmount,
                    'to_amount' => $net,
                    'gross_amount' => $gross !== 0.0 ? $gross : null,
                    'discount_amount' => $discount !== 0.0 ? $discount : null,
                ], fn ($v) => $v !== null),
            ]);
        } else {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->create([
                'description' => $validated['item_description'],
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
                    'description' => $validated['item_description'],
                    'amount' => $net,
                    'gross_amount' => $gross !== 0.0 ? $gross : null,
                    'discount_amount' => $discount !== 0.0 ? $discount : null,
                ], fn ($v) => $v !== null),
            ]);
        }

        $this->refreshInvoiceTotals($invoice);

        $this->shipment->load('activityLogs.user');

        $this->resetInvoiceItemForm();

        $this->notification()->success(
            $wasUpdating ? __('Invoice item updated.') : __('Invoice item added.')
        );
    }

    public function editItem(int $itemId): void
    {
        $invoice = $this->getInvoice();

        /** @var InvoiceItem $item */
        $item = $invoice->items()->whereKey($itemId)->firstOrFail();

        $this->invoiceItemId = $item->id;
        $this->item_description = (string) $item->description;

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
            ], fn ($v) => $v !== null);

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
        $this->authorize('invoices.manage');

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
            ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
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

    public function openAssignDriverModal(): void
    {
        $this->authorize('shipments.update');
        $this->driver_id = $this->shipment->driver_id;
        $this->showAssignDriverModal = true;
    }

    public function assignDriver(): void
    {
        $this->authorize('shipments.update');

        $validated = $this->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $driverId = (int) $validated['driver_id'];
        $driver = Driver::query()->findOrFail($driverId);

        $this->shipment->loadMissing('shipper');

        DB::transaction(function () use ($driverId, $driver): void {
            $driverLabel = filled($driver->company)
                ? (string) $driver->company
                : (filled($driver->phone) ? (string) $driver->phone : (string) $driver->id);

            $this->shipment->update([
                'driver_id' => $driverId,
                'shipment_status' => ShipmentStatus::Dispatched,
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => ShipmentStatus::Dispatched,
                'note' => __('Driver assigned; shipment dispatched.'),
                'metadata' => [
                    'source' => 'shipment_show_assign_driver',
                    'driver_id' => $driverId,
                    'driver_label' => $driverLabel,
                    'created_by' => Auth::id(),
                ],
                'recorded_at' => now(),
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'driver_assigned',
                'properties' => [
                    'driver_id' => $driverId,
                    'driver_label' => $driverLabel,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);

            $adminRoleNames = Role::query()
                ->where('name', '!=', 'shipper')
                ->pluck('name');

            $recipientIds = User::query()
                ->role($adminRoleNames)
                ->pluck('id')
                ->merge(User::query()->whereHas('staff')->pluck('id'))
                ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
                ->when($this->shipment->shipper?->user_id, fn ($q) => $q->push($this->shipment->shipper->user_id))
                ->unique()
                ->values();

            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShipmentDispatchedNotification($this->shipment));
            }
        });

        $this->reloadShipmentPageData();

        $this->showAssignDriverModal = false;

        $this->notification()->success(__('Driver assigned successfully.'));
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
        if ($this->attachDocumentType !== ShipmentDocumentType::TitleDocument->value) {
            $this->attachTitleVehicleIs = '';

            return;
        }

        $this->shipment->loadMissing('vehicle');
        $v = $this->shipment->vehicle?->vehicle_is;
        if ($v instanceof VehicleIs) {
            $this->attachTitleVehicleIs = $v->value;
        }
    }

    public function openAttachDocumentModal(): void
    {
        $this->authorize('documents.manage');
        $this->attachDocumentType = '';
        $this->attachDocumentNotes = '';
        $this->attachFiles = [];
        $this->attachTitleVehicleIs = '';
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
                Rule::requiredIf(fn () => $this->attachDocumentType === ShipmentDocumentType::TitleDocument->value),
                'nullable',
                'string',
                Rule::enum(VehicleIs::class),
            ],
        ];

        $this->validate($rules);

        $documentType = ShipmentDocumentType::from($this->attachDocumentType);

        if ($documentType === ShipmentDocumentType::TitleDocument && $this->shipment->vehicle_id === null) {
            $this->addError('attachDocumentType', __('A vehicle must be linked to attach a title document.'));

            return;
        }

        $fromShipmentStatus = $this->shipment->shipment_status;
        $document = null;
        $fileCount = 0;

        DB::transaction(function () use ($documentType, &$document, &$fileCount, $fromShipmentStatus): void {
            if ($documentType === ShipmentDocumentType::TitleDocument) {
                $this->shipment->loadMissing('vehicle');
                $vehicle = $this->shipment->vehicle;
                if ($vehicle === null) {
                    throw new \RuntimeException('Vehicle required for title document.');
                }

                $vehicle->vehicle_is = VehicleIs::from($this->attachTitleVehicleIs);
                $vehicle->save();
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
                $path = $uploaded->store('shipment-documents/'.$this->shipment->id, 'local');
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

            $targetStatus = $documentType->targetShipmentStatusAfterAttachment();
            $toShipmentStatus = $fromShipmentStatus;

            if ($targetStatus !== null) {
                $this->shipment->shipment_status = $targetStatus;
                if ($targetStatus !== ShipmentStatus::AtWorkshop) {
                    $this->shipment->workshop_id = null;
                    $this->shipment->shipment_status_before_workshop = null;
                }

                $this->shipment->save();
                $toShipmentStatus = $this->shipment->shipment_status;
            }

            $vehicleIsLabel = null;
            if ($documentType === ShipmentDocumentType::TitleDocument) {
                $vehicleIsLabel = VehicleIs::from($this->attachTitleVehicleIs)->label();
            }

            $logProperties = [
                'shipment_document_id' => $document->id,
                'document_type' => $documentType->value,
                'document_type_label' => $documentType->label(),
                'file_count' => $fileCount,
                'file_names' => $attachedFileNames,
                'reference_no' => $this->shipment->reference_no,
                'source' => 'shipment_show_attach_document',
                'uploaded_by' => Auth::id(),
                'vehicle_is' => $vehicleIsLabel,
            ];

            if ($fromShipmentStatus !== $toShipmentStatus) {
                $logProperties['from_shipment_status'] = $fromShipmentStatus?->value;
                $logProperties['to_shipment_status'] = $toShipmentStatus?->value;
            }

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'document_attached',
                'properties' => $logProperties,
            ]);

            $noteParts = [__('Document attached: :type (:count files)', [
                'type' => $documentType->label(),
                'count' => $fileCount,
            ])];

            if ($fromShipmentStatus !== $toShipmentStatus && $fromShipmentStatus !== null && $toShipmentStatus !== null) {
                $noteParts[] = __('Status: :from → :to', [
                    'from' => $fromShipmentStatus->name,
                    'to' => $toShipmentStatus->name,
                ]);
            }

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => $this->shipment->shipment_status ?? ShipmentStatus::Pending,
                'workshop_id' => $this->shipment->workshop_id,
                'note' => implode(' — ', $noteParts),
                'metadata' => [
                    'source' => 'shipment_show_attach_document',
                    'shipment_document_id' => $document->id,
                    'document_type' => $documentType->value,
                    'document_type_label' => $documentType->label(),
                    'shipment_document_file_ids' => $attachedFileIds,
                    'file_names' => $attachedFileNames,
                    'vehicle_is' => $vehicleIsLabel,
                    'created_by' => Auth::id(),
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->shipment->refresh();
        $toStatus = $this->shipment->shipment_status;

        if ($document !== null && $fileCount > 0) {
            $this->sendDocumentAttachedNotifications(
                $document,
                $fileCount,
                $fromShipmentStatus,
                $fromShipmentStatus !== $toStatus ? $toStatus : null,
            );
        }

        $this->reloadShipmentPageData();
        $this->showAttachDocumentModal = false;
        $this->attachDocumentType = '';
        $this->attachDocumentNotes = '';
        $this->attachFiles = [];
        $this->attachTitleVehicleIs = '';

        $this->notification()->success(__('Document(s) attached.'));
    }

    public function openToWorkshopModal(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        if ($this->shipment->shipment_status === ShipmentStatus::AtWorkshop) {
            return;
        }

        $this->toWorkshopWorkshopId = $this->shipment->workshop_id;
        $this->showToWorkshopModal = true;
    }

    public function saveToWorkshop(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $validated = $this->validate([
            'toWorkshopWorkshopId' => ['required', 'integer', 'exists:workshops,id'],
        ]);

        $workshopId = (int) $validated['toWorkshopWorkshopId'];

        DB::transaction(function () use ($workshopId): void {
            $workshop = Workshop::query()->findOrFail($workshopId);
            $before = $this->shipment->shipment_status;
            $this->shipment->shipment_status_before_workshop = $before;
            $this->shipment->workshop_id = $workshopId;
            $this->shipment->shipment_status = ShipmentStatus::AtWorkshop;
            $this->shipment->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'shipment_sent_to_workshop',
                'properties' => [
                    'workshop_id' => $workshopId,
                    'workshop_name' => $workshop->name,
                    'from_shipment_status' => $before?->value,
                    'from_shipment_status_label' => $before?->name,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => ShipmentStatus::AtWorkshop,
                'workshop_id' => $workshopId,
                'note' => __('Shipment sent to workshop.'),
                'metadata' => [
                    'source' => 'shipment_show_to_workshop',
                    'workshop_name' => $workshop->name,
                    'from_shipment_status' => $before?->value,
                    'from_shipment_status_label' => $before?->name,
                    'created_by' => Auth::id(),
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->reloadShipmentPageData();
        $this->showToWorkshopModal = false;
        $this->notification()->success(__('Shipment marked at workshop.'));
    }

    public function openFromWorkshopConfirmModal(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        if ($this->shipment->shipment_status !== ShipmentStatus::AtWorkshop) {
            return;
        }

        $this->showFromWorkshopConfirmModal = true;
    }

    public function fromWorkshop(): void
    {
        $this->authorize('shipments.update');
        $this->authorizeStaffOrSuperAdmin();

        $stored = $this->shipment->shipment_status_before_workshop;
        if ($stored === null) {
            $this->notification()->error(__('Cannot restore status'), __('Previous status was not recorded.'));

            $this->showFromWorkshopConfirmModal = false;

            return;
        }

        DB::transaction(function () use ($stored): void {
            $this->shipment->shipment_status = $stored;
            $this->shipment->workshop_id = null;
            $this->shipment->shipment_status_before_workshop = null;
            $this->shipment->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'shipment_returned_from_workshop',
                'properties' => [
                    'to_shipment_status' => $stored->value,
                    'to_shipment_status_label' => $stored->name,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => $stored,
                'workshop_id' => null,
                'note' => __('Shipment returned from workshop.'),
                'metadata' => [
                    'source' => 'shipment_show_from_workshop',
                    'to_shipment_status' => $stored->value,
                    'to_shipment_status_label' => $stored->name,
                    'created_by' => Auth::id(),
                ],
                'recorded_at' => now(),
            ]);
        });

        $this->reloadShipmentPageData();
        $this->showFromWorkshopConfirmModal = false;
        $this->notification()->success(__('Workshop hold cleared; status restored.'));
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
            'vehicle',
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
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);
    }

    protected function authorizeStaffOrSuperAdmin(): void
    {
        $user = Auth::user();
        if ($user === null || (! $user->hasRole('super_admin') && ! $user->staff()->exists())) {
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
                {{-- <div class="shrink-0 rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                    <flux:icon.document-text class="size-6 text-zinc-600 dark:text-zinc-400" />
                </div> --}}
                <div class="min-w-0 flex-1">
                    <x-crud.page-header 
                        :heading="__('SHIPMENT #') . $shipment->reference_no" 
                        :subheading="__('View full shipment, tracking, and financial details.')"
                    />
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if($shipment->shipment_status)
                            <flux:badge color="indigo" variant="subtle" size="sm" icon="truck">
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
                            <flux:badge color="zinc" variant="outline" size="sm" icon="briefcase">
                                {{ $shipment->logistics_service->name ?? $shipment->logistics_service }}
                            </flux:badge>
                        @endif
                        @if($shipment->shipping_mode)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="cube">
                                {{ $shipment->shipping_mode->name ?? $shipment->shipping_mode }}
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

                        @if(\Illuminate\Support\Facades\Route::has('shipments.edit'))
                            <flux:menu.item icon="pencil-square" :href="route('shipments.edit', $shipment)" wire:navigate>
                                {{ __('Edit Shipment') }}
                            </flux:menu.item>
                        @endif

                        @can('invoices.manage')
                            <flux:menu.item icon="document-arrow-down" :href="route('shipments.invoice.download', $shipment)">
                                {{ __('Download Invoice') }}
                            </flux:menu.item>
                        @endcan


                        <flux:menu.separator />

                        <flux:menu.item icon="user-plus" wire:click="openAssignDriverModal">
                            {{ __('Assign Driver') }}
                        </flux:menu.item>

                        @can('documents.manage')
                            <flux:menu.item icon="paper-clip" wire:click="openAttachDocumentModal">
                                {{ __('Attach document') }}
                            </flux:menu.item>
                        @endcan

                        @can('shipments.update')
                            @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                                @if($shipment->shipment_status !== \App\Enums\ShipmentStatus::AtWorkshop)
                                    <flux:menu.item icon="wrench-screwdriver" wire:click="openToWorkshopModal">
                                        {{ __('To workshop') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item icon="arrow-uturn-left" wire:click="openFromWorkshopConfirmModal">
                                        {{ __('From workshop') }}
                                    </flux:menu.item>
                                @endif
                            @endif
                        @endcan

                        @can('invoices.manage')
                            <flux:menu.separator />
                            <flux:menu.submenu :heading="__('Invoice status')" icon="document-text">
                                @foreach(InvoiceStatus::cases() as $status)
                                    @if(($shipment->invoice?->status?->value ?? $shipment->invoice_status?->value) !== $status->value)
                                        <flux:menu.item wire:click="openInvoiceStatusConfirm('{{ $status->value }}')">
                                            {{ $status->name }}
                                        </flux:menu.item>
                                    @endif
                                @endforeach
                            </flux:menu.submenu>
                        @endcan
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        {{-- At-a-glance row --}}
        <x-crud.panel class="p-4">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                <div class="md:col-span-3">
                    <div class="space-y-3">
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('VIN') }}
                            </flux:text>
                            <flux:text class="font-mono">
                                {{ $shipment->vin ?? '—' }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Lot number') }}
                            </flux:text>
                            <flux:text class="font-mono">
                                {{ $shipment->vehicle?->lot_number ?? '—' }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Auction') }}
                            </flux:text>
                            <flux:text>
                                @if(filled($shipment->vehicle?->auction_name))
                                    {{ \Illuminate\Support\Str::upper($shipment->vehicle->auction_name) }}
                                @else
                                    —
                                @endif
                            </flux:text>
                        </div>
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
                                        <span class="font-normal text-zinc-600 dark:text-zinc-400">({{ $shipment->consignee->address }})</span>
                                    @endif
                                </flux:text>
                            </div>
                        @endif

                        @if(! $shipment->shipper && ! $shipment->consignee)
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('No shipper or consignee on this shipment.') }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            </div>
        </x-crud.panel>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left rail --}}
            <div class="lg:col-span-2 space-y-6">
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
                                    ({{ $shipment->originPort->state?->code ?? '—' }} - {{ $shipment->originPort->country?->iso2 ?? '—' }})
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
                                    ({{ $shipment->destinationPort->state?->code ?? '—' }} - {{ $shipment->destinationPort->country?->iso2 ?? '—' }})
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
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Gatepass PIN') }}
                            </flux:text>
                            <flux:text class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                {{ $shipment->gatepass_pin ?? '—' }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Driver') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                {{ $shipment->driver?->company ?? '—' }}
                                @if($shipment->driver?->phone)
                                    <span class="text-zinc-500">({{ $shipment->driver->phone }})</span>
                                @endif
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Location') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                {{ \Illuminate\Support\Str::upper($shipment->vehicle?->location ?? '—') }}
                            </flux:text>
                        </div>
                    </div>
                </x-crud.panel>

                {{-- Vehicle & Photos --}}
                @if($shipment->vehicle)
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <x-crud.panel class="overflow-hidden p-0 h-full min-h-[360px]">
                                @php $photos = $shipment->vehicle->copartCarPhotoUrls(); @endphp
                                @if(count($photos) > 0)
                                    <div 
                                        x-data="{ 
                                            active: 0, 
                                            photos: {{ json_encode($photos) }},
                                            next() { this.active = (this.active + 1) % this.photos.length },
                                            prev() { this.active = (this.active - 1 + this.photos.length) % this.photos.length }
                                        }" 
                                        class="relative h-full w-full group"
                                    >
                                        <img :src="photos[active]" class="h-full w-full object-cover transition-all duration-500 ease-in-out" />

                                        <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/80 to-transparent p-6">
                                            <flux:heading class="text-white! text-2xl!">
                                                {{ $shipment->vehicle->year }} {{ $shipment->vehicle->make }} {{ $shipment->vehicle->model }}
                                            </flux:heading>
                                            <div class="flex items-center gap-4 mt-2">
                                                <flux:badge color="white" variant="solid" size="sm" icon="finger-print" class="text-zinc-900!">
                                                    {{ $shipment->vehicle->vin }}
                                                </flux:badge>
                                                <flux:badge color="indigo" variant="solid" size="sm" icon="ticket">
                                                    {{ $shipment->vehicle->lot_number ?? 'N/A' }}
                                                </flux:badge>
                                            </div>
                                        </div>

                                        @if(count($photos) > 1)
                                            <button 
                                                type="button" 
                                                @click="prev()" 
                                                class="absolute left-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle"
                                            >
                                                <flux:icon.chevron-left class="size-6" />
                                            </button>
                                            <button 
                                                type="button" 
                                                @click="next()" 
                                                class="absolute right-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle"
                                            >
                                                <flux:icon.chevron-right class="size-6" />
                                            </button>

                                            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
                                                <template x-for="(photo, index) in photos" :key="index">
                                                    <div 
                                                        @click="active = index" 
                                                        :class="active === index ? 'bg-white w-6' : 'bg-white/30 hover:bg-white/50 w-2'" 
                                                        class="h-1.5 rounded-full transition-all duration-300 cursor-pointer"
                                                    ></div>
                                                </template>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="h-full flex flex-col items-center justify-center p-12 text-zinc-400 bg-zinc-50 dark:bg-zinc-900">
                                        <flux:icon.camera class="size-16 mb-4 opacity-20" />
                                        <flux:text>{{ __('No photos available for this vehicle.') }}</flux:text>
                                    </div>
                                @endif
                            </x-crud.panel>
                        </div>

                        <div class="space-y-6">
                            <x-crud.panel class="p-6 h-full">
                                <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                                    <flux:icon.document-magnifying-glass class="size-5 text-indigo-500" />
                                    {{ __('Vehicle Details') }}
                                </flux:heading>

                                <div class="grid grid-cols-1 gap-y-4 gap-x-4">
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Body') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->body_style ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Type') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->vehicle_type ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Vehicle is?') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->vehicle_is?->label() ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Damage') }}
                                        </flux:text>
                                        <flux:badge color="rose" variant="subtle" size="sm">
                                            {{ $shipment->vehicle->primary_damage ?? 'None' }}
                                        </flux:badge>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Key') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            @if($shipment->vehicle->car_keys === '1' || $shipment->vehicle->car_keys === 1)
                                                {{ __('Yes') }}
                                            @else
                                                {{ __('No') }}
                                            @endif
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Location') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->location ?? '—' }}
                                        </flux:text>
                                    </div>
                                </div>
                            </x-crud.panel>
                        </div>
                    </div>
                @endif

                {{-- Invoice & Items --}}
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
                        <div class="shrink-0 text-right">
                            @php
                                $effectiveInvoiceStatus = $shipment->invoice?->status ?? $shipment->invoice_status;
                            @endphp
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
                    </div>

                    <div class="mb-4">
                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                            {{ __('Total') }}
                        </flux:text>
                        <flux:text class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ '$'.number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
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
                                                    {{ '$'.number_format((float) $item->gross_amount, 2) }}
                                                </flux:text>
                                                <flux:text size="xs" class="text-emerald-600 dark:text-emerald-400">
                                                    −{{ '$'.number_format((float) $item->discount_amount, 2) }}
                                                </flux:text>
                                            @endif
                                            <flux:text size="sm" class="font-mono font-semibold">
                                                {{ '$'.number_format((float) $item->amount, 2) }}
                                            </flux:text>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <flux:button icon="pencil-square" size="xs" variant="ghost" wire:click="editItem({{ $item->id }})" />
                                            <flux:button
                                                icon="trash"
                                                size="xs"
                                                variant="ghost"
                                                class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-950/40"
                                                wire:click="deleteItem({{ $item->id }})"
                                            />
                                        </div>
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

                    <form wire:submit.prevent="addOrUpdateItem" class="space-y-3">
                        <flux:select wire:model.live="item_description" label="{{ __('Invoice item') }}" icon="document-text">
                            <flux:select.option value="">{{ __('Select invoice item') }}</flux:select.option>
                            @foreach(\App\Models\ChargeItem::query()->whereNotNull('item')->orderBy('item')->get() as $chargeItem)
                                <flux:select.option :value="$chargeItem->item">
                                    {{ $chargeItem->item }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="item_amount"
                            :label="__('Amount')"
                            icon="currency-dollar"
                            :readonly="$this->invoiceItemAmountReadonly"
                        />
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
                </x-crud.panel>

                {{-- Tracking Timeline --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.clock class="size-5 text-indigo-500" />
                        {{ __('Tracking History') }}
                    </flux:heading>

                    @if($shipment->trackings->isEmpty())
                        <flux:text class="text-zinc-500">
                            {{ __('No tracking events have been recorded for this shipment yet.') }}
                        </flux:text>
                    @else
                        @php
                            $trackingPresenter = app(ShipmentTrackingPresenter::class);
                        @endphp
                        <div class="space-y-4">
                            @foreach($shipment->trackings as $index => $tracking)
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="size-3 rounded-full {{ $index === 0 ? 'bg-indigo-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
                                        @if(! $loop->last)
                                            <div class="flex-1 w-px bg-zinc-200 dark:bg-zinc-800 mt-1"></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 pb-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <flux:badge 
                                                    :color="$index === 0 ? 'indigo' : 'zinc'" 
                                                    variant="subtle" 
                                                    size="sm"
                                                >
                                                    @if($tracking->status === \App\Enums\ShipmentStatus::AtWorkshop && filled($tracking->workshop?->name))
                                                        {{ $tracking->workshop->name }}
                                                    @else
                                                        {{ $tracking->status->name ?? $tracking->status }}
                                                    @endif
                                                </flux:badge>
                                                @if($tracking->workshop && $tracking->status !== \App\Enums\ShipmentStatus::AtWorkshop)
                                                    <flux:text size="xs" class="text-zinc-500">
                                                        {{ $tracking->workshop->name }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                            <flux:text size="xs" class="text-zinc-500">
                                                {{ $tracking->recorded_at?->toDayDateTimeString() ?? $tracking->created_at->toDayDateTimeString() }}
                                            </flux:text>
                                        </div>
                                        @if($tracking->note)
                                            <flux:text size="sm" class="mt-1">
                                                {{ $tracking->note }}
                                            </flux:text>
                                        @endif
                                        @php
                                            $trackingDetailBadges = $trackingPresenter->badges($tracking, $shipment);
                                        @endphp
                                        @if(count($trackingDetailBadges) > 0)
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                @foreach($trackingDetailBadges as $tb)
                                                    @if(! empty($tb['href']))
                                                        <a
                                                            href="{{ $tb['href'] }}"
                                                            class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-800 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200 dark:hover:bg-indigo-900/40"
                                                        >
                                                            {{ $tb['text'] }}
                                                        </a>
                                                    @else
                                                        <flux:badge size="sm" color="zinc" variant="{{ $tb['variant'] ?? 'subtle' }}">
                                                            {{ $tb['text'] }}
                                                        </flux:badge>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-crud.panel>

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
                                <div class="flex items-start gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                                    <flux:avatar 
                                        :name="$log->user?->name ?? 'System'" 
                                        size="xs" 
                                        class="bg-zinc-100! text-zinc-700!" 
                                    />
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

            {{-- Right rail --}}
            <div class="space-y-6">
                {{-- Documents & Auction Receipt --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.paper-clip class="size-5 text-indigo-500" />
                        {{ __('Documents') }}
                    </flux:heading>

                    @php
                        $documents = $shipment->documents->sortByDesc(fn ($d) => $d->created_at?->timestamp ?? 0)->values();
                        $isStaffOrAdmin = auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists();
                    @endphp

                    <div class="space-y-4">
                        @if($shipment->auction_receipt)
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                                    <flux:icon.document-arrow-down class="size-5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <flux:text size="sm" class="font-semibold">
                                        {{ __('Auction receipt') }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-zinc-500 font-mono truncate">
                                        {{ \Illuminate\Support\Str::limit($shipment->auction_receipt, 48) }}
                                    </flux:text>
                                </div>
                            </div>
                        @endif

                        @if($documents->isEmpty() && ! $shipment->auction_receipt)
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('No documents attached yet.') }}
                            </flux:text>
                        @elseif($documents->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($documents as $document)
                                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <flux:text size="sm" class="font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {{ $document->document_type?->label() ?? __('Document') }}
                                                </flux:text>
                                                @if(filled($document->notes))
                                                    <flux:text size="xs" class="text-zinc-500 mt-0.5 block">
                                                        {{ $document->notes }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                            @if($isStaffOrAdmin)
                                                <flux:button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    class="text-red-600 dark:text-red-400"
                                                    wire:click="openDeleteDocumentConfirm({{ $document->id }})"
                                                    wire:key="del-doc-{{ $document->id }}"
                                                />
                                            @endif
                                        </div>
                                        <ul class="space-y-2">
                                            @foreach($document->files as $docFile)
                                                @php
                                                    $ext = strtoupper(pathinfo((string) ($docFile->original_name ?? $docFile->path), PATHINFO_EXTENSION) ?: '—');
                                                @endphp
                                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                                                    <div class="min-w-0 flex-1">
                                                        <flux:text size="sm" class="font-medium truncate">
                                                            {{ $docFile->original_name ?? basename($docFile->path) }}
                                                        </flux:text>
                                                        <flux:text size="xs" class="text-zinc-500">
                                                            {{ __('Format') }}: {{ $ext }}
                                                            @if(\Illuminate\Support\Facades\Storage::disk('local')->exists($docFile->path))
                                                                · {{ number_format(\Illuminate\Support\Facades\Storage::disk('local')->size($docFile->path) / 1024, 1) }} KB
                                                            @endif
                                                        </flux:text>
                                                    </div>
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <flux:button
                                                            size="sm"
                                                            variant="outline"
                                                            icon="arrow-down-tray"
                                                            :href="\App\Support\ShipmentDocumentSignedDownloadUrl::for($shipment, $docFile)"
                                                        >
                                                            {{ __('Download') }}
                                                        </flux:button>
                                                        @if($isStaffOrAdmin)
                                                            <flux:button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                icon="trash"
                                                                class="text-red-600 dark:text-red-400"
                                                                wire:click="openDeleteFileConfirm({{ $docFile->id }})"
                                                                wire:key="del-file-{{ $docFile->id }}"
                                                            />
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-2 block">
                                {{ __('Manage attachments') }}
                            </flux:text>
                            @can('documents.manage')
                                <flux:button variant="outline" icon="arrow-up-tray" class="w-full" wire:click="openAttachDocumentModal">
                                    {{ __('Attach document') }}
                                </flux:button>
                            @else
                                <flux:text size="sm" class="text-zinc-500">{{ __('You do not have permission to attach documents.') }}</flux:text>
                            @endcan
                        </div>
                    </div>
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
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Assign Driver') }}</flux:heading>
                <flux:subheading>{{ __('Select an existing driver or add a new one, then assign to this shipment.') }}</flux:subheading>
            </div>
            @can('drivers.create')
            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" icon="plus" wire:click="openCreateDriverModal">
                    {{ __('Add New Driver') }}
                </flux:button>
            </div>
        @endcan
            <div class="space-y-3">
                <x-select
                    wire:model.live="driver_id"
                    name="driver_id"
                    :label="__('Driver')"
                    :placeholder="__('Search and select driver')"
                    option-value="id"
                    option-label="name"
                    :async-data="route('api.drivers.search')"
                    searchable
                    required
                />
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
                <flux:input wire:model="new_driver_company" :label="__('Company')" icon="building-office" placeholder="e.g. Danmazari Transport LTD" />
                <flux:input wire:model="new_driver_phone" :label="__('Phone')" icon="phone" required placeholder="+2348167768410" />
                <flux:input wire:model="new_driver_email" :label="__('Email')" icon="envelope" type="email" placeholder="driver@example.com" />
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
                    <flux:heading size="lg">{{ __('Attach document') }}</flux:heading>
                    <flux:subheading>{{ __('Select type, add files, and optional notes.') }}</flux:subheading>
                </div>

                <flux:select wire:model.live="attachDocumentType" :label="__('Document type')" required>
                    <flux:select.option value="">{{ __('Choose type…') }}</flux:select.option>
                    @foreach(\App\Enums\ShipmentDocumentType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="attachDocumentType" />

                @if($attachDocumentType === \App\Enums\ShipmentDocumentType::TitleDocument->value)
                    <flux:select wire:model="attachTitleVehicleIs" :label="__('Vehicle condition')" required>
                        <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
                        @foreach(\App\Enums\VehicleIs::cases() as $v)
                            <flux:select.option value="{{ $v->value }}">{{ $v->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="attachTitleVehicleIs" />
                @endif

                <flux:textarea wire:model="attachDocumentNotes" :label="__('Notes (optional)')" rows="2" />

                <div>
                    <flux:text class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Files') }}</flux:text>
                    <input
                        type="file"
                        wire:model="attachFiles"
                        multiple
                        class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-zinc-400 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                    />
                    <flux:error name="attachFiles" />
                    <flux:error name="attachFiles.*" />
                    <div wire:loading wire:target="attachFiles" class="mt-1 text-xs text-zinc-500">{{ __('Uploading…') }}</div>
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
                        <flux:subheading>{{ __('Choose the workshop. Current status will be saved and restored when you use “From workshop”.') }}</flux:subheading>
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
                    <flux:subheading>{{ __('Restore the shipment to its previous status and clear the workshop assignment.') }}</flux:subheading>
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
                <flux:subheading>{{ __('This deletes all files in this group from storage. This cannot be undone.') }}</flux:subheading>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteShipmentDocumentConfirmed">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model.self="showDeleteFileConfirmModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Remove file?') }}</flux:heading>
                <flux:subheading>{{ __('The file will be deleted from storage. If it was the last file, the attachment group is removed.') }}</flux:subheading>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteShipmentDocumentFileConfirmed">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</x-crud.page-shell>
