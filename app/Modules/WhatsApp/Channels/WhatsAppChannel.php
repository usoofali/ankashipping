<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Channels;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(protected WhatsAppService $waService) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $data = $notification->toWhatsApp($notifiable);
        $messageBody = $data['body'] ?? '';
        $relatedEntity = $data['related_entity'] ?? null;
        $files = $data['files'] ?? [];

        if (empty($messageBody) && empty($files)) {
            return;
        }

        // 1. Get Phone Number (normalized)
        $phone = $notifiable->phone ?? null;
        if (! $phone) {
            Log::channel('whatsapp')->warning('WhatsApp notification skipped: Notifiable has no phone number.', [
                'notifiable_id' => $notifiable->getKey(),
                'notifiable_type' => get_class($notifiable),
            ]);

            return;
        }

        $phone = preg_replace('/[^\d]/', '', $phone);

        // 2. Find Conversation
        $conversation = WhatsAppConversation::where('phone_number', $phone)->first();

        if (! $conversation) {
            Log::channel('whatsapp')->warning('WhatsApp notification skipped: No conversation found for phone number.', [
                'phone' => $phone,
                'notifiable_id' => $notifiable->getKey(),
            ]);

            return;
        }

        // 3. Check 24-Hour Window
        $isWindowOpen = $conversation->last_message_at && $conversation->last_message_at->diffInHours(now()) < 24;

        if ($isWindowOpen) {
            if (! empty($messageBody)) {
                $response = $this->waService->sendMessage($phone, $messageBody);
                $this->logMessage($conversation, $messageBody, $response, $relatedEntity, 'sent');
            }

            foreach ($files as $file) {
                if (isset($file['url'])) {
                    $response = $this->waService->sendDocument($phone, $file['url'], $file['name'] ?? 'document.pdf');
                    $this->logMessage($conversation, 'Document: '.($file['name'] ?? 'document.pdf'), $response, $relatedEntity, 'sent');
                }
            }
        } else {
            if (! empty($messageBody)) {
                $this->logMessage($conversation, $messageBody, null, $relatedEntity, 'pending');
            }

            foreach ($files as $file) {
                if (isset($file['url'])) {
                    $this->logMessage(
                        $conversation,
                        $file['name'] ?? 'Document',
                        null,
                        $relatedEntity,
                        'pending',
                        $file['url']
                    );
                }
            }

            Log::channel('whatsapp')->info('Notification buffered (window closed)', [
                'conversation_id' => $conversation->id,
                'notification' => get_class($notification),
            ]);
        }
    }

    protected function logMessage(WhatsAppConversation $conversation, string $body, ?array $response, ?object $relatedEntity, string $status, ?string $mediaUrl = null): void
    {
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'message_text' => $body,
            'media_url' => $mediaUrl,
            'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
            'status' => $status,
            'related_entity_id' => $relatedEntity ? $relatedEntity->id : null,
            'related_entity_type' => $relatedEntity ? $relatedEntity->getMorphClass() : null,
        ]);
    }
}
