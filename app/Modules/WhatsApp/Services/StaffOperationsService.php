<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleIs;
use App\Enums\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppCategory;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Notifications\ShipmentDocumentAttachedNotification;
use App\Notifications\StampedDockReceiptNotification;
use App\Notifications\TitleDocumentAttachedNotification;
use App\Notifications\VehicleDocumentAttachedNotification;
use App\ShippingWorkflow\ShippingWorkflow;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class StaffOperationsService
{
    public function __construct(
        protected WhatsAppService $waService,
        protected ShippingWorkflow $workflow
    ) {}

    public function handleMainMenu(WhatsAppConversation $conversation, string $cleanText): void
    {
        switch ($cleanText) {
            case '1':
                $this->startTrackingFlow($conversation);
                break;
            case '2':
                $this->startDocumentFlow($conversation);
                break;
            case '3':
                $this->sendDirectiveInstructions($conversation);
                break;
            case '4':
                $this->escalateToAgent($conversation);
                break;
            default:
                $this->sendGreeting($conversation);
                break;
        }
    }

    protected function startTrackingFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'tracking_awaiting_vin']);
        $this->waService->sendMessage($conversation->phone_number, "🔍 Please send the *VIN* or *Reference number* you wish to track.\n\n_(Type 'Menu' to cancel)_");
    }

    protected function startDocumentFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'documents_awaiting_vin']);
        $this->waService->sendMessage($conversation->phone_number, "📄 Please send the *VIN* or *Reference number* for which you need documents.\n\n_(Type 'Menu' to cancel)_");
    }

    public function sendGreeting(WhatsAppConversation $conversation): void
    {
        $conversation->loadMissing(['contact' => function (MorphTo $morphTo) {
            $morphTo->morphWith([
                Staff::class => ['user'],
                Shipper::class => ['user'],
            ]);
        }]);
        $name = $conversation->contact->user->name ?? 'Staff';
        $message = "🛠 *Operations Terminal: {$name}*\n\nPlease choose an option:\n\n1️⃣ Track Shipment (Global)\n2️⃣ View Documents\n3️⃣ Submit Document (Directives)\n4️⃣ Speak to Admin/Support";
        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    public function sendDirectiveInstructions(WhatsAppConversation $conversation): void
    {
        $message = "🛠 *Operational Directives*\n\nYou can perform actions by typing a hashtag followed by the reference:\n\n".
            "📄 *Documents:*\n".
            "• `#bl [REF]` - Bill of Lading (single shipment)\n".
            "• `#bl batch` - Auto-process multiple BL\n".
            "• `#title [REF]` - Title Documents\n".
            "• `#dock [REF]` - Stamped Dock Receipt\n".
            "• `#photos [REF]` - Vehicle Photos/Videos\n".
            "• `#other [REF]` - General Docs\n\n".
            "💰 *Finances:*\n".
            "• `#invoice [status] [REF]` - Update Invoice (draft|cleared|completed)\n\n".
            "📦 *Containers:*\n".
            "• `#fill [REF]` - Mark container as filled\n".
            "• `#fill force [REF]` - Force mark as filled\n\n".
            '_(Example: `#photos ANK0001` or `#photos VIN_NUMBER`)_';

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    public function processDirective(WhatsAppConversation $conversation, string $text): void
    {
        if (! preg_match('/^#([a-z]+)\s*(.*)/is', $text, $matches)) {
            return;
        }

        $tag = strtolower($matches[1]);
        $content = trim($matches[2]);

        // Route to category if hashtag matches
        $this->handleRoutingDirective($conversation, $tag);

        switch ($tag) {
            case 'bl':
            case 'title':
            case 'dock':
            case 'photo':
            case 'other':
                $this->handleDocumentDirective($conversation, $tag, $content);
                break;
            case 'invoice':
                $this->handleInvoiceDirective($conversation, $content);
                break;
            case 'fill':
                $this->handleFillDirective($conversation, $content);
                break;
        }
    }

    protected function handleDocumentDirective(WhatsAppConversation $conversation, string $tag, string $content): void
    {
        $ref = strtoupper($content);

        if ($tag === 'bl' && ($ref === '' || $ref === 'BATCH')) {
            $this->startBulkBlFlow($conversation);

            return;
        }

        if (empty($ref)) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Please provide a Reference or VIN. (Example: `#{$tag} ANK0001`)");

            return;
        }

        $shipment = Shipment::where('reference_no', $ref)->first();
        $vehicle = null;

        if (! $shipment) {
            $vehicle = Vehicle::findByVin($ref);
            if ($vehicle) {
                $shipment = $vehicle->shipment;
            }
        }

        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Could not find shipment with reference/VIN: *{$ref}*");

            return;
        }

        $user = $conversation->contact->user;
        if (! $user) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Your staff account is not linked to a system user.');

            return;
        }

        // --- Permission & Workflow Checks ---
        switch ($tag) {
            case 'bl':
                if (! $user->can('workflow.attach_bl')) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to attach Bill of Lading.');

                    return;
                }
                if (! $this->workflow->canAttachBL($shipment)) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: Bill of Lading can only be attached in DELIVERED status.');

                    return;
                }
                break;

            case 'dock':
                if (! $user->can('workflow.attach_dock_receipt')) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to attach Dock Receipt.');

                    return;
                }
                if (! $this->workflow->canAttachDockReceipt($shipment)) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: Dock Receipt can only be attached in INLAND status onwards.');

                    return;
                }
                break;

            case 'title':
                if (! $user->can('workflow.attach_title')) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to attach Title Documents.');

                    return;
                }
                if ($shipment->isContainer() && ! $vehicle) {
                    $this->startVehicleSelectionFlow($conversation, $shipment, $tag);

                    return;
                }
                if ($shipment->shipping_mode === ShippingMode::Roro) {
                    $vehicle = $shipment->vehicles->first();
                }
                if (! $vehicle || ! $this->workflow->canAttachTitle($shipment, $vehicle)) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: Title can only be attached in DISPATCHED status.');

                    return;
                }
                break;

                break;

            case 'photo':
            case 'photos':
                if (! $user->can('workflow.upload_photos')) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to upload photos.');

                    return;
                }
                if ($shipment->isContainer() && ! $vehicle) {
                    $this->startVehicleSelectionFlow($conversation, $shipment, $tag);

                    return;
                }
                if ($shipment->shipping_mode === ShippingMode::Roro) {
                    $vehicle = $shipment->vehicles->first();
                }
                if (! $vehicle || ! $this->workflow->canAttachPhotos($shipment, $vehicle)) {
                    $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: Photos cannot be uploaded in the current state.');

                    return;
                }
                break;

            case 'other':
                // No specific permission or workflow check for 'other' docs,
                // but we check if it should be vehicle-specific for containers.
                if ($shipment->isContainer() && ! $vehicle) {
                    // For 'other', we ask if it's for a specific vehicle or the whole shipment
                    $this->startOtherDocScopeFlow($conversation, $shipment, $tag);

                    return;
                }
                break;
        }

        // For #title, we must ask for the vehicle condition (Runner/Non-Runner/Forklift) first
        if ($tag === 'title') {
            $this->startVehicleConditionFlow($conversation, $shipment, $vehicle, $tag);

            return;
        }

        // If we reach here, validation passed.
        $this->waService->sendMessage($conversation->phone_number, "✅ Validation Passed: *{$shipment->reference_no}*\n\nAction: Submit *#{$tag}*. Please upload the file(s) now.");

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'staff_awaiting_document_media',
            'data_payload' => [
                'tag' => $tag,
                'shipment_id' => $shipment->id,
                'vehicle_id' => $vehicle?->id,
            ],
        ]);
    }

    protected function startVehicleSelectionFlow(WhatsAppConversation $conversation, Shipment $shipment, string $tag): void
    {
        $message = "🚗 *Vehicle Selection*\n\nPlease select which vehicle this *#{$tag}* belongs to:\n\n";

        $vehicles = $shipment->vehicles;
        $options = [];
        foreach ($vehicles as $index => $v) {
            $num = $index + 1;
            $label = ($v->year ?? '').' '.($v->make ?? '').' ('.substr($v->vin ?? '', -6).')';
            $message .= "{$num}️⃣ {$label}\n";
            $options[$num] = $v->id;
        }

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'staff_awaiting_vehicle_selection',
            'data_payload' => [
                'tag' => $tag,
                'shipment_id' => $shipment->id,
                'options' => $options,
            ],
        ]);

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function startOtherDocScopeFlow(WhatsAppConversation $conversation, Shipment $shipment, string $tag): void
    {
        $message = "📂 *Document Scope*\n\nIs this *#other* document for:\n\n1️⃣ The entire Shipment\n2️⃣ A specific Vehicle";

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'staff_awaiting_other_scope',
            'data_payload' => [
                'tag' => $tag,
                'shipment_id' => $shipment->id,
            ],
        ]);

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function startVehicleConditionFlow(WhatsAppConversation $conversation, Shipment $shipment, ?Vehicle $vehicle, string $tag): void
    {
        $message = "⚙️ *Vehicle Condition*\n\nPlease select the condition for *{$shipment->reference_no}*:\n\n1️⃣ Runner\n2️⃣ Non-Runner\n3️⃣ Forklift\n\n_(Reply with the number)_";

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'staff_awaiting_vehicle_condition',
            'data_payload' => [
                'tag' => $tag,
                'shipment_id' => $shipment->id,
                'vehicle_id' => $vehicle?->id,
            ],
        ]);

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function handleInvoiceDirective(WhatsAppConversation $conversation, string $content): void
    {
        $parts = preg_split('/\s+/', $content, 2);
        if (count($parts) < 2) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Usage: `#invoice [draft|cleared|completed] [REF]`');

            return;
        }

        $statusStr = strtolower($parts[0]);
        $ref = strtoupper($parts[1]);

        $shipment = Shipment::where('reference_no', $ref)->first();
        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Could not find shipment with reference: *{$ref}*");

            return;
        }

        $user = $conversation->contact->user;
        if (! $user->can('invoices.manage')) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to manage invoices.');

            return;
        }

        $newStatus = match ($statusStr) {
            'draft' => InvoiceStatus::Draft,
            'cleared' => InvoiceStatus::Cleared,
            'completed' => InvoiceStatus::Completed,
            default => null,
        };

        if (! $newStatus) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Invalid status: *{$statusStr}*. Use draft, cleared, or completed.");

            return;
        }

        if ($newStatus === InvoiceStatus::Cleared && ! $this->workflow->canClearInvoice($shipment, $user)) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Workflow Error: Cannot clear this invoice yet.');

            return;
        }
        if ($newStatus === InvoiceStatus::Completed && ! $this->workflow->canCompleteInvoice($shipment, $user)) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Workflow Error: Cannot complete this invoice. Check if shipment is Loaded.');

            return;
        }

        $invoice = $shipment->invoice;
        if (! $invoice) {
            $this->waService->sendMessage($conversation->phone_number, '❌ No invoice found for this shipment.');

            return;
        }

        $fromStatus = $invoice->status;

        DB::transaction(function () use ($invoice, $newStatus, $fromStatus, $shipment, $user): void {
            $invoice->update(['status' => $newStatus]);
            $shipment->invoice_status = $newStatus;

            // Sync with Web UI logic: AWAITING_BL -> AWAITING_PAYMENT when completed
            if ($newStatus === InvoiceStatus::Completed) {
                $shipment->payment_status = PaymentStatus::AwaitingPayment;
            } elseif ($fromStatus === InvoiceStatus::Completed) {
                // Reversal: If it was completed and now it's not
                $shipment->payment_status = PaymentStatus::AwaitingBL;
            }

            $shipment->save();

            ActivityLog::create([
                'shipment_id' => $shipment->id,
                'user_id' => $user->id,
                'action' => 'invoice_status_changed',
                'properties' => [
                    'from' => $fromStatus->value,
                    'to' => $newStatus->value,
                    'from_label' => $fromStatus->name,
                    'to_label' => $newStatus->name,
                    'reference_no' => $shipment->reference_no,
                    'source' => 'whatsapp_bot',
                ],
            ]);
        });

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($shipment->shipper?->user_id) {
            $recipientIds->push($shipment->shipper->user_id);
        }

        $recipients = User::whereIn('id', $recipientIds->unique())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new InvoiceStatusChangedNotification($shipment, $invoice, $fromStatus, $newStatus));
        }

        $this->waService->sendMessage($conversation->phone_number, "✅ Invoice status updated to *{$newStatus->name}* for *{$ref}*.");
    }

    protected function handleFillDirective(WhatsAppConversation $conversation, string $content): void
    {
        $parts = preg_split('/\s+/', $content, 2);
        $force = false;
        $ref = '';

        if (count($parts) === 0) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Usage: `#fill [force] [REF]`');

            return;
        }

        if (strtolower($parts[0]) === 'force') {
            $force = true;
            $ref = strtoupper($parts[1] ?? '');
        } else {
            $ref = strtoupper($parts[0]);
        }

        if (empty($ref)) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Please provide a shipment reference. (Example: `#fill ANK0001`)');

            return;
        }

        $shipment = Shipment::where('reference_no', $ref)->first();
        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Could not find shipment with reference: *{$ref}*");

            return;
        }

        if ($shipment->shipping_mode !== ShippingMode::Container) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: The `#fill` directive is only for Container shipments.');

            return;
        }

        $user = $conversation->contact->user;

        // Permission Check
        $permission = $force ? 'workflow.force_filled' : 'workflow.mark_filled';
        if (! $user->can($permission)) {
            $this->waService->sendMessage($conversation->phone_number, "❌ Access Denied: You do not have the `{$permission}` permission.");

            return;
        }

        // Workflow Check
        if (! $this->workflow->canMarkFilled($shipment, $force)) {
            $reason = $force
                ? 'Cannot force fill in current status.'
                : 'Normal fill requires at least 4 vehicles at warehouse status and status must be OPEN.';
            $this->waService->sendMessage($conversation->phone_number, "❌ Workflow Error: {$reason}");

            return;
        }

        DB::transaction(function () use ($shipment, $force, $user): void {
            $shipment->update([
                'shipment_status' => ShipmentStatus::Booking,
            ]);

            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Booking,
                'note' => $force ? __('Container forcefully filled by admin via WhatsApp.') : __('Container marked as filled via WhatsApp.'),
                'recorded_at' => now(),
            ]);

            ActivityLog::create([
                'shipment_id' => $shipment->id,
                'user_id' => $user->id,
                'action' => 'container_filled',
                'properties' => [
                    'force' => $force,
                    'reference_no' => $shipment->reference_no,
                    'source' => 'whatsapp_bot',
                ],
            ]);
        });

        $statusMsg = $force ? '*FORCE FILLED*' : '*FILLED*';
        $this->waService->sendMessage($conversation->phone_number, "✅ Shipment {$ref} successfully {$statusMsg} and moved to *BOOKING*.");
    }

    public function handleStep(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text, ?string $mediaId): void
    {
        if ($state->current_step === 'staff_awaiting_vehicle_selection') {
            $this->handleVehicleSelection($conversation, $state, $text);

            return;
        }

        if ($state->current_step === 'staff_awaiting_other_scope') {
            $this->handleOtherScope($conversation, $state, $text);

            return;
        }

        if ($state->current_step === 'staff_awaiting_vehicle_condition') {
            $this->handleVehicleCondition($conversation, $state, $text);

            return;
        }

        if ($state->current_step === 'staff_awaiting_document_media') {
            $this->handleMediaUpload($conversation, $state, $mediaId);

            return;
        }

        if ($state->current_step === 'staff_awaiting_bulk_bl_pdf') {
            $this->handleBulkBlUpload($conversation, $state, $mediaId);

            return;
        }
    }

    protected function startBulkBlFlow(WhatsAppConversation $conversation): void
    {
        $user = $conversation->contact?->user;
        if (! $user?->can('workflow.attach_bl')) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Access Denied: You do not have permission to attach Bill of Lading.');

            return;
        }

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'staff_awaiting_bulk_bl_pdf',
            'data_payload' => [],
        ]);

        $this->waService->sendMessage(
            $conversation->phone_number,
            "📄 *Batch Bill of Lading Processing*\n\nPlease upload the multi-page BL PDF now.\n\n Repair the PDF if not recognized : https://www.ilovepdf.com/repair-pdf\n\n_Only shipments in DELIVERED status will be processed._\n\n_(Type 'Menu' to cancel)_"
        );
    }

    protected function handleBulkBlUpload(WhatsAppConversation $conversation, WhatsAppMenuState $state, ?string $mediaId): void
    {
        if (! $mediaId) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ Please upload a PDF file.\n\n_(Type 'Menu' to cancel)_");

            return;
        }

        $user = $conversation->contact?->user;
        if (! $user) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Your account is not linked to a system user.');
            $state->delete();

            return;
        }

        $localPath = $this->waService->downloadMedia($mediaId);
        if (! $localPath) {
            $this->waService->sendMessage(
                $conversation->phone_number,
                "❌ *WhatsApp Media Download Error*\n\nWhatsApp servers were unable to process or serve this file (Meta Error 131052). This occurs when a PDF file has corrupted headers or non-standard formatting.\n\n🛠 *Solution:* Please repair/normalize the file using an online PDF repair tool (such as iLovePDF Repair at https://www.ilovepdf.com/repair-pdf) and upload the repaired PDF, or type *Menu* to cancel."
            );

            return;
        }

        $this->waService->sendMessage($conversation->phone_number, '⏳ *Processing...* Please wait while I scan the document.');

        try {
            $absolutePath = Storage::disk('public')->path($localPath);
            $bulkBolService = app(BulkBolService::class);
            $results = $bulkBolService->process($absolutePath, $conversation, $user);
            $summary = $bulkBolService->formatSummary($results);

            $summary .= "\n\n👉 *Send another BL PDF to process more, or type 'Menu' to finish.*";
            $this->waService->sendMessage($conversation->phone_number, $summary);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Bulk BL processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = "❌ *Failed to Process PDF File*\n\n*Reason:* {$e->getMessage()}\n\nThe file might be corrupted, password-protected, or unreadable.\n\n👉 Please upload a valid, uncorrupted PDF file to try again, or type 'Menu' to cancel.";
            $this->waService->sendMessage($conversation->phone_number, $errorMsg);
        } finally {
            if (Storage::disk('public')->exists($localPath)) {
                Storage::disk('public')->delete($localPath);
            } elseif (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    protected function handleVehicleSelection(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        $payload = $state->data_payload;
        $options = $payload['options'];
        $vehicleId = $options[$text] ?? null;

        if (! $vehicleId) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Invalid selection. Please reply with a number from the list.');

            return;
        }

        $vehicle = Vehicle::findOrFail($vehicleId);
        $shipment = $vehicle->shipment;
        $tag = $payload['tag'];

        if ($tag === 'title' && ! $this->workflow->canAttachTitle($shipment, $vehicle)) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Invalid Action: Title can only be attached in DISPATCHED status for this vehicle.');
            $state->delete();

            return;
        }

        $this->waService->sendMessage($conversation->phone_number, "✅ Vehicle Selected: *{$vehicle->vin}*\n\nPlease upload the *#{$tag}* now.");

        $state->update([
            'current_step' => 'staff_awaiting_document_media',
            'data_payload' => array_merge($payload, ['vehicle_id' => $vehicle->id]),
        ]);
    }

    protected function handleOtherScope(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        $payload = $state->data_payload;
        $shipment = Shipment::findOrFail($payload['shipment_id']);

        if ($text === '1') {
            $this->waService->sendMessage($conversation->phone_number, '✅ Scope: *Entire Shipment*. Please upload the document(s) now.');
            $state->update([
                'current_step' => 'staff_awaiting_document_media',
                'data_payload' => array_merge($payload, ['vehicle_id' => null]),
            ]);
        } elseif ($text === '2') {
            $this->startVehicleSelectionFlow($conversation, $shipment, $payload['tag']);
        } else {
            $this->waService->sendMessage($conversation->phone_number, '❌ Please reply 1 or 2.');
        }
    }

    protected function handleVehicleCondition(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        $condition = match ($text) {
            '1' => VehicleIs::Runner,
            '2' => VehicleIs::NonRunner,
            '3' => VehicleIs::Forklift,
            default => null,
        };

        if (! $condition) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Invalid selection. Please reply 1, 2, or 3.');

            return;
        }

        $payload = $state->data_payload;
        $tag = $payload['tag'];

        $this->waService->sendMessage($conversation->phone_number, "✅ Condition set to *{$condition->label()}*. Now, please upload the *#{$tag}* file(s).");

        $state->update([
            'current_step' => 'staff_awaiting_document_media',
            'data_payload' => array_merge($payload, ['vehicle_is' => $condition->value]),
        ]);
    }

    protected function handleMediaUpload(WhatsAppConversation $conversation, WhatsAppMenuState $state, ?string $mediaId): void
    {
        if (! $mediaId) {
            $this->waService->sendMessage($conversation->phone_number, '⚠️ Please upload a file (image or PDF).');

            return;
        }

        $payload = $state->data_payload;
        $tag = $payload['tag'];
        $shipment = Shipment::findOrFail($payload['shipment_id']);
        $vehicleId = $payload['vehicle_id'] ?? null;
        $user = $conversation->contact->user;

        $localPath = $this->waService->downloadMedia($mediaId);
        if (! $localPath) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Failed to download media from WhatsApp.');

            return;
        }

        $filename = basename($localPath);
        $finalPath = ($vehicleId ? 'vehicle-documents/'.$vehicleId : 'shipment-documents/'.$shipment->id).'/'.$filename;
        if (Storage::disk('public')->exists($localPath)) {
            Storage::disk('public')->copy($localPath, $finalPath);
            Storage::disk('public')->delete($localPath);
        }

        $fromShipmentStatus = $shipment->shipment_status;
        $document = null;
        $vehicle = $vehicleId ? Vehicle::find($vehicleId) : null;

        DB::transaction(function () use ($tag, $shipment, $vehicleId, $vehicle, $user, $finalPath, $filename, $payload, &$document): void {
            if (! $vehicleId) {
                // Shipment Level
                $docType = match ($tag) {
                    'bl' => ShipmentDocumentType::BillOfLading,
                    'dock' => ShipmentDocumentType::StampDockReceipt,
                    default => ShipmentDocumentType::Other,
                };

                $document = $shipment->documents()->create([
                    'document_type' => $docType,
                    'notes' => 'Uploaded via WhatsApp Bot',
                ]);

                $document->files()->create([
                    'path' => $finalPath,
                    'original_name' => $filename,
                    'uploaded_by' => $user->id,
                ]);

                if ($docType === ShipmentDocumentType::StampDockReceipt) {
                    $shipment->update(['shipment_status' => ShipmentStatus::Delivered]);
                } elseif ($docType === ShipmentDocumentType::BillOfLading) {
                    $shipment->update(['shipment_status' => ShipmentStatus::Loaded]);
                }

                ShipmentTracking::create([
                    'shipment_id' => $shipment->id,
                    'status' => $shipment->shipment_status,
                    'note' => "Document attached via WhatsApp: {$docType->label()}",
                    'recorded_at' => now(),
                ]);

                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id,
                    'action' => 'shipment_document_attached',
                    'properties' => ['document_type' => $docType->value, 'source' => 'whatsapp_bot'],
                ]);

            } else {
                $docType = match ($tag) {
                    'title' => VehicleDocumentType::TitleDocument,
                    'photo' => VehicleDocumentType::PhotosAndVideos,
                    default => VehicleDocumentType::Other,
                };

                $document = $vehicle->vehicleDocuments()->create([
                    'document_type' => $docType,
                    'notes' => 'Uploaded via WhatsApp Bot',
                ]);

                if ($tag === 'title' && isset($payload['vehicle_is'])) {
                    $vehicle->update(['vehicle_is' => $payload['vehicle_is']]);
                }

                $document->files()->create([
                    'path' => $finalPath,
                    'original_name' => $filename,
                    'uploaded_by' => $user->id,
                ]);

                $statusNote = "Document attached via WhatsApp: {$docType->label()}";
                if ($docType === VehicleDocumentType::TitleDocument) {
                    if ($shipment->shipping_mode === ShippingMode::Roro) {
                        $shipment->update(['shipment_status' => ShipmentStatus::Booking]);

                        // RoRo: Update all vehicles to DISPATCHED (Matching Web UI Line 1054)
                        $shipment->vehicles()->update(['tracking_status' => VehicleStatus::Dispatched]);

                        ShipmentTracking::create([
                            'shipment_id' => $shipment->id,
                            'status' => ShipmentStatus::Booking,
                            'note' => $statusNote,
                            'recorded_at' => now(),
                        ]);
                    } else {
                        $vehicle->updateStatus(VehicleStatus::Inland, $statusNote);
                    }
                } elseif ($docType === VehicleDocumentType::PhotosAndVideos) {
                    $vehicle->updateStatus(VehicleStatus::AtWarehouse, $statusNote);
                }

                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id,
                    'action' => 'vehicle_document_attached',
                    'properties' => ['vehicle_id' => $vehicle->id, 'document_type' => $docType->value, 'source' => 'whatsapp_bot'],
                ]);
            }
        });

        // Notifications
        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        if ($tag === 'dock' || $tag === 'title') {
            if ($shipment->shipper?->user_id) {
                $recipientIds->push($shipment->shipper->user_id);
            }
        }
        $recipients = User::whereIn('id', $recipientIds->unique())->get();
        if ($recipients->isNotEmpty()) {
            if ($tag === 'dock') {
                Notification::send($recipients, new StampedDockReceiptNotification($shipment, $document));
            } elseif ($tag === 'title') {
                Notification::send($recipients, new TitleDocumentAttachedNotification($vehicle, $document));
            } elseif ($vehicleId) {
                // Vehicle-level document (photo, other) — use generic label, not "Title Document"
                $label = match ($tag) {
                    'photo', 'photos' => 'Vehicle Photos/Videos',
                    default => 'Vehicle Document',
                };
                Notification::send($recipients, new VehicleDocumentAttachedNotification($vehicle, $document, $label));
            } else {
                // Shipment-level document (bl, other for whole shipment) — $document is ShipmentDocument
                $staffOnlyRecipients = User::whereIn('id', $this->staffAndAdminNotificationRecipientIds())->get();
                Notification::send($staffOnlyRecipients, new ShipmentDocumentAttachedNotification(
                    $shipment,
                    $document,
                    1,
                    $fromShipmentStatus ?? $shipment->shipment_status,
                    $shipment->shipment_status
                ));
            }
        }

        $this->waService->sendMessage($conversation->phone_number, "✅ *#{$tag}* has been successfully attached to *{$shipment->reference_no}*.");
        $state->delete();
    }

    protected function handleRoutingDirective(WhatsAppConversation $conversation, string $tag): void
    {
        $category = WhatsAppCategory::where('hashtag', $tag)->first();
        if ($category) {
            $conversation->update(['category_id' => $category->id]);
            $this->waService->sendMessage($conversation->phone_number, "📁 This conversation has been routed to *{$category->name}*.");
        }
    }

    protected function escalateToAgent(WhatsAppConversation $conversation): void
    {
        $conversation->update(['status' => 'escalated']);
        $this->waService->sendMessage($conversation->phone_number, "👨‍💼 *Agent Escalation*\n\nAn agent will be with you shortly. You can now send your message directly.\n\n_(Type *End* at any time to return to the main menu)_");
    }

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
}
