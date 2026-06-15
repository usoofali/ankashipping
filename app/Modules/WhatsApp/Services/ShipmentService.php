<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\ShipmentStatus;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;

class ShipmentService
{
    public function __construct(protected WhatsAppService $waService) {}

    public function track(WhatsAppConversation $conversation, string $query): void
    {
        $query = trim($query);

        // 1. Search by Reference Number
        $shipment = Shipment::where('reference_no', $query)->first();

        // 2. Search by VIN
        if (! $shipment) {
            $vehicle = Vehicle::where('vin', $query)->first();
            if ($vehicle && $vehicle->shipment_id) {
                $shipment = $vehicle->shipment;
            }
        }

        // 2. Security Check: Verify Ownership
        if ($shipment && ! $this->userHasAccess($conversation, $shipment)) {
            $shipment = null; // Treat as not found if they don't own it
        }

        if (! $shipment) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Shipment Not Found*\n\nPlease check the VIN or Reference and try again.");

            return;
        }

        // 3. Prepare Detailed Message
        $status = $shipment->shipment_status ? $shipment->shipment_status->name : 'N/A';
        $mode = $shipment->shipping_mode ? ucfirst($shipment->shipping_mode->value) : 'N/A';
        $origin = $shipment->originPort ? $shipment->originPort->name : 'N/A';
        $destination = $shipment->destinationPort ? $shipment->destinationPort->name : 'N/A';
        $eta = $shipment->arrival_date ? $shipment->arrival_date->format('d M Y') : 'N/A';

        $message = "🚗 *Shipment Status*

            *Reference:* {$shipment->reference_no}
            *Status:* {$status}
            *Mode:* {$mode}";

        if ($shipment->isContainer() && $shipment->vehicles) {
            $message .= " ({$shipment->vehicles->count()} Vehicles)";
        }

        $message .= "\n*Origin:* {$origin}\n*Destination:* {$destination}\n*ETA:* {$eta}";

        // 4. Advanced Logistics (Inland, Delivered, Loaded, Completed)
        $advancedStatuses = [
            ShipmentStatus::Inland,
            ShipmentStatus::Delivered,
            ShipmentStatus::Loaded,
            ShipmentStatus::Completed,
        ];

        if (in_array($shipment->shipment_status, $advancedStatuses)) {
            $logistics = "\n\n📦 *Logistics Details*";
            $hasLogistics = false;

            $fields = [
                'Booking' => 'booking_number',
                'B/L' => 'bill_of_lading_number',
                'ITN' => 'itn_number',
                'Container #' => 'container_no',
                'Seal #' => 'seal_no',
                'Container Type' => 'container_type',
                'Vessel' => 'vessel_name',
                'Voyage' => 'voyage_no',
            ];

            foreach ($fields as $label => $field) {
                if ($shipment->$field) {
                    $logistics .= "\n*{$label}:* ".$shipment->$field;
                    $hasLogistics = true;
                }
            }

            $dateFields = [
                'Cut-off' => 'cut_off_date',
                'Departure' => 'departure_date',
                'Arrival' => 'arrival_date',
            ];

            foreach ($dateFields as $label => $field) {
                if ($shipment->$field) {
                    $logistics .= "\n*{$label}:* ".$shipment->$field->format('d M Y');
                    $hasLogistics = true;
                }
            }

            if ($hasLogistics) {
                $message .= $logistics;
            }
        }

        // 5. Vehicle Details
        if ($shipment->isContainer() && $shipment->vehicles->isNotEmpty()) {
            $message .= "\n\n🚗 *Vehicles in Container:*";
            foreach ($shipment->vehicles as $index => $vehicle) {
                $this->addVehicleDetail($message, $vehicle, $index + 1);
            }
        } elseif ($shipment->isRoRo() && $shipment->vehicle) {
            $message .= "\n\n🚗 *Vehicle Details:*";
            $this->addVehicleDetail($message, $shipment->vehicle);
        }

        $message .= "\n\n💡 _Send another VIN to track more, or type *'Menu'* to go back._";

        $this->waService->sendMessage($conversation->phone_number, $message);
    }

    protected function addVehicleDetail(string &$message, Vehicle $vehicle, ?int $index = null): void
    {
        $vIs = $vehicle->vehicle_is ? $vehicle->vehicle_is->label() : 'N/A';
        $vStatus = $vehicle->tracking_status ? $vehicle->tracking_status->name : 'N/A';
        $vKeys = $vehicle->car_keys ? 'Yes' : 'No';

        $prefix = $index ? "\n\n{$index}. " : "\n\n";
        $message .= "{$prefix}*{$vehicle->year} {$vehicle->make} {$vehicle->model}*";
        $message .= "\n   *VIN:* {$vehicle->vin}";
        $message .= "\n   *Condition:* {$vIs}";
        $message .= "\n   *Keys:* {$vKeys}";
        $message .= "\n   *Status:* {$vStatus}";
    }

    protected function userHasAccess(WhatsAppConversation $conversation, Shipment $shipment): bool
    {
        $contactType = $conversation->contact_type;
        $contactId = $conversation->contact_id;

        // Staff can see everything
        if ($contactType === Staff::class) {
            return true;
        }

        // Shippers can see only their own shipments
        if ($contactType === Shipper::class) {
            return (int) $shipment->shipper_id === (int) $contactId;
        }

        // Drivers can see shipments assigned to them
        if ($contactType === Driver::class) {
            // Check if assigned to shipment
            if ((int) $shipment->driver_id === (int) $contactId) {
                return true;
            }

            // Check if assigned to any vehicle in this shipment
            return $shipment->vehicles()->where('driver_id', $contactId)->exists();
        }

        return false;
    }
}
