<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\ChargeItem;
use App\Models\Shipper;

final class InvoiceLineAmountResolver
{
    /**
     * @return array{gross: string, discount: string, net: string}
     */
    public function resolveDiscountLine(ChargeItem $chargeItem, ?Shipper $shipper): array
    {
        $gross = (float) $chargeItem->default_amount;
        $shipperDiscount = (float) ($shipper?->discount_amount ?? 0);

        if ($shipperDiscount <= 0) {
            return $this->pack($gross, 0.0, $gross);
        }

        $discount = min($gross, $shipperDiscount);
        $net = max(0.0, $gross - $discount);

        return $this->pack($gross, $discount, $net);
    }

    /**
     * @return array{gross: string, discount: string, net: string}
     */
    public function resolveStandardLine(float $amount): array
    {
        return $this->pack($amount, 0.0, $amount);
    }

    /**
     * @return array{gross: string, discount: string, net: string}
     */
    private function pack(float $gross, float $discount, float $net): array
    {
        return [
            'gross' => number_format($gross, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'net' => number_format($net, 2, '.', ''),
        ];
    }
}
