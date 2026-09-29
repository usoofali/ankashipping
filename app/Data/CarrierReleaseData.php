<?php

declare(strict_types=1);

namespace App\Data;

final class CarrierReleaseData
{
    public function __construct(
        public readonly string $carrier,
        public readonly string $releaseType,
        public readonly string $vin,
        public readonly ?string $blNumber,
        public readonly ?string $pinNumber,
        public readonly ?string $vessel,
        public readonly ?string $voyage,
        public readonly ?string $pol,
        public readonly ?string $pod,
        public readonly ?string $consignee,
        public readonly string $officialReleaseText,
        public readonly ?string $rawSubject = null,
    ) {}

    public function isSallaum(): bool
    {
        return $this->carrier === 'sallaum';
    }

    public function isGrimaldiAcl(): bool
    {
        return $this->carrier === 'grimaldi_acl';
    }

    public function isTelexRelease(): bool
    {
        return $this->releaseType === 'telex_release';
    }

    public function isSeawayBill(): bool
    {
        return $this->releaseType === 'seaway_bill';
    }
}
