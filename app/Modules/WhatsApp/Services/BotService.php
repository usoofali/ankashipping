<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Models\Driver;
use App\Models\Shipper;
use App\Models\Staff;
use App\Modules\WhatsApp\Models\WhatsAppCategory;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotService
{
    public function __construct(
        protected WhatsAppService $waService,
        protected ShipmentService $shipmentService,
        protected DocumentService $documentService,
        protected PreAlertService $preAlertService,
        protected WalletService $walletService,
        protected DriverService $driverService,
        protected StaffOperationsService $staffService
    ) {}

    public function handle(WhatsAppConversation $conversation, array $messageData): void
    {
        $text = trim($messageData['text']['body'] ?? '');
        $lowerText = strtolower($text);
        $isStaff = $conversation->contact_type === Staff::class;

        // NEW: Staff Operations Directives (#bl, #title, etc)
        if ($isStaff && str_starts_with($text, '#')) {
            $this->staffService->processDirective($conversation, $text);

            return;
        }

        // Allow user to manually end an escalation to return to the bot
        if ($conversation->status === 'escalated' && ($lowerText === 'end' || $lowerText === 'menu')) {
            $conversation->update([
                'status' => 'bot',
                'agent_id' => null,
            ]);

            $conversation->menuState()->delete();
            $this->sendGreeting($conversation);

            return;
        }

        if ($conversation->status === 'escalated' || str_starts_with($text, '#')) {
            return;
        }

        if (! $conversation->contact_id) {
            $this->sendUnregisteredNotice($conversation);

            return;
        }

        $mediaId = $messageData['image']['id'] ?? $messageData['document']['id'] ?? null;
        $state = $conversation->menuState;

        if ($text === '1' && $conversation->status === 'pending_flush') {
            $this->flushBufferedMessages($conversation);

            return;
        }

        if ($text === '2' && $conversation->status === 'pending_flush') {
            // Mark all pending messages as skipped so they don't re-surface on next contact.
            $conversation->messages()->where('status', 'pending')->update(['status' => 'skipped']);
            $conversation->update(['status' => 'bot']);
            $this->sendGreeting($conversation);

            return;
        }

        if ($state) {
            $this->processState($conversation, $state, $text, $mediaId);

            return;
        }

        $this->handleMainMenu($conversation, $text);
    }

    protected function sendUnregisteredNotice(WhatsAppConversation $conversation): void
    {
        $registerUrl = url('/register');
        $message = "Welcome to *ANKA Shipping & Logistics* 🚢
        
We noticed that this phone number is not currently registered in our system. To use our automated services, please:

1️⃣ *Register at:* {$registerUrl}
2️⃣ Ensure you are messaging us from the same phone number used during registration.

Thank you!";

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function handleMainMenu(WhatsAppConversation $conversation, string $text): void
    {
        $cleanText = trim(preg_replace('/[^0-9]/', '', $text));

        Log::channel('whatsapp')->info('Menu Selection Processed', [
            'raw' => $text,
            'clean' => $cleanText,
        ]);

        if ($conversation->contact_type === Staff::class) {
            $this->staffService->handleMainMenu($conversation, $cleanText);

            return;
        }

        if ($conversation->contact_type === Driver::class) {
            switch ($cleanText) {
                case '1':
                    $this->driverService->sendPendingDockReceipts($conversation);
                    break;
                case '2':
                    $this->driverService->startSubmitDockReceiptFlow($conversation);
                    break;
                case '3':
                    $this->escalateToAgent($conversation);
                    break;
                default:
                    $this->sendGreeting($conversation);
                    break;
            }

            return;
        }

        switch ($cleanText) {
            case '1':
                $this->startTrackingFlow($conversation);
                break;
            case '2':
                $this->startDocumentFlow($conversation);
                break;
            case '3':
                $this->startPreAlertFlow($conversation);
                break;
            case '4':
                $this->startWalletFlow($conversation);
                break;
            case '5':
                $this->escalateToAgent($conversation);
                break;
            default:
                $this->sendGreeting($conversation);
                break;
        }
    }

    protected function sendGreeting(WhatsAppConversation $conversation): void
    {
        $conversation->loadMissing(['contact' => function (MorphTo $morphTo) {
            $morphTo->morphWith([
                Staff::class => ['user'],
                Shipper::class => ['user'],
            ]);
        }]);
        if ($conversation->contact_type === Staff::class) {
            $this->staffService->sendGreeting($conversation);

            return;
        }

        if ($conversation->contact_type === Driver::class) {
            $company = $conversation->contact->company ?? 'Driver';
            $message = "🚚 *Welcome, {$company}*\n\nPlease choose an option:\n\n1️⃣ Get Dock Receipts\n2️⃣ Send Stamped Dock\n3️⃣ Speak to Agent";
            $this->waService->sendMessage($conversation->phone_number, $message);

            return;
        }

        $message = '*Welcome to Anka Shipping & Logistics* 🚢

Please choose an option:

1️⃣ Track Shipment
2️⃣ View Documents
3️⃣ Create Pre-Alert
4️⃣ My Wallet
5️⃣ Speak to Agent';
        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function startTrackingFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'tracking_awaiting_vin']);
        $this->waService->sendMessage($conversation->phone_number, "🔍 Please send the *VIN* or *Reference number* you wish to track.\n\n_(Type 'Menu' to cancel)_");
    }

    protected function startDocumentFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'documents_awaiting_vin']);
        $this->waService->sendMessage($conversation->phone_number, "📄 Please send the *VIN* for which you need documents.\n\n_(Type 'Menu' to cancel)_");
    }

    protected function startPreAlertFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'prealert_awaiting_shipping_mode',
            'data_payload' => [],
        ]);

        $message = "✨ *Create Pre-Alert*\n\nPlease choose your *Shipping Mode*:\n\n1️⃣ RoRo (Roll-on/Roll-off)\n2️⃣ Container\n\n_(Type 'Menu' to cancel)_";
        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function startWalletFlow(WhatsAppConversation $conversation): void
    {
        $this->walletService->startWalletFlow($conversation);
    }

    protected function escalateToAgent(WhatsAppConversation $conversation): void
    {
        $conversation->update(['status' => 'escalated']);
        $this->waService->sendMessage($conversation->phone_number, "👨‍💼 *Agent Escalation*\n\nAn agent will be with you shortly. You can now send your message directly.\n\n_(Type *End* at any time to return to the main menu)_");
    }

    protected function processState(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text, ?string $mediaId): void
    {
        $lowerText = Str::lower($text);
        if ($lowerText === 'exit' || $lowerText === 'menu' || $lowerText === 'cancel') {
            $state->delete();
            $this->sendGreeting($conversation);

            return;
        }

        if (strlen($text) === 1 && in_array($text, ['1', '2', '3', '4', '5']) && Str::contains($state->current_step, 'awaiting_vin')) {
            $state->delete();
            $this->handleMainMenu($conversation, $text);

            return;
        }

        switch ($state->current_step) {
            case 'tracking_awaiting_vin':
                $this->shipmentService->track($conversation, $text);
                break;
            case 'documents_awaiting_vin':
                $this->documentService->sendDocuments($conversation, $text);
                break;
            case 'documents_awaiting_selection':
                $this->documentService->handleSelection($conversation, $state, $text);
                break;
            case 'prealert_awaiting_shipping_mode':
            case 'prealert_awaiting_vin':
            case 'prealert_awaiting_gatepass':
            case 'prealert_awaiting_auction_receipt':
            case 'prealert_awaiting_consignee':
            case 'prealert_awaiting_notify_party':
            case 'prealert_awaiting_carrier':
            case 'prealert_awaiting_destination_port':
            case 'prealert_awaiting_towing':
            case 'prealert_awaiting_shipment_selection':
                $this->preAlertService->handleStep($conversation, $state, $text, $mediaId);
                break;
            case 'wallet_menu_selection':
            case 'wallet_fund_awaiting_amount':
            case 'wallet_fund_awaiting_reference':
            case 'wallet_fund_awaiting_receipt':
            case 'wallet_pay_awaiting_vin':
            case 'wallet_pay_awaiting_confirmation':
                $this->walletService->handleStep($conversation, $state, $text, $mediaId);
                break;
            case 'driver_awaiting_vin':
            case 'driver_awaiting_receipt_file':
                $this->driverService->handleStep($conversation, $state, $text, $mediaId);
                break;
            case 'staff_awaiting_vehicle_selection':
            case 'staff_awaiting_other_scope':
            case 'staff_awaiting_vehicle_condition':
            case 'staff_awaiting_document_media':
                $this->staffService->handleStep($conversation, $state, $text, $mediaId);
                break;
        }
    }

    protected function flushBufferedMessages(WhatsAppConversation $conversation): void
    {
        $pending = $conversation->messages()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pending->isNotEmpty()) {
            $deduplicated = $pending->groupBy(function ($msg) {
                return $msg->related_entity_type.':'.$msg->related_entity_id;
            })->map->last();

            foreach ($deduplicated as $msg) {
                if ($msg->media_url) {
                    $this->waService->sendDocument(
                        $conversation->phone_number,
                        $msg->media_url,
                        $msg->message_text ?: 'document.pdf'
                    );
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '📢 *Update:* '.$msg->message_text);
                }

                $msg->update(['status' => 'sent']);
            }
        }

        $conversation->update(['status' => 'bot']);
        // Do NOT send the greeting here — the customer has just received their updates.
        // They can type anything or 'Menu' to get back to the main menu.
    }

    protected function handleRoutingDirective(WhatsAppConversation $conversation, string $tag): void
    {
        $category = WhatsAppCategory::where('hashtag', $tag)->first();
        if ($category) {
            $conversation->update(['category_id' => $category->id]);
            $this->waService->sendMessage($conversation->phone_number, "📁 This conversation has been routed to *{$category->name}*.");
        }
    }
}
