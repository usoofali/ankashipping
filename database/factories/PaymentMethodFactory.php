<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Bank Transfer', 'Card', 'Cash', 'Wallet']);

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
        ];
    }
}
