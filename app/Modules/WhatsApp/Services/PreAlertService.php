<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\PrealertStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VinLookupOutcome;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipment as ShipmentModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Notifications\PrealertCreatedNotification;
use App\Services\VinLookupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class PreAlertService
{
    public function __construct(
        protected WhatsAppService $waService,
        protected VinLookupService $vinLookupService
    ) {}

    public function handleStep(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text, ?string $mediaId): void
    {
        $payload = $state->data_payload ?? [];
        $cleanText = trim($text);

        switch ($state->current_step) {
            case 'prealert_awaiting_shipping_mode':
                if ($cleanText === '1') {
                    $payload['shipping_mode'] = 'roro';
                } elseif ($cleanText === '2') {
                    $payload['shipping_mode'] = 'container';
                } else {
                    $this->waService->sendMessage($conversation->phone_number, "⚠️ Invalid selection. Please choose:\n\n1️⃣ RoRo\n2️⃣ Container");

                    return;
                }

                $payload['vehicle_ids'] = [];
                $payload['vehicle_data'] = [];
                $state->update([
                    'current_step' => 'prealert_awaiting_vin',
                    'data_payload' => $payload,
                ]);
                $this->waService->sendMessage($conversation->phone_number, '✅ *Mode saved.* Now, please send the *Vehicle VIN*.');
                break;

            case 'prealert_awaiting_vin':
                // Check if user wants to proceed to next step (only for Container mode with at least 1 vehicle)
                if ($cleanText === '0' && ($payload['shipping_mode'] ?? '') === 'container' && ! empty($payload['vehicle_ids'])) {
                    $this->transitionToConsignee($conversation, $state, $payload);

                    return;
                }

                $vin = strtoupper(trim($text));
                if (strlen($vin) !== 17) {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid VIN. A VIN must be exactly 17 characters.');

                    return;
                }

                // Check for duplicates in current session
                if (in_array($vin, $payload['vins'] ?? [])) {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ This VIN is already in your current pre-alert list.');

                    return;
                }

                $result = $this->vinLookupService->lookup($vin, (int) $conversation->contact_id);

                if ($result->outcome === VinLookupOutcome::VehicleReady || $result->outcome === VinLookupOutcome::FetchedFromApi) {
                    $vehicle = $result->vehicle;

                    $payload['vehicle_ids'][] = $vehicle->id;
                    $payload['vins'][] = $vehicle->vin;
                    $payload['current_vehicle_id'] = $vehicle->id;
                    $state->update(['data_payload' => $payload, 'current_step' => 'prealert_awaiting_gatepass']);

                    $msg = "🚗 *Vehicle Found:*\n{$vehicle->year} {$vehicle->make} {$vehicle->model}\n\n✅ Please enter the *Gate Pass PIN* for this vehicle:";
                    $this->waService->sendMessage($conversation->phone_number, $msg);
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '❌ '.$result->message."\n\nPlease send a valid VIN or type 'Menu' to cancel.");
                }
                break;

            case 'prealert_awaiting_gatepass':
                $id = $payload['current_vehicle_id'];
                $payload['vehicle_data'][$id]['gatepass_pin'] = $text;

                $state->update([
                    'current_step' => 'prealert_awaiting_auction_receipt',
                    'data_payload' => $payload,
                ]);

                $this->waService->sendMessage($conversation->phone_number, "✅ *PIN saved.*\n\nNow, please upload the *Auction Receipt* (Image or PDF) for this vehicle:");
                break;

            case 'prealert_awaiting_auction_receipt':
                if (! $mediaId) {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Please upload a valid Receipt (Image or PDF) for this vehicle.');

                    return;
                }

                $id = $payload['current_vehicle_id'];
                $localPath = $this->waService->downloadMedia($mediaId);

                if ($localPath && Storage::disk('public')->exists($localPath)) {
                    $tmpFilename = 'receipts/tmp/' . basename($localPath);
                    Storage::disk('public')->copy($localPath, $tmpFilename);
                    Storage::disk('public')->delete($localPath);
                    $payload['vehicle_data'][$id]['auction_receipt_tmp'] = $tmpFilename;
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Failed to process receipt. Please try uploading it again.');

                    return;
                }

                unset($payload['current_vehicle_id']);

                if (($payload['shipping_mode'] ?? '') === 'container' && count($payload['vehicle_ids']) < 5) {
                    $count = count($payload['vehicle_ids']);
                    $state->update(['current_step' => 'prealert_awaiting_vin', 'data_payload' => $payload]);
                    $msg = "✅ *Vehicle #{$count} enrichment complete.*\n\n👉 Send another *VIN* to add more.\n👉 Send *0* to continue to Consignee selection.";
                    $this->waService->sendMessage($conversation->phone_number, $msg);
                } else {
                    $this->transitionToConsignee($conversation, $state, $payload);
                }
                break;

            case 'prealert_awaiting_consignee':
                $consignees = $this->getConsignees($conversation);
                $index = (int) $cleanText - 1;

                if (isset($consignees[$index])) {
                    $payload['consignee_id'] = $consignees[$index]->id;

                    $state->update([
                        'current_step' => 'prealert_awaiting_notify_party',
                        'data_payload' => $payload,
                    ]);

                    $msg = "✅ *Consignee saved.*\n\nSelect a *Notify Party* (or send 0 to skip):";
                    foreach ($consignees as $i => $c) {
                        $msg .= "\n".($i + 1).". {$c->name}";
                    }
                    $msg .= "\n0. Skip (Same as Consignee)";

                    $this->waService->sendMessage($conversation->phone_number, $msg);
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid selection. Please choose a number from the list.');
                }
                break;

            case 'prealert_awaiting_notify_party':
                if ($cleanText === '0') {
                    $payload['notify_party_id'] = null;
                } else {
                    $consignees = $this->getConsignees($conversation);
                    $index = (int) $cleanText - 1;

                    if (isset($consignees[$index])) {
                        $payload['notify_party_id'] = $consignees[$index]->id;
                    } else {
                        $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid selection. Please choose a number from the list or 0 to skip.');

                        return;
                    }
                }

                $state->update([
                    'current_step' => 'prealert_awaiting_carrier',
                    'data_payload' => $payload,
                ]);

                $carriers = Carrier::orderBy('name')->take(15)->get();
                $msg = "✅ *Notify Party saved.*\n\nSelect a *Carrier*:";
                foreach ($carriers as $i => $car) {
                    $msg .= "\n".($i + 1).". {$car->name}";
                }

                $this->waService->sendMessage($conversation->phone_number, $msg);
                break;

            case 'prealert_awaiting_carrier':
                $carriers = Carrier::orderBy('name')->take(15)->get();
                $index = (int) $cleanText - 1;
                if (isset($carriers[$index])) {
                    $payload['carrier_id'] = $carriers[$index]->id;
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid selection. Please choose a number from the list.');

                    return;
                }

                $state->update([
                    'current_step' => 'prealert_awaiting_destination_port',
                    'data_payload' => $payload,
                ]);

                $ports = Port::where('type', 'destination')->orderBy('name')->take(15)->get();
                $msg = "✅ *Carrier saved.*\n\nSelect a *Destination Port*:";
                foreach ($ports as $i => $p) {
                    $msg .= "\n".($i + 1).". {$p->name}";
                }

                $this->waService->sendMessage($conversation->phone_number, $msg);
                break;

            case 'prealert_awaiting_destination_port':
                $ports = Port::where('type', 'destination')->orderBy('name')->take(15)->get();
                $index = (int) $cleanText - 1;
                if (isset($ports[$index])) {
                    $payload['destination_port_id'] = $ports[$index]->id;
                } else {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid selection. Please choose a number from the list.');

                    return;
                }

                if (($payload['shipping_mode'] ?? '') === 'container') {
                    $this->transitionToShipmentSelection($conversation, $state, $payload);
                } else {
                    $this->finalize($conversation, $payload);
                    $state->delete();
                }
                break;

            case 'prealert_awaiting_shipment_selection':
                if ($cleanText === '0') {
                    Log::channel('whatsapp')->info('User skipped shipment selection.');
                    $payload['shipment_id'] = null;
                } else {
                    $shipments = $this->getOpenShipments($conversation, count($payload['vehicle_ids'] ?? []));
                    $index = (int) $cleanText - 1;
                    Log::channel('whatsapp')->info('User selected shipment index: '.$index);

                    if (isset($shipments[$index])) {
                        $payload['shipment_id'] = $shipments[$index]->id;
                        Log::channel('whatsapp')->info('Shipment ID resolved: '.$payload['shipment_id']);
                    } else {
                        Log::channel('whatsapp')->warning('Invalid shipment selection: '.$cleanText);
                        $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid selection. Please choose a number from the list or 0 to skip.');

                        return;
                    }
                }

                $this->finalize($conversation, $payload);
                $state->delete();
                break;
        }
    }

    protected function transitionToConsignee(WhatsAppConversation $conversation, WhatsAppMenuState $state, array $payload, string $prefix = ''): void
    {
        $state->update([
            'current_step' => 'prealert_awaiting_consignee',
            'data_payload' => $payload,
        ]);

        $msg = ($prefix ? $prefix."\n\n" : '').'Next, select a *Consignee* by number:';
        $consignees = $this->getConsignees($conversation);
        foreach ($consignees as $index => $c) {
            $msg .= "\n".($index + 1).". {$c->name}";
        }

        $this->waService->sendMessage($conversation->phone_number, $msg);
    }

    protected function getConsignees(WhatsAppConversation $conversation)
    {
        return Consignee::where('shipper_id', $conversation->contact_id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->take(15)
            ->get();
    }

    protected function transitionToShipmentSelection(WhatsAppConversation $conversation, WhatsAppMenuState $state, array $payload): void
    {
        $currentVehicleCount = count($payload['vehicle_ids'] ?? []);
        $shipments = $this->getOpenShipments($conversation, $currentVehicleCount);

        if ($shipments->isEmpty()) {
            $this->finalize($conversation, $payload);
            $state->delete();

            return;
        }

        $state->update([
            'current_step' => 'prealert_awaiting_shipment_selection',
            'data_payload' => $payload,
        ]);

        $msg = "🚢 *Link to Existing Shipment (Optional)*\n\nYou can add these vehicles to an open container. Select one (or send 0 to skip):\n";
        foreach ($shipments as $i => $s) {
            $msg .= "\n".($i + 1).". {$s->reference_no} ({$s->vehicles_count}/{$s->capacity})";
        }
        $msg .= "\n0. Skip (Start New Container)";

        $this->waService->sendMessage($conversation->phone_number, $msg);
    }

    protected function getOpenShipments(WhatsAppConversation $conversation, int $currentPrealertCount)
    {
        return ShipmentModel::where('shipper_id', $conversation->contact_id)
            ->where('shipping_mode', 'container')
            ->where('shipment_status', ShipmentStatus::Open)
            ->whereNull('sealed_at')
            ->withCount('vehicles')
            ->get()
            ->filter(fn ($s) => ($s->vehicles_count + $currentPrealertCount) <= 5)
            ->values();
    }

    protected function finalize(WhatsAppConversation $conversation, array $data): void
    {
        Log::channel('whatsapp')->info('Finalizing Pre-Alert creation...');
        // 1. Create Prealert
        $prealert = Prealert::create([
            'shipper_id' => $conversation->contact_id,
            'consignee_id' => $data['consignee_id'],
            'notify_party_id' => $data['notify_party_id'],
            'carrier_id' => $data['carrier_id'],
            'destination_port_id' => $data['destination_port_id'],
            'shipment_id' => $data['shipment_id'] ?? null,
            'shipping_mode' => $data['shipping_mode'],
            'status' => PrealertStatus::Pending,
        ]);
        Log::channel('whatsapp')->info('Pre-Alert record created: '.$prealert->id);

        // 2. Link & Enrich Vehicles
        $vehicleCount = 0;
        foreach ($data['vehicle_ids'] ?? [] as $vId) {
            $vehicle = Vehicle::find($vId);
            if ($vehicle) {
                $vData = $data['vehicle_data'][$vId] ?? [];

                // Move from tmp to final receipts folder
                $finalPath = null;
                $tmpPath = $vData['auction_receipt_tmp'] ?? null;
                if ($tmpPath && Storage::disk('public')->exists($tmpPath)) {
                    $finalPath = str_replace('receipts/tmp/', 'receipts/', $tmpPath);
                    Storage::disk('public')->move($tmpPath, $finalPath);
                }

                $vehicle->update([
                    'prealert_id' => $prealert->id,
                    'gatepass_pin' => $vData['gatepass_pin'] ?? null,
                    'auction_receipt' => $finalPath,
                ]);
                $vehicleCount++;
            }
        }

        $vins = implode(', ', $data['vins'] ?? []);
        $this->waService->sendMessage(
            $conversation->phone_number,
            "🎉 *Pre-Alert Created Successfully!*\n\n*Vehicles ({$vehicleCount}):* {$vins}\n*Mode:* ".strtoupper($data['shipping_mode'])."\n*Status:* PENDING\n\nOur team will review your submission shortly."
        );

        // 3. Notify Admins/Staff (Mirrors web UI)
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        $recipientIds = User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->when($prealert->shipper?->user_id, fn ($q) => $q->push($prealert->shipper->user_id))
            ->unique()
            ->values();

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            try {
                Notification::send($recipients, new PrealertCreatedNotification($prealert));
            } catch (\Exception $e) {
                Log::channel('whatsapp')->error('Failed to send Pre-Alert notification: '.$e->getMessage());
            }
        }
    }
}
