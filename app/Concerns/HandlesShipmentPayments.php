<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Actions\Financial\ProcessWalletPaymentAction;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;

trait HandlesShipmentPayments
{
    /**
     * Process a wallet payment for a shipment.
     */
    public function processShipmentPayment(Shipment $shipment): bool
    {
        $this->authorize('shipments.pay');

        $shipper = $shipment->shipper;

        if (! $shipper) {
            $this->notification()->error(__('Payment Error'), __('Missing shipper data.'));

            return false;
        }

        try {
            $action = app(ProcessWalletPaymentAction::class);
            $success = $action->execute($shipment, $shipper, Auth::user());

            if ($success) {
                $this->notification()->success(__('Payment Successful'), __('Payment for :ref has been processed.', ['ref' => $shipment->reference_no]));
            }

            return $success;
        } catch (\Throwable $e) {
            $this->notification()->error(__('Payment Failed'), $e->getMessage());

            return false;
        }
    }
}
