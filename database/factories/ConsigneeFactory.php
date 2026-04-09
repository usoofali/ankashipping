<?php

namespace Database\Factories;

use App\Models\Consignee;
use App\Models\Shipper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consignee>
 */
class ConsigneeFactory extends Factory
{
    protected $model = Consignee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipper_id' => Shipper::factory(),
            'name' => fake()->name(),
            'address' => fake()->optional()->address(),
            'is_default' => false,
        ];
    }
}
