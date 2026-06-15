<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppUserStat;
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
        $messageText = $messageData['text']['body']
            ?? $messageData['image']['caption']
            ?? $messageData['document']['caption']
            ?? $messageData['video']['caption']
            ?? '';
        $messageType = $messageData['type'] ?? 'text';
        $mediaId = $messageData['image']['id']
            ?? $messageData['document']['id']
            ?? $messageData['audio']['id']
            ?? $messageData['video']['id']
            ?? null;

        // 1. Find or Create Conversation
        $wasRecentlyCreated = false;
        $conversation = WhatsAppConversation::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['status' => 'bot']
        );
        $wasRecentlyCreated = $conversation->wasRecentlyCreated;

        // 2. Match Contact if not already linked
        $contact = null;
        if (! $conversation->contact_id) {
            $contact = $matcher->match($phoneNumber);
            if ($contact) {
                $conversation->update([
                    'contact_id' => $contact->id,
                    'contact_type' => $contact->getMorphClass(),
                ]);
            }
        }

        // 3. Upsert WhatsApp usage stat (one row per phone number)
        $resolvedContact = $contact ?? $conversation->contact;
        $morphClass = $resolvedContact ? $resolvedContact->getMorphClass() : null;
        $normalizedPhone = $matcher->normalize($phoneNumber);

        $stat = WhatsAppUserStat::firstOrCreate(
            ['phone_number' => $normalizedPhone],
            [
                'contact_role' => 'unknown',
                'first_contact_at' => now(),
                'conversation_count' => 1,
            ]
        );

        // Increment counters via the query builder to avoid cast conflicts
        WhatsAppUserStat::where('phone_number', $normalizedPhone)
            ->increment('total_messages', 1, ['last_contact_at' => now()]);

        if ($wasRecentlyCreated && ! $stat->wasRecentlyCreated) {
            WhatsAppUserStat::where('phone_number', $normalizedPhone)
                ->increment('conversation_count');
        }

        // Fill first_contact_at only when it was never set
        if ($stat->first_contact_at === null) {
            $stat->update(['first_contact_at' => now()]);
        }

        // Update contact info whenever we have a resolved contact
        if ($resolvedContact) {
            $stat->update([
                'contact_id' => $resolvedContact->id,
                'contact_type' => $morphClass,
                'contact_name' => WhatsAppUserStat::resolveContactName($resolvedContact),
                'contact_role' => WhatsAppUserStat::resolveRole($morphClass),
            ]);
        }

        // 4. Log the Message
        $message = WhatsAppMessage::updateOrCreate(
            ['whatsapp_message_id' => $messageId],
            [
                'conversation_id' => $conversation->id,
                'sender_type' => 'customer',
                'message_text' => $messageText,
                'message_type' => $messageType,
                'media_url' => $mediaId,
                'status' => 'delivered',
            ]
        );

        // 5. Route Message (Dynamic Categories & Reassignment)
        $router->route($conversation, $message);

        // 6. Update Window and Flush Pending Notifications
        $flushed = $this->flushPendingMessages($conversation, $waService);

        $conversation->update(['last_message_at' => now()]);

        // 7. Trigger Bot Logic (Phase 3)
        // Skip if we just issued a pending_flush prompt — the customer must reply first.
        if ($flushed) {
            return;
        }

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

    protected function flushPendingMessages(WhatsAppConversation $conversation, WhatsAppService $waService): bool
    {
        // Only send the prompt when the conversation is NOT already waiting for a flush reply.
        // If it is already pending_flush it means the customer hasn't replied yet — don't re-prompt.
        if ($conversation->status === 'pending_flush') {
            return false;
        }

        $pendingMessages = $conversation->messages()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingMessages->isEmpty()) {
            return false;
        }

        // Apply Deduplication Logic (Latest Status Only per Entity)
        $deduplicated = $pendingMessages->groupBy(function ($msg) {
            if (! $msg->related_entity_id) {
                return 'msg_'.$msg->id; // Don't deduplicate if no entity is linked
            }

            return $msg->related_entity_type.':'.$msg->related_entity_id;
        })->map(function ($group) {
            return $group->last(); // Keep only the latest for each entity
        });

        $count = $deduplicated->count();
        $waService->sendMessage(
            $conversation->phone_number,
            "🚗 *Welcome back!*\n\nYou have *{$count}* missed updates regarding your shipments. Would you like to read them?\n\n1️⃣ Read Updates\n2️⃣ Skip to Main Menu"
        );

        $conversation->update(['status' => 'pending_flush']);

        return true;
    }
}
