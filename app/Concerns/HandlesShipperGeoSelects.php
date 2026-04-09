<?php

declare(strict_types=1);

namespace App\Concerns;

trait HandlesShipperGeoSelects
{
    public ?int $country_id = null;

    public ?int $state_id = null;

    public ?int $city_id = null;

    public function updatedCountryId(): void
    {
        $this->state_id = null;
        $this->city_id = null;
    }

    public function updatedStateId(): void
    {
        $this->city_id = null;
    }
}
