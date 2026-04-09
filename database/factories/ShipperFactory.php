<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipper>
 */
class ShipperFactory extends Factory
{
    protected $model = Shipper::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = City::factory()->create();

        return [
            'user_id' => User::factory(),
            'company_name' => fake()->optional()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'discount_amount' => 0,
        ];
    }
}
