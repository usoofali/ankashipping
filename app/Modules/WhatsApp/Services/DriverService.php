<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Support\ShipmentPdfSupport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DriverService
{
    use ShipmentPdfSupport;

    public function __construct(protected WhatsAppService $waService) {}

    // Flow 1
    public function sendPendingDockReceipts(WhatsAppConversation $conversation): void
    {
        $contactId = $conversation->contact_id;

        $shipments = Shipment::where('shipping_mode', ShippingMode::Roro)
            ->where('shipment_status', ShipmentStatus::Inland)
            ->whereHas('vehicles', fn ($query) => $query->where('driver_id', $contactId))
            ->get();

        if ($shipments->isEmpty()) {
            $this->waService->sendMessage($conversation->phone_number, 'You currently have no pending RoRo Dock Receipts.');

            return;
        }

        $this->waService->sendMessage($conversation->phone_number, '📤 *Generating your pending Dock Receipts...* Please wait.');

        foreach ($shipments as $shipment) {
            $filename = "DockReceipt_{$shipment->reference_no}.pdf";
            $tempPath = 'whatsapp-temp/'.Str::uuid().'_'.$filename;

            try {
                $pdfContent = $this->generateDockReceiptPdf($shipment)->output();
                Storage::disk('public')->put($tempPath, $pdfContent);

                $url = Storage::disk('public')->url($tempPath);

                $msg = "📄 *Dock Receipt for Shipment:* `{$shipment->reference_no}`\n\n_(Tip: Long-press the reference number to copy it)_";
                $this->waService->sendMessage($conversation->phone_number, $msg);
                $this->waService->sendDocument($conversation->phone_number, $url, $filename);
            } catch (\Throwable $e) {
                $this->waService->sendMessage($conversation->phone_number, "⚠️ *Dock Receipt* for {$shipment->reference_no} could not be generated at this time.");
            }
        }
    }

    // Flow 2
    public function startSubmitDockReceiptFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'driver_awaiting_vin']);

        $msg = "Please enter the *Shipment Reference* (e.g. ANK00010) or *Vehicle VIN* for the delivery.\n\n💡 _Tip: You can copy and paste the reference number sent with the original Dock Receipt._";
        $this->waService->sendMessage($conversation->phone_number, $msg);
    }

    public function handleStep(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text, ?string $mediaId): void
    {
        switch ($state->current_step) {
            case 'driver_awaiting_vin':
                $this->handleAwaitingVin($conversation, $state, $text);
                break;
            case 'driver_awaiting_receipt_file':
                $this->handleAwaitingReceiptFile($conversation, $state, $mediaId);
                break;
        }
    }

    protected function handleAwaitingVin(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        $query = trim($text);

        $shipment = Shipment::where('reference_no', $query)->first();
        if (! $shipment) {
            $vehicle = Vehicle::where('vin', $query)->first();
            if ($vehicle && $vehicle->shipment_id) {
                $shipment = Shipment::find($vehicle->shipment_id);
            }
        }

        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference and try again.");

            return;
        }

        // Verify assignment
        $contactId = $conversation->contact_id;
        $isAssigned = $shipment->vehicles()->where('driver_id', $contactId)->exists();

        if (! $isAssigned) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Access Denied*\n\nYou are not assigned to this shipment.");

            return;
        }

        if ($shipment->shipment_status !== ShipmentStatus::Inland) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ This shipment is currently in *{$shipment->shipment_status?->name}* status, not INLAND. Dock receipts can only be submitted for INLAND shipments.");

            return;
        }

        $state->update([
            'current_step' => 'driver_awaiting_receipt_file',
            'data_payload' => ['shipment_id' => $shipment->id],
        ]);

        $this->waService->sendMessage($conversation->phone_number, "📸 Please upload a *photo or PDF* of the Stamped Dock Receipt for {$shipment->reference_no}.");
    }

    protected function handleAwaitingReceiptFile(WhatsAppConversation $conversation, WhatsAppMenuState $state, ?string $mediaId): void
    {
        if (! $mediaId) {
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Please upload a valid image or PDF document.');

            return;
        }

        $shipmentId = $state->data_payload['shipment_id'] ?? null;
        if (! $shipmentId) {
            $state->delete();
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Session expired. Please start over.');

            return;
        }

        $shipment = Shipment::find($shipmentId);

        $this->waService->sendMessage($conversation->phone_number, '⏳ Downloading document...');

        $tempPath = $this->waService->downloadMedia($mediaId);

        if (! $tempPath) {
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Failed to download the document. Please try again.');

            return;
        }

        // Update state with the downloaded path so the agent can approve it
        $payload = $state->data_payload;
        $payload['receipt_path'] = $tempPath;
        $state->update(['data_payload' => $payload]);

        // Escalate conversation to agent
        $conversation->update(['status' => 'escalated']);

        $internalMessage = "🔔 *Agent Review Needed* Driver has submitted a Stamped Dock Receipt for Shipment #{$shipment->reference_no}. Please review the document above. Reply with `Approve` or `Reject: [reason]` to process.";

        // Save the internal message to the chat
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'bot',
            'message_text' => $internalMessage,
            'status' => 'sent', // Or delivered to show it internally
        ]);

        $this->waService->sendMessage($conversation->phone_number, '✅ Document received! An agent will review and verify your submission shortly.');
    }
}
