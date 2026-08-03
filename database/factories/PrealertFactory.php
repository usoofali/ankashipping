<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PrealertStatus;
use App\Enums\ShippingMode;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prealert>
 */
class PrealertFactory extends Factory
{
    protected $model = Prealert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipper_id' => Shipper::factory(),
            'consignee_id' => Consignee::factory(),
            'carrier_id' => Carrier::factory(),
            'destination_port_id' => Port::factory(),
            'shipping_mode' => fake()->randomElement(ShippingMode::cases()),
            'notes' => fake()->sentence(),
            'status' => PrealertStatus::Pending,
        ];
    }
}
