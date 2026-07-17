<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Notifications\TelexReleaseRequestedNotification;
use App\Notifications\TelexReleaseSubmittedNotification;
use App\ShippingWorkflow\ShippingWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class TelexRequestService
{
    public function __construct(
        protected WhatsAppService $waService,
        protected ShippingWorkflow $workflow
    ) {}

    /**
     * Step 1: Shipper selects 6️⃣ Request Telex Release → ask for VIN or Reference.
     */
    public function startTelexRequestFlow(WhatsAppConversation $conversation): void
    {
        $conversation->menuState()->updateOrCreate([], ['current_step' => 'telex_awaiting_vin']);
        $this->waService->sendMessage(
            $conversation->phone_number,
            "📄 *Request Telex Release*\n\nPlease send the *VIN* or *Reference number* for the shipment you wish to request a Telex Release for.\n\n_(Type 'Menu' to cancel)_"
        );
    }

    public function handleStep(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text): void
    {
        if ($state->current_step === 'telex_awaiting_vin') {
            $this->handleVinSubmission($conversation, $state, $text);
        }
    }

    protected function handleVinSubmission(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $query): void
    {
        $query = trim($query);

        // 1. Find Shipment
        $shipment = Shipment::where('reference_no', $query)->first();

        if (! $shipment) {
            $vehicle = Vehicle::findByVin($query);
            if ($vehicle && $vehicle->shipment_id) {
                $shipment = Shipment::find($vehicle->shipment_id);
            }
        }

        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference number and try again.");

            return;
        }

        // 2. Security Check
        if (! $this->userHasAccess($conversation, $shipment)) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference number and try again.");

            return;
        }

        // 3. Check Payment Status
        if ($shipment->payment_status !== PaymentStatus::Paid) {
            $this->waService->sendMessage(
                $conversation->phone_number,
                "⚠️ *Payment Required*\n\nA Telex Release can only be issued or requested once the invoice for shipment *{$shipment->reference_no}* is fully *PAID*.\n\nYour current payment status is: *{$shipment->payment_status->label()}*.\n\n💡 _To settle your invoice or top up your balance, type *'Menu'* and choose option *4️⃣ My Wallet*._"
            );
            $state->delete();

            return;
        }

        // 4. Option A: Telex is ALREADY Available
        if (filled($shipment->telex_release_text)) {
            $telexText = $shipment->telex_release_text;
            $msg = "📄 *TELEX RELEASE FOR SHIPMENT {$shipment->reference_no}* 📄\n"
                ."*BL Number:* {$shipment->bill_of_lading_number}\n\n"
                ."*Official Carrier Release Details:*\n"
                ."━━━━━━━━━━━━━━━━━━━\n"
                ."{$telexText}\n"
                ."━━━━━━━━━━━━━━━━━━━\n\n"
                .'✅ Your cargo is released and ready for pickup at the destination port against proper identification without presentation of Original Bills of Lading!';

            $this->waService->sendMessage($conversation->phone_number, $msg);
            $state->delete();

            return;
        }

        // 5. Option B: Telex is NOT YET Available -> Instant Automatic Request
        $user = $conversation->contact->user ?? null;
        $this->requestTelexRelease($shipment, $user, 'whatsapp_bot');

        $this->waService->sendMessage(
            $conversation->phone_number,
            "✅ *TELEX RELEASE REQUEST SUBMITTED*\n*Reference:* {$shipment->reference_no}\n\nYour request has been automatically routed to our operations team! You will receive an instant notification here with your official Telex details as soon as it is processed."
        );

        $state->delete();
    }

    public function requestTelexRelease(Shipment $shipment, ?User $user = null, string $source = 'whatsapp_bot'): void
    {
        if ($shipment->shipment_status !== ShipmentStatus::TelexRequested) {
            DB::transaction(function () use ($shipment, $user, $source): void {
                $shipment->update([
                    'shipment_status' => ShipmentStatus::TelexRequested,
                ]);

                ShipmentTracking::create([
                    'shipment_id' => $shipment->id,
                    'status' => ShipmentStatus::TelexRequested,
                    'note' => $source === 'whatsapp_bot'
                        ? __('Telex Release requested by shipper via WhatsApp Bot.')
                        : __('Telex Release requested by shipper via Web Portal.'),
                    'recorded_at' => now(),
                ]);

                if ($user) {
                    ActivityLog::create([
                        'shipment_id' => $shipment->id,
                        'user_id' => $user->id,
                        'action' => 'telex_requested',
                        'properties' => [
                            'reference_no' => $shipment->reference_no,
                            'source' => $source,
                        ],
                    ]);
                }
            });

            $this->notifyStaffOfTelexRequest($shipment);
        }
    }

    public function fulfillTelexRelease(Shipment $shipment, string $telexText, ?User $user = null, string $source = 'web_portal'): void
    {
        DB::transaction(function () use ($shipment, $telexText, $user, $source): void {
            $shipment->update([
                'telex_release_text' => $telexText,
                'telex_released_at' => now(),
                'shipment_status' => ShipmentStatus::Completed,
            ]);

            ShipmentTracking::create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Completed,
                'note' => __('Official Telex Release text recorded via :source. Shipment marked Completed.', ['source' => $source]),
                'recorded_at' => now(),
            ]);

            if ($user) {
                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id,
                    'action' => 'telex_release_submitted',
                    'properties' => [
                        'reference_no' => $shipment->reference_no,
                        'source' => $source,
                    ],
                ]);
            }
        });

        $this->sendTelexNotificationToParticipants($shipment);
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

    public function notifyStaffOfTelexRequest(Shipment $shipment): void
    {
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        $recipientIds = User::query()
            ->permission('workflow.submit_telex')
            ->pluck('id')
            ->merge(User::query()->role($adminRoleNames)->pluck('id'))
            ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
            ->unique()
            ->values();

        $recipients = User::whereIn('id', $recipientIds)->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TelexReleaseRequestedNotification($shipment));
        }
    }

    public function sendTelexNotificationToParticipants(Shipment $shipment): void
    {
        $recipientIds = collect();

        // Shipper user
        if ($shipment->shipper?->user_id) {
            $recipientIds->push($shipment->shipper->user_id);
        }

        // Consignee user (if linked to a user via shipper or user model)
        if ($shipment->consignee?->user_id) {
            $recipientIds->push($shipment->consignee->user_id);
        }

        $recipients = User::whereIn('id', $recipientIds->unique())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TelexReleaseSubmittedNotification($shipment));
        }
    }
}
