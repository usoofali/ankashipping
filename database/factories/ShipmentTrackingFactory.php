<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentTracking>
 */
class ShipmentTrackingFactory extends Factory
{
    protected $model = ShipmentTracking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => ShipmentStatus::Booked->value,
            'workshop_id' => null,
            'note' => fake()->optional()->sentence(),
            'metadata' => null,
            'recorded_at' => now(),
        ];
    }

    public function atWorkshop(?Workshop $workshop = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShipmentStatus::AtWorkshop->value,
            'workshop_id' => $workshop?->id ?? Workshop::factory(),
        ]);
    }
}
