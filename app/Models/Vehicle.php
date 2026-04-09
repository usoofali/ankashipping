<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleIs;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Belongs to at most one {@see Shipment}. A unique vehicle_id enforces at most one vehicle per shipment
 * when vehicle_id is non-null on the shipment. Rows may exist without a shipment (pre-shipment / prealert). VIN is unique when present.
 *
 * Copart/IAAI API rows store `api_snapshot` with first-class `car_photo` (gallery URLs), `sales_history`,
 * `currency`, plus full `result_item` for parity with the provider payload.
 */
final class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'vin',
        'lot_number',
        'make',
        'model',
        'year',
        'series',
        'body_style',
        'color',
        'vehicle_type',
        'vehicle_is',
        'transmission',
        'fuel',
        'engine_type',
        'drive',
        'cylinders',
        'odometer',
        'car_keys',
        'doc_type',
        'auction_receipt',
        'primary_damage',
        'secondary_damage',
        'highlights',
        'location',
        'auction_name',
        'seller',
        'est_retail_value',
        'is_insurance',
        'currency_code_id',
        'api_snapshot',
        'api_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_is' => VehicleIs::class,
            'is_insurance' => 'boolean',
            'api_snapshot' => 'array',
            'api_fetched_at' => 'datetime',
        ];
    }

    /**
     * Copart/IAAI RapidAPI `car_photo` object from the `api_snapshot` JSON (`id`, `all_lots_id`, `photo` URL list).
     * Prefer {@see self::copartCarPhotoUrls()} for image galleries.
     *
     * @return array{id?: int, all_lots_id?: string, photo?: list<string>}|null
     */
    public function copartCarPhoto(): ?array
    {
        $snap = $this->api_snapshot;
        if (! is_array($snap)) {
            return null;
        }

        $block = $snap['car_photo'] ?? null;

        return is_array($block) ? $block : null;
    }

    /**
     * Absolute image URLs from the Copart-style `car_photo.photo` array.
     *
     * @return list<string>
     */
    public function copartCarPhotoUrls(): array
    {
        $block = $this->copartCarPhoto();
        if ($block === null) {
            return [];
        }

        $photos = $block['photo'] ?? [];
        if (! is_array($photos)) {
            return [];
        }

        $out = [];
        foreach ($photos as $url) {
            if (is_string($url) && $url !== '') {
                $out[] = $url;
            }
        }

        return array_values($out);
    }

    /**
     * @return HasOne<Shipment, $this>
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
