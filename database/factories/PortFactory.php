<?php

namespace Database\Factories;

use App\Models\Port;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Port>
 */
class PortFactory extends Factory
{
    protected $model = Port::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $state = State::query()->inRandomOrder()->first() ?? State::factory()->create();
        $country = $state->country;

        return [
            'country_id' => $country->id,
            'state_id' => $state->id,
            'name' => fake()->words(2, true).' Port',
            'type' => fake()->randomElement(['origin', 'destination']),
        ];
    }
}
