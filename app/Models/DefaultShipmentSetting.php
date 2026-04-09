<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use Database\Factories\DefaultShipmentSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global defaults applied when creating shipments (excluding shipper, consignee, driver, reference).
 * Super admins will manage this row via a future settings UI.
 */
final class DefaultShipmentSetting extends Model
{
    /** @use HasFactory<DefaultShipmentSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'origin_port_id',
        'logistics_service',
        'shipping_mode',
        'shipment_status',
        'invoice_status',
        'payment_status',
        'payment_method_id',
    ];

    protected function casts(): array
    {
        return [
            'logistics_service' => LogisticsService::class,
            'shipping_mode' => ShippingMode::class,
            'shipment_status' => ShipmentStatus::class,
            'invoice_status' => InvoiceStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    /**
     * Singleton row for application-wide default shipment options.
     */
    public static function current(): self
    {
        $existing = self::query()->first();

        if ($existing instanceof self) {
            return $existing;
        }

        return self::query()->create([]);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
