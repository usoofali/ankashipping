<?php

declare(strict_types=1);

namespace App\Actions\Prealerts;

use App\Models\Prealert;
use App\Support\VinNormalizer;
use Illuminate\Support\Facades\Validator;

final class UpdatePrealert
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(Prealert $prealert, array $input): Prealert
    {
        if (array_key_exists('vin', $input)) {
            $input['vin'] = VinNormalizer::normalize((string) $input['vin']);
        }

        $validated = Validator::make($input, [
            'vin' => ['sometimes', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]+$/'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer', 'exists:vehicles,id'],
            'gatepass_pin' => ['sometimes', 'nullable', 'string', 'max:11'],
            'carrier_id' => ['sometimes', 'nullable', 'integer', 'exists:carriers,id'],
            'destination_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'auction_receipt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string'],
            'submitted_at' => ['sometimes', 'nullable', 'date'],
            'reviewed_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $prealert->fill($validated)->save();

        return $prealert->fresh() ?? $prealert;
    }
}
