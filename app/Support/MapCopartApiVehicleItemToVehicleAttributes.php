<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Vehicle;

/**
 * Maps RapidAPI Copart/IAAI `result[]` item into attributes for {@see Vehicle}.
 *
 * @param  array<string, mixed>  $item
 * @param  array<string, mixed>  $envelope  Full API JSON (for snapshot / quota)
 * @return array<string, mixed>
 */
final class MapCopartApiVehicleItemToVehicleAttributes
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public static function map(array $item, array $envelope = []): array
    {
        $year = $item['year'] ?? null;
        $lot = $item['lots'][0] ?? [];

        $cylinders = $item['cylinders'] ?? null;
        $cylindersInt = is_numeric($cylinders) ? (int) $cylinders : null;

        $carKeys = $lot['keys_available'] ?? null;
        $carKeysString = match (true) {
            $carKeys === true, $carKeys === 1, $carKeys === '1' => '1',
            $carKeys === false, $carKeys === 0, $carKeys === '0' => '0',
            default => null,
        };
        $loc = $lot['location'] ?? [];

        $locationName = null;

        if (isset($loc['location']['name'])) {
            $locationName = $loc['location']['name'];
        } else {
            $locationName = ucwords($loc['city']['name']).', '.strtoupper($loc['state']['name']) ?? null;
        }

        $photos = $lot['images']['normal'] ?? $lot['images']['small'] ?? [];

        $snapshot = [
            'provider' => 'copart_iaai_rapidapi',
            'api_request_left' => $envelope['api_request_left'] ?? null,
            'provider_vehicle_id' => $item['id'] ?? null,
            'car_photo' => ['photo' => array_values((array) $photos)],
            'sales_history' => [],
            'active_bidding' => [],
            'buy_now_car' => $lot['buy_now'] ?? null,
            'currency' => null,
            'result_item' => $item,
        ];

        return [
            'vin' => isset($item['vin']) ? VinNormalizer::normalize((string) $item['vin']) : null,
            'lot_number' => isset($lot['lot']) ? (string) $lot['lot'] : null,
            'make' => self::stringOrNull($item['manufacturer']['name'] ?? null),
            'model' => self::stringOrNull($item['model']['name'] ?? null),
            'year' => $year !== null ? (string) $year : null,
            'series' => null,
            'body_style' => self::stringOrNull($item['body_type']['name'] ?? null),
            'color' => self::stringOrNull($item['color']['name'] ?? null),
            'vehicle_type' => self::stringOrNull($item['vehicle_type']['name'] ?? null),
            'transmission' => self::stringOrNull($item['transmission']['name'] ?? null),
            'fuel' => self::stringOrNull($item['fuel']['name'] ?? null),
            'engine_type' => self::stringOrNull($item['engine']['name'] ?? null),
            'drive' => self::stringOrNull($item['drive_wheel']['name'] ?? null),
            'cylinders' => $cylindersInt,
            'odometer' => isset($lot['odometer']['mi']) ? (int) $lot['odometer']['mi'] : null,
            'car_keys' => $carKeysString,
            'doc_type' => self::stringOrNull($lot['title']['name'] ?? null),
            'primary_damage' => self::stringOrNull($lot['damage']['main']['name'] ?? null),
            'secondary_damage' => self::stringOrNull($lot['damage']['second']['name'] ?? null),
            'highlights' => null,
            'location' => self::stringOrNull($locationName),
            'auction_name' => self::stringOrNull($lot['domain']['name'] ?? $lot['selling_branch']['name'] ?? null),
            'vehicle_is' => null,
            'seller' => self::stringOrNull($lot['seller'] ?? null),
            'est_retail_value' => isset($lot['pre_accident_price'])
                ? number_format((float) $lot['pre_accident_price'], 2, '.', '')
                : null,
            'is_insurance' => false,
            'currency_code_id' => null,
            'api_snapshot' => $snapshot,
            'api_fetched_at' => now(),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_array($value)) {
            // Some API text fields return arrays instead of a flat string
            $value = $value['name'] ?? $value['city'] ?? $value['raw'] ?? json_encode($value);

            if (is_array($value)) {
                $value = json_encode($value);
            }
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
