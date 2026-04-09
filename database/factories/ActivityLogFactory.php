<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shipment_id' => null,
            'action' => fake()->words(3, true),
            'properties' => null,
        ];
    }

    public function forShipment(Shipment $shipment): static
    {
        return $this->state(fn (array $attributes) => [
            'shipment_id' => $shipment->id,
        ]);
    }
}
