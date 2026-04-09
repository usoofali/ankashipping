<?php

namespace Database\Factories;

use App\Models\EmailAttempt;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAttempt>
 */
class EmailAttemptFactory extends Factory
{
    protected $model = EmailAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_log_id' => EmailLog::factory(),
            'exception_message' => fake()->optional()->sentence(),
            'smtp_response' => fake()->optional()->text(),
            'attempted_at' => now(),
        ];
    }
}
