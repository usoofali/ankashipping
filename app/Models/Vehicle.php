<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\VehicleIs;
use App\Enums\VehicleStatus;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'shipment_id',
        'prealert_id',
        'driver_id',
        'workshop_id',
        'tracking_status',
        'status_before_workshop',
        'gatepass_pin',
        'value',
        'weight',
        'weight_unit',
        'measurement',
        'measurement_unit',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_is' => VehicleIs::class,
            'is_insurance' => 'boolean',
            'api_snapshot' => 'array',
            'api_fetched_at' => 'datetime',
            'tracking_status' => VehicleStatus::class,
            'status_before_workshop' => ShipmentStatus::class,
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
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<Prealert, $this>
     */
    public function prealert(): BelongsTo
    {
        return $this->belongsTo(Prealert::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return HasMany<VehicleTracking, $this>
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(VehicleTracking::class);
    }

    /**
     * Vehicle-level documents (decoupled from shipment documents).
     *
     * @return HasMany<VehicleDocument, $this>
     */
    public function vehicleDocuments(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function updateStatus(VehicleStatus $status, ?string $note = null): void
    {
        $this->update(['tracking_status' => $status]);

        $this->trackings()->create([
            'status' => $status,
            'note' => $note,
            'recorded_at' => now(),
        ]);
    }
}
