<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vin' => strtoupper(fake()->bothify('??################')),
            'lot_number' => (string) fake()->numerify('########'),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Ford']),
            'model' => fake()->word(),
            'year' => (string) fake()->numberBetween(2000, (int) date('Y')),
            'odometer' => fake()->numberBetween(1000, 200000),
            'color' => fake()->colorName(),
            'vehicle_type' => 'automobile',
            'auction_receipt' => null,
            'is_insurance' => fake()->boolean(),
        ];
    }

    public function withoutShipment(): static
    {
        return $this->state(fn (): array => []);
    }
}
