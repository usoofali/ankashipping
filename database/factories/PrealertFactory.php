<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\Vehicle;
use App\Support\VinNormalizer;
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
        $vin = VinNormalizer::normalize('1H'.strtoupper(fake()->regexify('[0-9A-HJ-NPR-Z]{15}')));

        return [
            'shipper_id' => Shipper::factory(),
            'vin' => $vin,
            'gatepass_pin' => fake()->optional()->regexify('[A-Z0-9]{11}'),
            'vehicle_id' => null,
            'carrier_id' => null,
            'destination_port_id' => null,
            'auction_receipt' => null,
            'notes' => null,

        ];
    }

    public function withVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'vehicle_id' => $vehicle->id,
            'vin' => $vehicle->vin ?? VinNormalizer::normalize('1H'.strtoupper(fake()->regexify('[0-9A-HJ-NPR-Z]{15}'))),
        ]);
    }
}
