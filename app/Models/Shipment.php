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

    protected $fillable = [
        'reference_no',
        'vin',
        'gatepass_pin',
        'shipper_id',
        'consignee_id',
        'driver_id',
        'vehicle_id',
        'carrier_id',
        'origin_port_id',
        'destination_port_id',
        'auction_receipt',
        'logistics_service',
        'shipping_mode',
        'shipment_status',
        'invoice_status',
        'payment_status',
        'payment_method_id',
        'workshop_id',
        'shipment_status_before_workshop',
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
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
     * Shipment belongs to a specific Vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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

        if ($this->shipment_status === ShipmentStatus::AtWorkshop) {
            $this->loadMissing('workshop');
            $workshopName = $this->workshop?->name;
            if ($workshopName !== null && $workshopName !== '') {
                return $workshopName;
            }
        }

        return $this->shipment_status->name;
    }

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
