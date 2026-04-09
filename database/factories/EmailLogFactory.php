<?php

namespace Database\Factories;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailable_class' => fake()->optional()->word(),
            'recipient_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body' => fake()->optional()->paragraph(),
            'status' => EmailLogStatus::Pending->value,
            'meta' => null,
        ];
    }
}
