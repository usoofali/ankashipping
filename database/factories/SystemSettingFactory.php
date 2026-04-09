<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'logo' => null,
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'zipcode' => fake()->postcode(),
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'auction_api_key' => null,
            'whatsapp_api_key' => null,
            'tracking_delivery_prefix' => 'MRF',
            'tracking_digits' => 5,
            'tracking_number_type' => 'auto_increment',
            'tracking_random_digits' => 10,
        ];
    }
}
