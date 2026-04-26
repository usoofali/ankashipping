<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        self::updated(static function (Shipment $shipment) {
            if ($shipment->wasChanged('shipment_status') && $shipment->shipment_status === ShipmentStatus::Cancelled) {
                $shipment->vehicles()->update(['shipment_id' => null]);
            }
        });
    }

    protected $fillable = [
        'reference_no',
        'shipper_id',
        'consignee_id',
        'carrier_id',
        'origin_port_id',
        'destination_port_id',
        'logistics_service',
        'shipping_mode',
        'shipment_status',
        'invoice_status',
        'payment_status',
        'payment_method_id',
        'capacity',
        'sealed_at',
        'completed_at',
        'seal_closed_at',
        'bill_of_lading_number',
        'booking_number',
        'itn_number',
        'container_no',
        'seal_no',
        'container_type',
        'vessel_name',
        'voyage_no',
        'cut_off_date',
        'departure_date',
        'arrival_date',
        'domestic_routing',
        'loading_pier',
        'notify_party_id',
        'exporter_name',
        'exporter_address',
        'exporter_state',
        'exporter_country',
        'exporter_zipcode',
    ];

    protected function casts(): array
    {
        return [
            'logistics_service' => LogisticsService::class,
            'shipping_mode' => ShippingMode::class,
            'shipment_status' => ShipmentStatus::class,
            'invoice_status' => InvoiceStatus::class,
            'payment_status' => PaymentStatus::class,
            'shipment_status_before_workshop' => ShipmentStatus::class,
            'sealed_at' => 'datetime',
            'completed_at' => 'datetime',
            'seal_closed_at' => 'datetime',
            'cut_off_date' => 'date',
            'departure_date' => 'date',
            'arrival_date' => 'date',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Consignee::class);
    }

    public function notifyParty(): BelongsTo
    {
        return $this->belongsTo(Consignee::class, 'notify_party_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Legacy/Helper for RoRo shipments which have exactly one vehicle.
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->oldestOfMany();
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Human-readable status for UI: when at workshop, prefer the linked workshop name.
     */
    public function shipmentStatusDisplay(): string
    {
        if ($this->shipment_status === null) {
            return '—';
        }

        return $this->shipment_status->name;
    }

    public function isLocked(): bool
    {
        return in_array($this->shipment_status, [
            ShipmentStatus::Completed,
            ShipmentStatus::Cancelled,
        ], true);
    }

    public function isRoRo(): bool
    {
        return $this->shipping_mode === ShippingMode::Roro;
    }

    /**
     * @return BelongsTo<Workshop, $this>
     *
     * @deprecated Logistics moved to Vehicle level
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function isContainer(): bool
    {
        return $this->shipping_mode === ShippingMode::Container;
    }

    public function isSealed(): bool
    {
        return $this->sealed_at !== null;
    }
}
