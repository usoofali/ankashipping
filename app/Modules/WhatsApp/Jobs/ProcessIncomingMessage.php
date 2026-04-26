<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\BotService;
use App\Modules\WhatsApp\Services\MessageRouter;
use App\Modules\WhatsApp\Services\PhoneNumberMatcher;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(public array $payload) {}

    public function handle(PhoneNumberMatcher $matcher, WhatsAppService $waService, MessageRouter $router): void
    {
        $value = $this->payload['entry'][0]['changes'][0]['value'] ?? [];
        $messageData = $value['messages'][0] ?? null;
        $statusData = $value['statuses'][0] ?? null;

        if ($statusData) {
            $this->handleStatusUpdate($statusData);
        }

        if (! $messageData) {
            return;
        }

        $phoneNumber = $messageData['from']; // This is the wa_id (phone number)
        $messageId = $messageData['id'];
        $messageText = $messageData['text']['body'] ?? '';
        $messageType = $messageData['type'] ?? 'text';

        // 1. Find or Create Conversation
        $conversation = WhatsAppConversation::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['status' => 'bot']
        );

        // 2. Match Contact if not already linked
        if (! $conversation->contact_id) {
            $contact = $matcher->match($phoneNumber);
            if ($contact) {
                $conversation->update([
                    'contact_id' => $contact->id,
                    'contact_type' => $contact->getMorphClass(),
                ]);
            }
        }

        // 3. Log the Message
        $message = WhatsAppMessage::updateOrCreate(
            ['whatsapp_message_id' => $messageId],
            [
                'conversation_id' => $conversation->id,
                'sender_type' => 'customer',
                'message_text' => $messageText,
                'message_type' => $messageType,
                'status' => 'delivered',
            ]
        );

        // 4. Route Message (Dynamic Categories & Reassignment)
        $router->route($conversation, $message);

        // 5. Update Window and Flush Pending Notifications
        $this->flushPendingMessages($conversation, $waService);

        $conversation->update(['last_message_at' => now()]);

        // 6. Trigger Bot Logic (Phase 3)
        app(BotService::class)->handle($conversation, $messageData);
    }

    protected function handleStatusUpdate(array $statusData): void
    {
        $messageId = $statusData['id'];
        $status = $statusData['status'];

        $message = WhatsAppMessage::where('whatsapp_message_id', $messageId)->first();

        if ($message) {
            $message->update(['status' => $status]);

            if ($status === 'failed') {
                Log::channel('whatsapp')->warning('WhatsApp Message Failed', [
                    'message_id' => $messageId,
                    'errors' => $statusData['errors'] ?? [],
                ]);
            }
        }
    }

    protected function flushPendingMessages(WhatsAppConversation $conversation, WhatsAppService $waService): void
    {
        $pendingMessages = $conversation->messages()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingMessages->isEmpty()) {
            return;
        }

        // Apply Deduplication Logic (Latest Status Only per Entity)
        $deduplicated = $pendingMessages->groupBy(function ($msg) {
            if (! $msg->related_entity_id) {
                return 'msg_' . $msg->id; // Don't deduplicate if no entity is linked
            }
            return $msg->related_entity_type.':'.$msg->related_entity_id;
        })->map(function ($group) {
            return $group->last(); // Keep only the latest for each entity
        });

        // Send Interactive Prompt if there are multiple, or just send them if few?
        // User agreed to: "Interactive Prompt Phase: 'You have X missed updates. Reply 1 to read them.'"

        $count = $deduplicated->count();
        $waService->sendMessage(
            $conversation->phone_number,
            "🚗 *Welcome back!*\n\nYou have *{$count}* missed updates regarding your shipments. Would you like to read them?\n\n1️⃣ Read Updates\n2️⃣ Skip to Main Menu"
        );

        $conversation->update(['status' => 'pending_flush']);
        // This reply will be handled by the BotService in Phase 3.
        // For now, we'll mark the deduplicated messages as 'ready_to_flush' or similar,
        // or just leave them as pending and have the bot handle them.
    }
}
