<?php

declare(strict_types=1);

namespace App\Actions\Prealerts;

use App\Enums\PrealertStatus;
use App\Models\Prealert;
use App\Support\VinNormalizer;
use Illuminate\Support\Facades\Validator;

final class CreatePrealert
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input): Prealert
    {
        if (array_key_exists('vin', $input)) {
            $input['vin'] = VinNormalizer::normalize((string) $input['vin']);
        }

        $validated = Validator::make($input, [
            'shipper_id' => ['required', 'integer', 'exists:shippers,id'],
            'vin' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]+$/'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'gatepass_pin' => ['nullable', 'string', 'max:11'],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'auction_receipt' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
            'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ])->validate();

        $validated['status'] ??= PrealertStatus::Draft->value;

        return Prealert::query()->create($validated);
    }
}
