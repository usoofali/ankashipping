<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Models\Driver;
use App\Models\Shipper;
use App\Modules\WhatsApp\Models\WhatsAppCategory;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

class MessageRouter
{
    /**
     * Route the incoming message to the correct category and team.
     */
    public function route(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        $text = trim($message->message_text);

        // 1. Detect Decorator (must be at the beginning)
        if (str_starts_with($text, '#')) {
            $words = explode(' ', $text);
            $tag = ltrim($words[0], '#');

            $category = WhatsAppCategory::where('hashtag', $tag)->first();

            if ($category) {
                $this->assignCategory($conversation, $message, $category, $tag);

                return;
            }
        }

        // 2. Default Category based on Contact Type (if no tag)
        if (! $conversation->category_id) {
            $category = null;

            if ($conversation->contact_type === Shipper::class) {
                $category = WhatsAppCategory::where('hashtag', 'customer')->first()
                         ?? WhatsAppCategory::where('is_default', true)->first();
            } elseif ($conversation->contact_type === Driver::class) {
                $category = WhatsAppCategory::where('hashtag', 'driver')->first();
            }

            if ($category) {
                $this->assignCategory($conversation, $message, $category);
            }
        }
    }

    /**
     * Update conversation and message with the new category and manage agent assignment.
     */
    protected function assignCategory(WhatsAppConversation $conversation, WhatsAppMessage $message, WhatsAppCategory $category, ?string $decorator = null): void
    {
        $oldCategoryId = $conversation->category_id;

        // Update Message
        $message->update([
            'category_id' => $category->id,
            'decorator' => $decorator ? '#'.$decorator : null,
        ]);

        // Update Conversation
        $conversation->category_id = $category->id;

        // Smart Reassignment: Only clear agent if they aren't in the new team
        if ($conversation->agent_id) {
            $isMember = $category->staff()->where('staff_id', $conversation->agent_id)->exists();
            if (! $isMember) {
                $conversation->agent_id = null;
            }
        }

        $conversation->save();
    }
}
