<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => TransactionType::Credit->value,
            'amount' => fake()->randomFloat(2, 10, 1000),
            'balance_after' => fake()->optional()->randomFloat(2, 0, 10000),
            'description' => fake()->optional()->sentence(),
            'reference' => fake()->optional()->bothify('REF-####'),
        ];
    }
}
