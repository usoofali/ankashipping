<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use App\Notifications\StampedDockReceiptNotification;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class WhatsAppInboxController extends Controller
{
    /**
     * List conversations with optional filter.
     */
    public function conversations(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'all');
        $staff = $request->user()->staff;
        $categoryIds = $staff ? $staff->whatsappCategories->pluck('id')->toArray() : [];

        $conversations = WhatsAppConversation::with([
            'contact' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Staff::class => ['user'],
                    Shipper::class => ['user'],
                ]);
            },
            'category',
            'latestMessage',
        ])
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('sender_type', 'customer')
                    ->where('status', '!=', 'read');
            }])
            ->when(! $request->user()->hasPermissionTo('whatsapp.manage_conversations') && ! $request->user()->hasRole('super_admin'), function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->when($filter === 'unassigned', fn ($q) => $q->whereNull('agent_id'))
            ->when($filter === 'escalated', fn ($q) => $q->where('status', 'escalated'))
            ->when($filter === 'bot', fn ($q) => $q->where('status', 'bot'))
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($conversations->map(function ($conv) {
            $name = $conv->contact?->user?->name ?? $conv->phone_number;
            $contactType = str_replace('App\\Models\\', '', $conv->contact_type ?? 'Unknown');

            return [
                'id' => $conv->id,
                'phone_number' => $conv->phone_number,
                'name' => $name,
                'contact_type' => $contactType,
                'agent_id' => $conv->agent_id,
                'status' => $conv->status,
                'category' => $conv->category ? [
                    'id' => $conv->category->id,
                    'name' => $conv->category->name,
                    'hashtag' => $conv->category->hashtag,
                ] : null,
                'last_message_at' => $conv->last_message_at?->toIso8601String(),
                'last_message_text' => $conv->latestMessage?->message_text,
                'unread_count' => $conv->unread_count ?? 0,
            ];
        }));
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(int $conversationId): JsonResponse
    {
        $conversation = WhatsAppConversation::with([
            'contact' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Staff::class => ['user'],
                    Shipper::class => ['user'],
                ]);
            },
        ])->findOrFail($conversationId);

        $messages = WhatsAppMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $name = $conversation->contact?->user?->name ?? $conversation->phone_number;
        $contactType = str_replace('App\\Models\\', '', $conversation->contact_type ?? 'Unknown');

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'name' => $name,
                'contact_type' => $contactType,
                'agent_id' => $conversation->agent_id,
                'status' => $conversation->status,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'is_window_open' => $conversation->last_message_at
                    && $conversation->last_message_at->diffInHours(now()) < 24,
            ],
            'messages' => $messages->map(fn ($msg) => [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'message_text' => $msg->message_text,
                'message_type' => $msg->message_type,
                'media_url' => $msg->media_url,
                'status' => $msg->status,
                'is_internal' => $msg->sender_type === 'bot' && str_contains($msg->message_text ?? '', 'Agent Review Needed'),
                'created_at' => $msg->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Mark messages as read.
     */
    public function markRead(int $conversationId): JsonResponse
    {
        WhatsAppMessage::where('conversation_id', $conversationId)
            ->where('sender_type', 'customer')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        return response()->json(['success' => true]);
    }

    /**
     * Send a message (including approve/reject handling).
     */
    public function send(Request $request, int $conversationId): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        $conversation = WhatsAppConversation::findOrFail($conversationId);
        $text = $request->input('message');
        $lowerText = strtolower(trim($text));

        // Auto-claim if unassigned
        if (! $conversation->agent_id) {
            $staff = $request->user()->staff;
            if ($staff) {
                $conversation->update([
                    'agent_id' => $staff->id,
                    'status' => 'escalated',
                ]);
            }
        }

        // Handle approve/reject for escalated driver flows
        if ($conversation->status === 'escalated' && $conversation->menuState) {
            $payload = $conversation->menuState->data_payload ?? [];
            if (isset($payload['receipt_path'], $payload['shipment_id'])) {
                if ($lowerText === 'approve') {
                    $this->handleAgentApproval($conversation, $payload, $request->user());

                    return response()->json(['success' => true, 'action' => 'approved']);
                }

                if (str_starts_with($lowerText, 'reject')) {
                    $reason = trim(substr(trim($text), 6), " :\t\n\r\0\x0B");
                    $this->handleAgentRejection($conversation, $payload, $reason, $request->user());

                    return response()->json(['success' => true, 'action' => 'rejected']);
                }
            }
        }

        $waService = app(WhatsAppService::class);
        $response = $waService->sendMessage($conversation->phone_number, $text);

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'agent',
            'message_text' => $text,
            'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
            'status' => 'sent',
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Claim a conversation.
     */
    public function claim(Request $request, int $conversationId): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($conversationId);
        $staff = $request->user()->staff;

        if ($staff) {
            $conversation->update([
                'agent_id' => $staff->id,
                'status' => 'escalated',
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Resolve a conversation.
     */
    public function resolve(int $conversationId): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($conversationId);

        $conversation->menuState()?->delete();
        $conversation->update([
            'status' => 'bot',
            'agent_id' => null,
        ]);

        app(WhatsAppService::class)->sendMessage(
            $conversation->phone_number,
            "This conversation has been marked as resolved. If you need further assistance, please reply 'Hi' to start over."
        );

        return response()->json(['success' => true]);
    }

    /**
     * Clear a conversation.
     */
    public function clear(int $conversationId): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($conversationId);

        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->menuState()?->delete();
            $conversation->update([
                'status' => 'bot',
                'agent_id' => null,
                'category_id' => null,
                'last_message_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(int $messageId)
    {
        $message = WhatsAppMessage::findOrFail($messageId);

        if (! $message->media_url) {
            return response()->json(['error' => 'No attachment found'], 404);
        }

        if (Storage::disk('public')->exists($message->media_url)) {
            return Storage::disk('public')->download($message->media_url);
        }

        $waService = app(WhatsAppService::class);
        $media = $waService->streamMedia($message->media_url);

        if (! $media) {
            return response()->json(['error' => 'Download failed'], 422);
        }

        return response()->streamDownload(function () use ($media) {
            echo $media['content'];
        }, $media['filename'], [
            'Content-Type' => $media['mime_type'],
        ]);
    }

    protected function handleAgentApproval(WhatsAppConversation $conversation, array $payload, User $agent): void
    {
        $shipment = Shipment::find($payload['shipment_id']);
        if (! $shipment) {
            return;
        }

        $tempPath = $payload['receipt_path'];
        $permanentPath = 'shipments/documents/'.basename($tempPath);

        if (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->move($tempPath, $permanentPath);
        } else {
            return;
        }

        $document = ShipmentDocument::create([
            'shipment_id' => $shipment->id,
            'document_type' => ShipmentDocumentType::StampDockReceipt,
            'notes' => 'Attached via WhatsApp Bot by Agent '.$agent->name,
        ]);

        ShipmentDocumentFile::create([
            'shipment_document_id' => $document->id,
            'path' => $permanentPath,
            'original_name' => basename($tempPath),
            'mime_type' => Storage::disk('public')->mimeType($permanentPath),
            'size' => Storage::disk('public')->size($permanentPath),
        ]);

        $fromShipmentStatus = $shipment->shipment_status;
        $shipment->update(['shipment_status' => ShipmentStatus::Delivered]);

        ActivityLog::create([
            'shipment_id' => $shipment->id,
            'user_id' => $agent->id,
            'action' => 'document_attached',
            'properties' => [
                'shipment_document_id' => $document->id,
                'document_type' => ShipmentDocumentType::StampDockReceipt->value,
                'document_type_label' => ShipmentDocumentType::StampDockReceipt->label(),
                'file_count' => 1,
                'reference_no' => $shipment->reference_no,
                'source' => 'whatsapp_bot',
            ],
        ]);

        if ($fromShipmentStatus !== ShipmentStatus::Delivered) {
            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Delivered,
                'note' => __('Document attached: :type. Status moved to :s', [
                    'type' => ShipmentDocumentType::StampDockReceipt->label(),
                    's' => ShipmentStatus::Delivered->name,
                ]),
                'recorded_at' => now(),
            ]);
        }

        // Send notifications
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        $recipientIds = User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
            ->unique()
            ->values();

        if ($shipment->shipper?->user_id !== null) {
            $recipientIds->push($shipment->shipper->user_id);
        }

        $recipients = User::query()->whereIn('id', $recipientIds->unique()->values())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new StampedDockReceiptNotification($shipment, $document));
        }

        $conversation->menuState()?->delete();
        $conversation->update(['status' => 'bot', 'agent_id' => null]);

        $waService = app(WhatsAppService::class);
        $waService->sendMessage(
            $conversation->phone_number,
            "✅ Your Stamped Dock Receipt for {$shipment->reference_no} has been approved. The shipment is now marked as Delivered."
        );

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'bot',
            'message_text' => '✅ Agent '.$agent->name.' approved the Stamped Dock Receipt.',
            'status' => 'sent',
        ]);
    }

    protected function handleAgentRejection(WhatsAppConversation $conversation, array $payload, string $reason, User $agent): void
    {
        $tempPath = $payload['receipt_path'];

        if (Storage::disk('local')->exists($tempPath)) {
            Storage::disk('local')->delete($tempPath);
        } elseif (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->delete($tempPath);
        }

        $conversation->menuState()?->delete();
        $conversation->update(['status' => 'bot', 'agent_id' => null]);

        $msg = '❌ Your submitted Dock Receipt was rejected.';
        if ($reason) {
            $msg .= ' Reason: '.$reason;
        }
        $msg .= ' Please contact an agent or try again.';

        $waService = app(WhatsAppService::class);
        $waService->sendMessage($conversation->phone_number, $msg);

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'bot',
            'message_text' => '❌ Agent '.$agent->name.' rejected the Stamped Dock Receipt'.($reason ? " (Reason: $reason)" : '').'.',
            'status' => 'sent',
        ]);
    }
}
