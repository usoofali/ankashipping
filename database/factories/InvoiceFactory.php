<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $tax = round($subtotal * 0.1, 2);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'shipment_id' => Shipment::factory(),
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax,
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ];
    }
}
