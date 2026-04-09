<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\City;
use App\Models\State;
use Illuminate\Validation\Validator;

final class ShipperGeoValidator
{
    public static function assertHierarchy(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $data = $validator->getData();

        $countryId = $data['country_id'] ?? null;
        $stateId = $data['state_id'] ?? null;
        $cityId = $data['city_id'] ?? null;

        if (! is_numeric($countryId) || ! is_numeric($stateId) || ! is_numeric($cityId)) {
            return;
        }

        $state = State::query()->find((int) $stateId);
        if (! $state || (int) $state->country_id !== (int) $countryId) {
            $validator->errors()->add('state_id', __('The selected state does not belong to the chosen country.'));

            return;
        }

        $city = City::query()->find((int) $cityId);
        if (! $city || (int) $city->state_id !== (int) $stateId) {
            $validator->errors()->add('city_id', __('The selected city does not belong to the chosen state.'));
        }
    }
}
