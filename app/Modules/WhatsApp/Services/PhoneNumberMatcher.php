<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Models\Driver;
use App\Models\Shipper;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PhoneNumberMatcher
{
    /**
     * Normalize the incoming WhatsApp phone number and match it against system models.
     *
     * @return Model|null (Shipper, Staff, or Driver)
     */
    public function match(string $phoneNumber): ?Model
    {
        $normalized = $this->normalize($phoneNumber);

        // Search Priority: Staff -> Shipper -> Driver
        return Staff::where('phone', $normalized)->first()
            ?? Shipper::where('phone', $normalized)->first()
            ?? Driver::where('phone', $normalized)->first();
    }

    /**
     * Convert WhatsApp format (digits only) to E.164 (+ digits).
     */
    public function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (! Str::startsWith($digits, '+')) {
            return '+'.$digits;
        }

        return $digits;
    }
}
