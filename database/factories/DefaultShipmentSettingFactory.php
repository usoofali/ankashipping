<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\DefaultShipmentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefaultShipmentSetting>
 */
class DefaultShipmentSettingFactory extends Factory
{
    protected $model = DefaultShipmentSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrier_id' => null,
            'origin_port_id' => null,
            'logistics_service' => null,
            'shipping_mode' => null,
            'shipment_status' => null,
            'invoice_status' => null,
            'payment_status' => null,
            'payment_method_id' => null,
        ];
    }

    public function withRoroOceanDraft(): static
    {
        return $this->state(fn (): array => [
            'logistics_service' => LogisticsService::Ocean,
            'shipping_mode' => ShippingMode::Roro,
            'shipment_status' => ShipmentStatus::Pending,
            'invoice_status' => InvoiceStatus::Draft,
            'payment_status' => PaymentStatus::Pending,
        ]);
    }
}
