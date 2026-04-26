<?php

declare(strict_types=1);

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use App\Models\Shipper;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('WhatsApp Inbox')] class extends Component {
    public ?int $selectedConversationId = null;

    public string $messageText = '';
    public string $filter = 'all';
    public bool $showClearModal = false;

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;
        $this->messageText = '';

        // Mark messages as read
        WhatsAppMessage::where('conversation_id', $id)
            ->where('sender_type', 'customer')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);
    }

    public function sendMessage(): void
    {
        $this->validate(['messageText' => 'required|string']);

        $conversation = WhatsAppConversation::findOrFail($this->selectedConversationId);

        if (!$conversation->agent_id) {
            $this->claimConversation($conversation->id);
        }

        // Inline Bot Chat: Check for Approve/Reject commands for escalated driver flows
        $lowerText = strtolower(trim($this->messageText));

        if ($conversation->status === 'escalated' && $conversation->menuState) {
            $payload = $conversation->menuState->data_payload ?? [];
            if (isset($payload['receipt_path']) && isset($payload['shipment_id'])) {
                if ($lowerText === 'approve') {
                    $this->handleAgentApproval($conversation, $payload);
                    $this->messageText = '';
                    return;
                }

                if (str_starts_with($lowerText, 'reject')) {
                    $reason = trim(substr(trim($this->messageText), 6), " :\t\n\r\0\x0B");
                    $this->handleAgentRejection($conversation, $payload, $reason);
                    $this->messageText = '';
                    return;
                }
            }
        }

        $waService = app(WhatsAppService::class);
        $response = $waService->sendMessage($conversation->phone_number, $this->messageText);

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'agent',
            'message_text' => $this->messageText,
            'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
            'status' => 'sent',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->messageText = '';
    }

    protected function handleAgentApproval(WhatsAppConversation $conversation, array $payload): void
    {
        $shipmentId = $payload['shipment_id'];
        $tempPath = $payload['receipt_path'];

        $shipment = \App\Models\Shipment::find($shipmentId);
        if (!$shipment) {
            return;
        }

        // 1. Move file to permanent storage (already on public disk, just move/copy to final location)
        $permanentPath = 'shipments/documents/' . basename($tempPath);

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($tempPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->move($tempPath, $permanentPath);
        } else {
            return; // File lost or already moved
        }

        // 2. Attach to shipment
        $document = \App\Models\ShipmentDocument::create([
            'shipment_id' => $shipment->id,
            'document_type' => \App\Enums\ShipmentDocumentType::StampDockReceipt,
            'notes' => 'Attached via WhatsApp Bot by Agent ' . auth()->user()->name,
        ]);

        \App\Models\ShipmentDocumentFile::create([
            'shipment_document_id' => $document->id,
            'path' => $permanentPath,
            'original_name' => basename($tempPath),
            'mime_type' => \Illuminate\Support\Facades\Storage::disk('public')->mimeType($permanentPath),
            'size' => \Illuminate\Support\Facades\Storage::disk('public')->size($permanentPath),
        ]);

        // 3. Update shipment status and log
        $fromShipmentStatus = $shipment->shipment_status;
        $shipment->update(['shipment_status' => \App\Enums\ShipmentStatus::Delivered]);

        \App\Models\ActivityLog::create([
            'shipment_id' => $shipment->id,
            'user_id' => auth()->id(),
            'action' => 'document_attached',
            'properties' => [
                'shipment_document_id' => $document->id,
                'document_type' => \App\Enums\ShipmentDocumentType::StampDockReceipt->value,
                'document_type_label' => \App\Enums\ShipmentDocumentType::StampDockReceipt->label(),
                'file_count' => 1,
                'reference_no' => $shipment->reference_no,
                'source' => 'whatsapp_bot',
            ],
        ]);

        if ($fromShipmentStatus !== \App\Enums\ShipmentStatus::Delivered) {
            \App\Models\ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => \App\Enums\ShipmentStatus::Delivered,
                'note' => __('Document attached: :type. Status moved to :s', [
                    'type' => \App\Enums\ShipmentDocumentType::StampDockReceipt->label(),
                    's' => \App\Enums\ShipmentStatus::Delivered->name
                ]),
                'recorded_at' => now(),
            ]);
        }

        // Send notifications
        $adminRoleNames = \Spatie\Permission\Models\Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        $recipientIds = \App\Models\User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(\App\Models\User::query()->whereHas('staff')->pluck('id'))
            ->merge(\App\Models\User::query()->whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->pluck('id'))
            ->unique()
            ->values();

        if ($shipment->shipper?->user_id !== null) {
            $recipientIds->push($shipment->shipper->user_id);
        }

        $recipients = \App\Models\User::query()->whereIn('id', $recipientIds->unique()->values())->get();
        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\StampedDockReceiptNotification($shipment, $document));
        }

        // 4. Resolve conversation
        $conversation->menuState()->delete();
        $conversation->update(['status' => 'bot', 'agent_id' => null]);

        // 5. Notify Driver
        $waService = app(WhatsAppService::class);
        $waService->sendMessage($conversation->phone_number, "✅ Your Stamped Dock Receipt for {$shipment->reference_no} has been approved. The shipment is now marked as Delivered.");

        // Internal agent message for the log
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'bot',
            'message_text' => '✅ Agent ' . auth()->user()->name . ' approved the Stamped Dock Receipt.',
            'status' => 'sent',
        ]);

        $this->selectedConversationId = null;
    }

    protected function handleAgentRejection(WhatsAppConversation $conversation, array $payload, string $reason): void
    {
        $tempPath = $payload['receipt_path'];

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tempPath)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($tempPath);
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($tempPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tempPath);
        }

        $conversation->menuState()->delete();
        $conversation->update(['status' => 'bot', 'agent_id' => null]);

        $msg = "❌ Your submitted Dock Receipt was rejected.";
        if ($reason) {
            $msg .= " Reason: " . $reason;
        }
        $msg .= " Please contact an agent or try again.";

        $waService = app(WhatsAppService::class);
        $waService->sendMessage($conversation->phone_number, $msg);

        // Internal agent message for the log
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'category_id' => $conversation->category_id,
            'sender_type' => 'bot',
            'message_text' => '❌ Agent ' . auth()->user()->name . ' rejected the Stamped Dock Receipt' . ($reason ? " (Reason: $reason)" : '') . '.',
            'status' => 'sent',
        ]);

        $this->selectedConversationId = null;
    }

    public function claimConversation(int $id): void
    {
        $conversation = WhatsAppConversation::findOrFail($id);
        $staff = auth()->user()->staff;

        if ($staff) {
            $conversation->update([
                'agent_id' => $staff->id,
                'status' => 'escalated',
            ]);
        }
    }

    public function resolveConversation(int $id): void
    {
        $conversation = WhatsAppConversation::findOrFail($id);

        $conversation->menuState()->delete();

        $conversation->update([
            'status' => 'bot',
            'agent_id' => null,
        ]);

        app(\App\Modules\WhatsApp\Services\WhatsAppService::class)->sendMessage(
            $conversation->phone_number,
            "This conversation has been marked as resolved. If you need further assistance, please reply 'Hi' to start over."
        );

        $this->selectedConversationId = null;
    }

    public function clearConversation(): void
    {
        if (!$this->selectedConversationId) {
            return;
        }

        $conversation = WhatsAppConversation::findOrFail($this->selectedConversationId);

        DB::transaction(function () use ($conversation) {
            // Delete all messages
            $conversation->messages()->delete();

            // Delete menu state
            $conversation->menuState()->delete();

            // Reset conversation
            $conversation->update([
                'status' => 'bot',
                'agent_id' => null,
                'category_id' => null,
                'last_message_at' => now(),
            ]);
        });

        $this->selectedConversationId = null;
        $this->showClearModal = false;
    }

    public function downloadAttachment(int $messageId)
    {
        $message = WhatsAppMessage::findOrFail($messageId);

        if (!$message->media_url) {
            $this->dialog()->error('No attachment found', 'This message does not contain a valid media attachment.');
            return;
        }

        // Check if it's a local file (e.g. downloaded by the bot for review)
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($message->media_url)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download($message->media_url);
        }

        $waService = app(WhatsAppService::class);
        $media = $waService->streamMedia($message->media_url);

        if (!$media) {
            $this->dialog()->error('Download Failed', 'The attachment could not be downloaded from WhatsApp. It may have expired.');
            return;
        }

        return response()->streamDownload(function () use ($media) {
            echo $media['content'];
        }, $media['filename'], [
            'Content-Type' => $media['mime_type'],
        ]);
    }

    #[On('echo-private:whatsapp,WhatsAppMessageReceived')]
    public function onMessageReceived(): void
    {
        // Refresh component when new message arrives via WebSockets (if configured)
    }

    public function getConversations(): Collection
    {
        $staff = auth()->user()->staff;
        $categoryIds = $staff ? $staff->whatsappCategories->pluck('id')->toArray() : [];

        return WhatsAppConversation::with([
            'contact' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Staff::class => ['user'],
                    Shipper::class => ['user'],
                ]);
            },
            'category',
            'messages' => fn($q) => $q->latest()->limit(1)
        ])
            ->when(!auth()->user()->hasRole('super_admin'), function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->when($this->filter === 'unassigned', function ($query) {
                $query->whereNull('agent_id');
            })
            ->when($this->filter === 'escalated', function ($query) {
                $query->where('status', 'escalated');
            })
            ->when($this->filter === 'bot', function ($query) {
                $query->where('status', 'bot');
            })
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getChatMessages(): Collection
    {
        if (!$this->selectedConversationId) {
            return new Collection;
        }

        // Limit to last 100 messages for performance
        return WhatsAppMessage::where('conversation_id', $this->selectedConversationId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->reverse();
    }

    public function selectedConversation(): ?WhatsAppConversation
    {
        return $this->selectedConversationId
            ? WhatsAppConversation::with([
                'contact' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Staff::class => ['user'],
                        Shipper::class => ['user'],
                    ]);
                }
            ])->find($this->selectedConversationId)
            : null;
    }
}; ?>

<x-crud.page-shell class="h-dvh overflow-hidden pb-12">
    <div class="flex h-full lg:gap-6 overflow-hidden min-h-0" wire:poll.5s>
        <!-- Sidebar: Conversation List -->
        <div class="flex-[2] flex-col bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden h-full {{ $this->selectedConversationId ? 'hidden lg:flex' : 'flex' }}">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Conversations') }}</flux:heading>
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Live</span>
                    </div>
                </div>

                <!-- Filters -->
                <flux:select wire:model.live="filter" size="sm">
                    <flux:select.option value="all">{{ __('All Conversations') }}</flux:select.option>
                    <flux:select.option value="unassigned">{{ __('Open (Unassigned)') }}</flux:select.option>
                    <flux:select.option value="escalated">{{ __('Urgent (Escalated)') }}</flux:select.option>
                    <flux:select.option value="bot">{{ __('Bot Handled') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="flex-1 overflow-y-auto">
                @foreach($this->getConversations() as $conv)
                    <div wire:click="selectConversation({{ $conv->id }})" 
                        class="mx-3 my-2.5 p-4 rounded-xl border transition-all cursor-pointer group relative overflow-hidden
                        {{ $selectedConversationId === $conv->id
        ? 'bg-indigo-50/50 dark:bg-indigo-500/5 border-indigo-200 dark:border-indigo-800 shadow-sm ring-1 ring-indigo-200/50 dark:ring-indigo-800/30'
        : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 hover:shadow-lg hover:-translate-y-0.5' 
                        }}">
                        
                        @if($selectedConversationId === $conv->id)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>
                        @endif

                        <div class="flex gap-4">
                            <!-- Avatar -->
                            <div class="relative shrink-0">
                                <div class="size-11 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/60 dark:border-zinc-700 flex items-center justify-center text-sm font-bold text-zinc-600 dark:text-zinc-400 group-hover:scale-105 transition-transform">
                                    @php
    $name = $conv->contact?->user?->name ?? $conv->phone_number;
    $initials = preg_match('/^\+?[0-9]+$/', $name) ? '?' : strtoupper(mb_substr($name, 0, 2));
                                    @endphp
                                    {{ $initials }}
                                </div>
                                <!-- WhatsApp icon badge -->
                                <div class="absolute -bottom-1 -right-1 bg-white dark:bg-zinc-900 rounded-full p-[3px] shadow-sm">
                                    <div class="size-4.5 bg-green-500 rounded-full flex items-center justify-center">
                                        <svg class="size-2.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51h-.57c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline mb-1">
                                    <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        {{ $conv->contact?->user?->name ?? $conv->phone_number }}
                                    </span>
                                    <span class="text-[11px] font-medium text-zinc-400 dark:text-zinc-500 shrink-0 ml-2">
                                        {{ $conv->last_message_at?->format('h:i A') }}
                                    </span>
                                </div>
                                
                                <div class="text-[13px] text-zinc-500 dark:text-zinc-400 truncate mb-3 italic">
                                    {{ $conv->messages->first()?->message_text ?? __('No messages yet') }}
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    @if($conv->category)
                                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 text-[10px] font-bold border border-zinc-200/60 dark:border-zinc-700/60 shadow-sm transition-all group-hover:border-indigo-200 dark:group-hover:border-indigo-900">#{{ $conv->category->hashtag }}</span>
                                    @endif
                                    @if(!$conv->agent_id)
                                        <span class="px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-900/10 text-rose-600 dark:text-rose-400 text-[10px] font-bold border border-rose-100 dark:border-rose-900/30 shadow-sm animate-pulse-slow">Unassigned</span>
                                    @endif
                                    @if($conv->status === 'escalated')
                                        <span class="px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-900/10 text-amber-600 dark:text-amber-400 text-[10px] font-bold border border-amber-100 dark:border-amber-900/30 shadow-sm">Escalated</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Main Area: Chat -->
        <div class="flex-1 lg:flex-1 flex-col bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden h-full min-h-0 {{ $this->selectedConversationId ? 'flex' : 'hidden lg:flex' }}">
            @if($this->selectedConversationId)
                <!-- Chat Header (Fixed Height) -->
                <div class="shrink-0 p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <flux:button variant="ghost" icon="chevron-left" size="sm" class="lg:hidden" wire:click="$set('selectedConversationId', null)" />

                        <div class="size-10 shrink-0 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold">
                            {{ substr($this->selectedConversation()->contact?->user?->name ?? $this->selectedConversation()->phone_number, 0, 1) }}
                        </div>
                        <div class="hidden lg:block">
                            <div class="flex items-center gap-2">
                                <flux:heading size="md">{{ $this->selectedConversation()->contact?->user?->name ?? $this->selectedConversation()->phone_number }}</flux:heading>
                                @php
    $type = str_replace('App\\Models\\', '', $this->selectedConversation()->contact_type ?? 'Unknown');
    $color = match ($type) {
        'Customer' => 'blue',
        'Driver' => 'orange',
        'Staff' => 'teal',
        default => 'zinc'
    };
                                @endphp
                                <flux:badge :color="$color" size="sm" inset="top bottom" class="uppercase text-[10px]">{{ $type }}</flux:badge>
                            </div>
                            <flux:subheading size="xs">{{ $this->selectedConversation()->phone_number }}</flux:subheading>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <flux:button variant="ghost" color="rose" icon="trash" size="sm" wire:click="$set('showClearModal', true)">
                            <span class=" lg:inline">{{ __('Clear') }}</span>
                        </flux:button>
 
                        @if(!$this->selectedConversation()->agent_id)
                            <flux:button variant="primary" icon="hand-raised" wire:click="claimConversation({{ $this->selectedConversationId }})">
                                <span class=" lg:inline">{{ __('Claim') }}</span>
                            </flux:button>
                        @else
                            <flux:button variant="filled" color="zinc" icon="check-circle" wire:click="resolveConversation({{ $this->selectedConversationId }})" wire:loading.attr="disabled">
                                <span class=" lg:inline">{{ __('Done') }}</span>
                            </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Messages Area (Scrollable, takes all remaining space) -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4 !bg-[#f0f2f5] dark:!bg-[#0b141a] min-h-0">
                    @foreach($this->getChatMessages() as $msg)
                        @php
        $isCustomer = $msg->sender_type === 'customer';
        $isInternal = $msg->sender_type === 'bot' && str_contains($msg->message_text, 'Agent Review Needed');
                        @endphp

                        <div class="flex {{ $isCustomer ? 'justify-start' : 'justify-end' }} mb-2">
                            @if($isCustomer)
                                <!-- Customer Avatar -->
                                <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-medium text-zinc-600 dark:text-zinc-300 shrink-0 mr-2 mt-auto">
                                    {{ strtoupper(substr($this->selectedConversation()->contact?->user?->name ?? 'D', 0, 2)) }}
                                </div>
                            @endif

                            <!-- Bubble -->
                            <x-card padding="p-1.5 px-2" class="w-fit max-w-[75%] !rounded-[7.5px] {{ $isCustomer ? '!rounded-tl-none' : '!rounded-tr-none' }} {{ $isInternal ? '!bg-[#fff4ce] dark:!bg-[#5c4b00] !border-0' : ($isCustomer ? '!bg-white dark:!bg-[#202c33] !border-0' : '!bg-[#d9fdd3] dark:!bg-[#005c4b] !border-0') }} shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] dark:shadow-[0_1px_0.5px_rgba(0,0,0,0.4)]">
                                @if($msg->media_url && in_array($msg->message_type, ['image', 'document', 'audio', 'video']))
                                    @if(!str_starts_with($msg->media_url, 'http'))
                                        <div class="mb-2">
                                            <flux:button size="sm" icon="arrow-down-tray" wire:click="downloadAttachment({{ $msg->id }})" class="w-full justify-start">
                                                {{ __('Download Attachment') }} ({{ ucfirst($msg->message_type) }})
                                            </flux:button>
                                        </div>
                                    @else
                                        <div class="mb-2">
                                            <flux:button size="sm" icon="arrow-top-right-on-square" href="{{ $msg->media_url }}" target="_blank" class="w-full justify-start">
                                                {{ __('View Attachment') }} ({{ ucfirst($msg->message_type) }})
                                            </flux:button>
                                        </div>
                                    @endif
                                @endif
                                
                                <p class="text-[14.2px] leading-[19px] whitespace-pre-wrap {{ $isInternal ? 'text-amber-900 dark:text-amber-100' : 'text-[#111b21] dark:text-[#e9edef]' }} break-words">{{ $msg->message_text }}</p>
                                
                                <!-- Time & Status in Footer -->
                                <x-slot name="footer" class="flex items-center justify-end gap-1 !bg-transparent border-t border-black/5 dark:border-white/5 !px-2 !py-0.5">
                                    <span class="text-[10px] {{ $isInternal ? 'text-amber-700/70 dark:text-amber-300/70' : 'text-[#667781] dark:text-[#8696a0]' }} leading-none">{{ $msg->created_at->format('H:i') }}</span>
                                    @if(!$isCustomer)
                                        <x-heroicons::solid.check-badge class="w-5 h-5 {{ $msg->status === 'read' ? 'text-info-600' : 'text-secondary-400' }}" />
                                    @endif
                                </x-slot>
                            </x-card>

                            @if(!$isCustomer)
                                <!-- Agent Avatar -->
                                <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-xs font-medium text-zinc-600 dark:text-zinc-300 shrink-0 ml-2 mt-auto">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Window Status Warning -->
                @php
    $lastMessageAt = $this->selectedConversation()->last_message_at;
    $isWindowOpen = $lastMessageAt && $lastMessageAt->diffInHours(now()) < 24;
                @endphp

                @if(!$isWindowOpen)
                    <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/30 border-t border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-400 text-[11px] flex items-center gap-2 shrink-0">
                        <flux:icon.exclamation-triangle variant="mini" class="size-4" />
                        <span>{{ __('24-hour window is closed. Free-form messages will not be delivered.') }}</span>
                    </div>
                @endif

                <!-- Input Area (Fixed Height) -->
                <div class="shrink-0 p-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                    <form wire:submit="sendMessage" class="flex gap-2">
                        <flux:input wire:model="messageText" placeholder="{{ __('Type your message...') }}" class="flex-1" />
                        <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send') }}</flux:button>
                    </form>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-zinc-500">
                    <flux:icon.chat-bubble-left-right class="size-16 mb-4 opacity-20" />
                    <p>{{ __('Select a conversation to start chatting') }}</p>
                </div>
            @endif
        </div>
    </div>

    <flux:modal name="clear-conversation-modal" wire:model="showClearModal" class="md:w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Clear Conversation?') }}</flux:heading>
                <flux:subheading>{{ __('This will permanently delete all messages and reset the bot state for this contact. This action cannot be undone.') }}</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showClearModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="clearConversation" variant="filled" color="rose">{{ __('Clear Everything') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-crud.page-shell>
