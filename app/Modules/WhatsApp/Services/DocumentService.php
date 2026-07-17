<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentStatus;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Support\ShipmentPdfSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    use ShipmentPdfSupport;

    public function __construct(
        protected WhatsAppService $waService,
        protected WhatsAppDocumentService $docService
    ) {}

    /**
     * Step 1: User provides VIN/Ref → show document selection menu.
     */
    public function sendDocuments(WhatsAppConversation $conversation, string $query): void
    {
        $query = trim($query);

        // 1. Find Shipment
        $shipment = Shipment::where('reference_no', $query)
            ->with(['invoice', 'vehicles.vehicleDocuments.files', 'documents.files'])
            ->first();

        if (! $shipment) {
            $vehicle = Vehicle::findByVin($query);
            if ($vehicle && $vehicle->shipment_id) {
                $shipment = Shipment::with(['invoice', 'vehicles.vehicleDocuments.files', 'documents.files'])
                    ->find($vehicle->shipment_id);
            }
        }

        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference and try again.");

            return;
        }

        // 2. Security Check
        if (! $this->userHasAccess($conversation, $shipment)) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference and try again.");

            return;
        }

        // 3. Build available document options
        $options = $this->buildAvailableOptions($shipment);

        if (empty($options)) {
            $this->waService->sendMessage(
                $conversation->phone_number,
                "📂 *No Documents Available*\n\nThere are currently no documents ready for *{$shipment->reference_no}*.\n\nDocuments become available as your shipment progresses.\n\n💡 _Send another Reference or VIN, or type *'Menu'* to go back._"
            );

            return;
        }

        // 4. Show document selection menu
        $menu = "📋 *Documents for {$shipment->reference_no}*\n";
        $menu .= "*Status:* {$shipment->shipment_status?->name}\n";
        $menu .= "━━━━━━━━━━━━━━━━━━━\n";
        $menu .= "*Please choose a document:*\n\n";

        foreach ($options as $number => $option) {
            $menu .= "{$number}️⃣ {$option['label']}\n";
        }

        $menu .= "\n0️⃣ *Send All Documents*";
        $menu .= "\n\n💡 _Type 'Menu' to cancel._";

        $this->waService->sendMessage($conversation->phone_number, $menu);

        // 5. Update state: wait for selection
        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'documents_awaiting_selection',
            'data_payload' => [
                'shipment_id' => $shipment->id,
                'options' => $options,
            ],
        ]);
    }

    /**
     * Step 2: User selects a document number → send it.
     */
    public function handleSelection(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        $payload = $state->data_payload ?? [];
        $shipmentId = $payload['shipment_id'] ?? null;
        $options = $payload['options'] ?? [];

        if (! $shipmentId) {
            $conversation->menuState()->delete();
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Session expired. Please re-enter your Reference or VIN.');

            return;
        }

        $shipment = Shipment::with(['invoice', 'vehicles.vehicleDocuments.files', 'documents.files'])
            ->find($shipmentId);

        if (! $shipment) {
            $conversation->menuState()->delete();
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Shipment not found. Please try again.');

            return;
        }

        $choice = trim(preg_replace('/[^0-9]/', '', $text));

        // Send all
        if ($choice === '0') {
            $this->waService->sendMessage($conversation->phone_number, '📤 *Sending all documents...* Please wait.');
            foreach ($options as $option) {
                $this->dispatchOption($conversation, $shipment, $option);
            }
            $this->waService->sendMessage(
                $conversation->phone_number,
                "✅ *All documents sent.*\n\n💡 _Send another Reference or VIN, or type *'Menu'* to go back._"
            );
            // Revert to vin-awaiting so they can search another
            $conversation->menuState()->updateOrCreate([], [
                'current_step' => 'documents_awaiting_vin',
                'data_payload' => [],
            ]);

            return;
        }

        // Send specific document
        if (isset($options[$choice])) {
            $option = $options[$choice];
            $this->waService->sendMessage($conversation->phone_number, "📤 Sending *{$option['label']}*...");
            $this->dispatchOption($conversation, $shipment, $option);

            // Stay in selection menu for another pick
            $this->waService->sendMessage(
                $conversation->phone_number,
                "✅ *{$option['label']}* sent.\n\nReply with another number for more documents, or type *'Menu'* to go back."
            );
        } else {
            $this->waService->sendMessage(
                $conversation->phone_number,
                '❓ Invalid choice. Please reply with a number from the list, or *0* for all documents.'
            );
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{label: string, type: string, meta: mixed}>
     */
    protected function buildAvailableOptions(Shipment $shipment): array
    {
        $advancedStatuses = [
            ShipmentStatus::Inland,
            ShipmentStatus::Delivered,
            ShipmentStatus::Loaded,
            ShipmentStatus::Completed,
        ];

        $options = [];
        $i = 1;

        // Invoice
        if ($shipment->invoice && $shipment->invoice->status === InvoiceStatus::Completed) {
            $options[(string) $i++] = ['label' => 'Invoice (PDF)', 'type' => 'invoice', 'meta' => null];
        }

        // Dock Receipt
        if (in_array($shipment->shipment_status, $advancedStatuses, true)) {
            $options[(string) $i++] = ['label' => 'Dock Receipt (PDF)', 'type' => 'dock_receipt', 'meta' => null];
        }

        // Auction Receipts (one per vehicle)
        foreach ($shipment->vehicles as $vehicle) {
            if (filled($vehicle->auction_receipt)) {
                $label = "Auction Receipt — {$vehicle->year} {$vehicle->make} {$vehicle->model}";
                $options[(string) $i++] = ['label' => $label, 'type' => 'auction_receipt', 'meta' => $vehicle->id];
            }
        }

        // Uploaded Shipment Documents (grouped as one option)
        $shipmentFiles = $shipment->documents->flatMap(fn ($d) => $d->files);
        if ($shipmentFiles->isNotEmpty()) {
            $options[(string) $i++] = ['label' => "Shipping Documents (×{$shipmentFiles->count()} files)", 'type' => 'shipment_docs', 'meta' => null];
        }

        // Uploaded Vehicle Documents (grouped as one option)
        $vehicleFiles = $shipment->vehicles->flatMap(fn ($v) => $v->vehicleDocuments->flatMap(fn ($d) => $d->files));
        if ($vehicleFiles->isNotEmpty()) {
            $options[(string) $i++] = ['label' => "Vehicle Documents (×{$vehicleFiles->count()} files)", 'type' => 'vehicle_docs', 'meta' => null];
        }

        return $options;
    }

    protected function dispatchOption(WhatsAppConversation $conversation, Shipment $shipment, array $option): void
    {
        match ($option['type']) {
            'invoice' => $this->sendDocumentPayload(
                $conversation,
                $this->docService->getInvoicePayload($shipment)
            ),
            'dock_receipt' => $this->sendDocumentPayload(
                $conversation,
                $this->docService->getDockReceiptPayload($shipment)
            ),
            'auction_receipt' => $this->sendAuctionReceipt($conversation, $shipment, (int) $option['meta']),
            'shipment_docs' => $this->sendUploadedFiles(
                $conversation,
                $shipment->documents->flatMap(fn ($d) => $d->files)
            ),
            'vehicle_docs' => $this->sendUploadedFiles(
                $conversation,
                $shipment->vehicles->flatMap(fn ($v) => $v->vehicleDocuments->flatMap(fn ($d) => $d->files))
            ),
            default => null,
        };
    }

    protected function sendAuctionReceipt(WhatsAppConversation $conversation, Shipment $shipment, int $vehicleId): void
    {
        $vehicle = $shipment->vehicles->firstWhere('id', $vehicleId);
        if (! $vehicle || ! filled($vehicle->auction_receipt)) {
            return;
        }

        $path = $vehicle->auction_receipt;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $url = Storage::disk('public')->url($path);
        $this->waService->sendDocument($conversation->phone_number, $url, "AuctionReceipt_{$vehicle->vin}.{$ext}");
    }

    protected function sendUploadedFiles(WhatsAppConversation $conversation, Collection $files): void
    {
        foreach ($files as $file) {
            $url = Storage::disk('public')->url($file->path);
            $this->waService->sendDocument($conversation->phone_number, $url, $file->original_name ?? basename($file->path));
        }
    }

    protected function sendDocumentPayload(WhatsAppConversation $conversation, array $payload): void
    {
        $this->waService->sendDocument(
            $conversation->phone_number,
            $payload['url'],
            $payload['name']
        );
    }

    protected function userHasAccess(WhatsAppConversation $conversation, Shipment $shipment): bool
    {
        $contactType = $conversation->contact_type;
        $contactId = $conversation->contact_id;

        if ($contactType === Staff::class) {
            return true;
        }

        if ($contactType === Shipper::class) {
            return (int) $shipment->shipper_id === (int) $contactId;
        }

        if ($contactType === Driver::class) {
            return $shipment->vehicles()->where('driver_id', $contactId)->exists();
        }

        return false;
    }
}
