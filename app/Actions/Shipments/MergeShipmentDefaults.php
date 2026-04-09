<?php

declare(strict_types=1);

namespace App\Actions\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\DefaultShipmentSetting;
use BackedEnum;

/**
 * Merges global {@see DefaultShipmentSetting} values with explicit request/input attributes.
 * Global defaults affect all shippers; super-admins will edit the singleton row in a future UI.
 */
final class MergeShipmentDefaults
{
    /**
     * Keys always taken only from user input, never from defaults (caller supplies them).
     */
    private const array PASSTHROUGH_KEYS = [
        'reference_no',
        'gatepass_pin',
        'shipper_id',
        'consignee_id',
        'driver_id',
    ];

    /**
     * Keys filled from input when explicitly set, otherwise from the resolved defaults row
     * ({@see merge()} second argument, or {@see DefaultShipmentSetting::current()} when omitted).
     */
    private const array DEFAULTABLE_KEYS = [
        'carrier_id',
        'origin_port_id',
        'logistics_service',
        'shipping_mode',
        'shipment_status',
        'invoice_status',
        'payment_status',
        'payment_method_id',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function merge(array $input, ?DefaultShipmentSetting $defaults = null): array
    {
        $defaults ??= DefaultShipmentSetting::current();

        $result = [];

        foreach (self::PASSTHROUGH_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = self::normalizeScalar($input[$key]);
            }
        }

        foreach (self::DEFAULTABLE_KEYS as $key) {
            if (self::inputExplicitlySetsDefaultable($key, $input)) {
                $result[$key] = self::normalizeDefaultableValue($input[$key]);

                continue;
            }

            $fromDefault = $defaults->getAttribute($key);
            if ($fromDefault !== null) {
                $result[$key] = self::normalizeDefaultableValue($fromDefault);
            }
        }

        if (! array_key_exists('shipment_status', $result)) {
            $result['shipment_status'] = ShipmentStatus::Pending->value;
        }

        return $result;
    }

    private static function inputExplicitlySetsDefaultable(string $key, array $input): bool
    {
        if (! array_key_exists($key, $input)) {
            return false;
        }

        $value = $input[$key];

        if ($value === null) {
            return false;
        }

        return true;
    }

    private static function normalizeDefaultableValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return self::normalizeScalar($value);
    }

    private static function normalizeScalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
