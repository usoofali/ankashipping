<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\Shipper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shipper = Shipper::factory();

        return [
            'reference_no' => fake()->unique()->bothify('REF-########'),
            'gatepass_pin' => fake()->optional()->regexify('[A-Z0-9]{11}'),
            'shipper_id' => $shipper,
            'consignee_id' => Consignee::factory()->for($shipper),
            'driver_id' => null,

            'carrier_id' => Carrier::factory(),
            'origin_port_id' => Port::factory(),
            'destination_port_id' => Port::factory(),
            'logistics_service' => LogisticsService::Ocean->value,
            'shipping_mode' => ShippingMode::Container->value,
            'shipment_status' => ShipmentStatus::Pending->value,
            'invoice_status' => InvoiceStatus::Draft->value,
            'payment_status' => PaymentStatus::Pending->value,
            'payment_method_id' => null,
        ];
    }
}
