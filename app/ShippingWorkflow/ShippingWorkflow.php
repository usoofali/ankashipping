<?php

declare(strict_types=1);

namespace App\ShippingWorkflow;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;

class ShippingWorkflow
{
    public function canAssignDriver(Shipment $shipment, ?Vehicle $vehicle = null): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($shipment->shipping_mode === ShippingMode::Roro) {
            return $shipment->vehicles()->exists()
                && in_array($shipment->shipment_status, [
                    ShipmentStatus::Pending,
                    ShipmentStatus::Dispatched,
                    ShipmentStatus::Booking,
                    ShipmentStatus::Inland,
                ], true);
        }

        return $vehicle !== null
            && in_array($vehicle->tracking_status, [
                VehicleStatus::Pending,
                VehicleStatus::Dispatched,
                VehicleStatus::Inland,
            ], true);
    }

    public function canAttachTitle(Shipment $shipment, Vehicle $vehicle): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($shipment->shipping_mode === ShippingMode::Roro) {
            if ($shipment->booked_without_title) {
                return in_array($shipment->shipment_status, [
                    ShipmentStatus::Booking,
                    ShipmentStatus::Inland,
                    ShipmentStatus::Delivered,
                    ShipmentStatus::Loaded,
                    ShipmentStatus::TelexRequested,
                ], true);
            }

            return $shipment->shipment_status === ShipmentStatus::Dispatched
                && $vehicle->tracking_status === VehicleStatus::Dispatched;
        }

        return $vehicle->tracking_status === VehicleStatus::Dispatched;
    }

    public function canAttachPhotos(Shipment $shipment, Vehicle $vehicle): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($shipment->shipping_mode === ShippingMode::Roro) {
            // Photos allowed from BOOKING status onwards
            return in_array($shipment->shipment_status, [
                ShipmentStatus::Booking,
                ShipmentStatus::Inland,
                ShipmentStatus::Delivered,
                ShipmentStatus::Loaded,
                ShipmentStatus::TelexRequested,
            ], true);
        }

        // Container allowed when INLAND, moves to AT_WAREHOUSE
        return $vehicle->tracking_status === VehicleStatus::Inland;
    }

    public function canMarkFilled(Shipment $shipment, bool $force = false): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled || $shipment->shipping_mode !== ShippingMode::Container) {
            return false;
        }

        if ($shipment->shipment_status !== ShipmentStatus::Open) {
            return false;
        }

        if ($force) {
            return true;
        }

        $count = $shipment->vehicles()->count();
        $allAtWarehouse = $shipment->vehicles()->where('tracking_status', '!=', VehicleStatus::AtWarehouse)->count() === 0;

        return $count >= 4 && $allAtWarehouse;
    }

    public function canAttachDockReceipt(Shipment $shipment): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        return in_array($shipment->shipment_status, [
            ShipmentStatus::Inland,
            ShipmentStatus::Delivered,
            ShipmentStatus::Loaded,
            ShipmentStatus::TelexRequested,
        ], true);
    }

    public function canAttachBL(Shipment $shipment): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        return $shipment->shipment_status === ShipmentStatus::Delivered;
    }

    public function canRequestTelex(Shipment $shipment, ?User $user = null): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($shipment->payment_status !== PaymentStatus::Paid) {
            return false;
        }

        if (! in_array($shipment->shipment_status, [ShipmentStatus::Loaded, ShipmentStatus::TelexRequested, ShipmentStatus::Completed], true)) {
            return false;
        }

        if ($user !== null && $user->hasRole('shipper')) {
            if (! $user->can('workflow.request_telex')) {
                return false;
            }

            if ($shipment->shipper_id !== $user->shipper?->id) {
                return false;
            }
        }

        return true;
    }

    public function canSubmitTelexRelease(Shipment $shipment, ?User $user = null): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($user !== null && ! $user->can('workflow.submit_telex')) {
            return false;
        }

        return in_array($shipment->shipment_status, [
            ShipmentStatus::Loaded,
            ShipmentStatus::TelexRequested,
            ShipmentStatus::Completed,
        ], true);
    }

    public function canCompleteShipment(Shipment $shipment): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        return in_array($shipment->shipment_status, [
            ShipmentStatus::Loaded,
            ShipmentStatus::TelexRequested,
        ], true);
    }

    public function canMarkDelivered(Shipment $shipment): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        return $shipment->shipment_status === ShipmentStatus::Inland;
    }

    public function canMarkLoaded(Shipment $shipment): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        return $shipment->shipment_status === ShipmentStatus::Delivered;
    }

    /**
     * @return array<string>
     */
    public function allowedVehicleDocumentTypes(Shipment $shipment, Vehicle $vehicle): array
    {
        $allowed = [VehicleDocumentType::Other->value];

        if ($this->canAttachTitle($shipment, $vehicle)) {
            $allowed[] = VehicleDocumentType::TitleDocument->value;
        }

        if ($this->canAttachPhotos($shipment, $vehicle)) {
            $allowed[] = VehicleDocumentType::PhotosAndVideos->value;
        }

        return $allowed;
    }

    /**
     * @return array<string>
     */
    public function allowedShipmentDocumentTypes(Shipment $shipment): array
    {
        $allowed = [ShipmentDocumentType::Other->value];

        if ($this->canAttachDockReceipt($shipment)) {
            $allowed[] = ShipmentDocumentType::StampDockReceipt->value;
        }

        if ($this->canAttachBL($shipment)) {
            $allowed[] = ShipmentDocumentType::BillOfLading->value;
        }

        return $allowed;
    }

    public function canViewInvoice(Shipment $shipment, User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $status = $shipment->invoice?->status ?? $shipment->invoice_status;

        if ($status === InvoiceStatus::Draft) {
            return $user->can('invoices.view_draft');
        }

        // Shippers can see Cleared/Completed invoices if they have invoices.view
        return $user->can('invoices.view_cleared') || $user->can('invoices.view');
    }

    public function canDownloadInvoice(Shipment $shipment, User $user): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        // Staff and Admins can download if invoice is generated
        if ($user->hasRole('super_admin') || call_user_func([$user, 'staff'])->exists()) {
            return $shipment->invoice !== null;
        }

        // Shippers: Only COMPLETED invoices
        $status = $shipment->invoice?->status ?? $shipment->invoice_status;

        return $status === InvoiceStatus::Completed;
    }

    public function canClearInvoice(Shipment $shipment, User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $status = $shipment->invoice?->status ?? $shipment->invoice_status;

        return $status === InvoiceStatus::Draft && $user->can('invoices.clear');
    }

    public function canCompleteInvoice(Shipment $shipment, User $user): bool
    {
        // System-wide guard: shipment must be LOADED or TELEX_REQUESTED even for super_admin
        if (! in_array($shipment->shipment_status, [ShipmentStatus::Loaded, ShipmentStatus::TelexRequested], true)) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $status = $shipment->invoice?->status ?? $shipment->invoice_status;

        return $status === InvoiceStatus::Cleared && $user->can('invoices.complete');
    }

    public function canEditInvoice(Shipment $shipment, User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $status = $shipment->invoice?->status ?? $shipment->invoice_status;

        if ($status === InvoiceStatus::Draft) {
            return $user->can('invoices.clear');
        }

        if ($status === InvoiceStatus::Cleared) {
            return $user->can('invoices.complete');
        }

        // COMPLETED is locked for non-admins
        return false;
    }

    public function hasLogisticsInfo(Shipment $shipment): bool
    {
        if ($shipment->booked_without_title) {
            return true;
        }
        $required = [
            'vessel_name',
            'voyage_no',
            'itn_number',
        ];

        if ($shipment->isContainer()) {
            $required[] = 'container_no';
            $required[] = 'seal_no';
            $required[] = 'container_type';
            $required[] = 'booking_number';
        }
        foreach ($required as $field) {
            if (empty($shipment->{$field})) {
                return false;
            }
        }

        return true;
    }

    public function canDownloadDockReceipt(Shipment $shipment, User $user): bool
    {
        if ($shipment->shipment_status === ShipmentStatus::Cancelled) {
            return false;
        }

        if ($user->hasRole('super_admin') || call_user_func([$user, 'staff'])->exists()) {
            return $this->hasLogisticsInfo($shipment);
        }

        // For Shippers: Shipment must be Inland or beyond to download
        return in_array($shipment->shipment_status, [
            ShipmentStatus::Inland,
            ShipmentStatus::Delivered,
            ShipmentStatus::Loaded,
            ShipmentStatus::TelexRequested,
            ShipmentStatus::Completed,
        ], true) && $this->hasLogisticsInfo($shipment);
    }

    public function canTransitionToInland(Shipment $shipment): bool
    {
        return $shipment->shipment_status === ShipmentStatus::Booking
            && $this->hasLogisticsInfo($shipment);
    }
}
