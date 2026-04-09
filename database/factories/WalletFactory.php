<?php

namespace Database\Factories;

use App\Models\Shipper;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipper_id' => Shipper::factory(),
            'balance' => fake()->randomFloat(2, 0, 10000),
        ];
    }
}
