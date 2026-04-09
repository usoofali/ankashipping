<?php

namespace Database\Factories;

use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentDocumentFile>
 */
class ShipmentDocumentFileFactory extends Factory
{
    protected $model = ShipmentDocumentFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->lexify('????').'.pdf';

        return [
            'shipment_document_id' => ShipmentDocument::factory(),
            'path' => 'documents/'.fake()->uuid().'/'.$name,
            'original_name' => $name,
            'uploaded_by' => null,
        ];
    }

    public function uploadedByUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => User::factory(),
        ]);
    }
}
