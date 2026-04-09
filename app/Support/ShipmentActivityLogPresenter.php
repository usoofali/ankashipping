<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;

final class ShipmentActivityLogPresenter
{
    public function title(ActivityLog $log): string
    {
        $action = (string) $log->action;

        return match ($action) {
            'invoice_item_added' => __('Invoice line added'),
            'invoice_item_updated' => __('Invoice line updated'),
            'invoice_item_removed' => __('Invoice line removed'),
            'invoice_status_changed' => __('Invoice status changed'),
            'driver_assigned' => __('Driver assigned'),
            'document_attached' => __('Document attached'),
            'document_removed' => __('Document removed'),
            'document_file_removed' => __('Document file removed'),
            'shipment_sent_to_workshop' => __('Shipment sent to workshop'),
            'shipment_returned_from_workshop' => __('Shipment returned from workshop'),
            'created' => __('Shipment created'),
            'updated' => __('Shipment updated'),
            default => ucwords(str_replace('_', ' ', $action)),
        };
    }

    /**
     * @return list<array{text: string, variant?: string}>
     */
    public function badges(ActivityLog $log): array
    {
        $p = is_array($log->properties) ? $log->properties : [];
        $action = (string) $log->action;
        $badges = [];

        if (filled($p['message'] ?? null)) {
            $badges[] = ['text' => (string) $p['message'], 'variant' => 'subtle'];
        }

        if (filled($p['source'] ?? null)) {
            $badges[] = ['text' => __('Source: :s', ['s' => (string) $p['source']]), 'variant' => 'outline'];
        }

        if (array_key_exists('prealert_id', $p) && $p['prealert_id'] !== null && $p['prealert_id'] !== '') {
            $badges[] = ['text' => __('Prealert #:id', ['id' => (string) $p['prealert_id']]), 'variant' => 'subtle'];
        }

        $badges = array_merge($badges, match ($action) {
            'invoice_item_updated' => $this->invoiceItemUpdatedBadges($p),
            'invoice_item_added', 'invoice_item_removed' => $this->invoiceItemSingleBadges($p),
            'invoice_status_changed' => $this->invoiceStatusBadges($p),
            'driver_assigned' => $this->driverBadges($p),
            'document_attached' => $this->documentAttachedBadges($p),
            'document_removed' => $this->documentRemovedBadges($p),
            'document_file_removed' => $this->documentFileRemovedBadges($p),
            'shipment_sent_to_workshop' => $this->workshopSentBadges($p),
            'shipment_returned_from_workshop' => $this->workshopReturnedBadges($p),
            'created', 'updated' => [],
            default => $this->genericScalarBadges($p),
        });

        return $badges;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function invoiceItemUpdatedBadges(array $p): array
    {
        $out = [];
        if (filled($p['from_description'] ?? null) || filled($p['to_description'] ?? null)) {
            $out[] = [
                'text' => __('Item: :from → :to', [
                    'from' => (string) ($p['from_description'] ?? '—'),
                    'to' => (string) ($p['to_description'] ?? '—'),
                ]),
                'variant' => 'subtle',
            ];
        }
        if (array_key_exists('from_amount', $p) || array_key_exists('to_amount', $p)) {
            $out[] = [
                'text' => __('Amount: :from → :to', [
                    'from' => '$'.number_format((float) ($p['from_amount'] ?? 0), 2),
                    'to' => '$'.number_format((float) ($p['to_amount'] ?? 0), 2),
                ]),
                'variant' => 'subtle',
            ];
        }
        if (array_key_exists('gross_amount', $p) && (float) $p['gross_amount'] != 0.0) {
            $out[] = ['text' => __('Gross: :a', ['a' => '$'.number_format((float) $p['gross_amount'], 2)]), 'variant' => 'outline'];
        }
        if (array_key_exists('discount_amount', $p) && (float) $p['discount_amount'] != 0.0) {
            $out[] = ['text' => __('Discount: :a', ['a' => '$'.number_format((float) $p['discount_amount'], 2)]), 'variant' => 'outline'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function invoiceItemSingleBadges(array $p): array
    {
        $out = [];
        if (filled($p['description'] ?? null)) {
            $out[] = ['text' => __('Item: :i', ['i' => (string) $p['description']]), 'variant' => 'subtle'];
        }
        if (array_key_exists('amount', $p)) {
            $out[] = ['text' => __('Amount: :a', ['a' => '$'.number_format((float) $p['amount'], 2)]), 'variant' => 'subtle'];
        }
        if (array_key_exists('gross_amount', $p) && (float) $p['gross_amount'] != 0.0) {
            $out[] = ['text' => __('Gross: :a', ['a' => '$'.number_format((float) $p['gross_amount'], 2)]), 'variant' => 'outline'];
        }
        if (array_key_exists('discount_amount', $p) && (float) $p['discount_amount'] != 0.0) {
            $out[] = ['text' => __('Discount: :a', ['a' => '$'.number_format((float) $p['discount_amount'], 2)]), 'variant' => 'outline'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function invoiceStatusBadges(array $p): array
    {
        $from = (string) ($p['from_label'] ?? $p['from'] ?? '—');
        $to = (string) ($p['to_label'] ?? $p['to'] ?? '—');

        return [['text' => __('Status: :from → :to', ['from' => $from, 'to' => $to]), 'variant' => 'subtle']];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function driverBadges(array $p): array
    {
        $out = [];
        if (filled($p['driver_label'] ?? null)) {
            $out[] = ['text' => __('Driver: :d', ['d' => (string) $p['driver_label']]), 'variant' => 'subtle'];
        } elseif (isset($p['driver_id'])) {
            $out[] = ['text' => __('Driver ID: :id', ['id' => (string) $p['driver_id']]), 'variant' => 'outline'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function documentAttachedBadges(array $p): array
    {
        $out = [];
        if (filled($p['document_type_label'] ?? null)) {
            $out[] = ['text' => __('Type: :t', ['t' => (string) $p['document_type_label']]), 'variant' => 'subtle'];
        } elseif (filled($p['document_type'] ?? null)) {
            $type = ShipmentDocumentType::tryFrom((string) $p['document_type']);
            $out[] = ['text' => __('Type: :t', ['t' => $type?->label() ?? (string) $p['document_type']]), 'variant' => 'subtle'];
        }
        if (isset($p['file_count'])) {
            $out[] = ['text' => __('Files: :n', ['n' => (string) $p['file_count']]), 'variant' => 'outline'];
        }
        if (filled($p['vehicle_is'] ?? null)) {
            $out[] = ['text' => __('Vehicle: :v', ['v' => (string) $p['vehicle_is']]), 'variant' => 'subtle'];
        }
        if (filled($p['from_shipment_status'] ?? null) && filled($p['to_shipment_status'] ?? null)) {
            $from = ShipmentStatus::tryFrom((string) $p['from_shipment_status']);
            $to = ShipmentStatus::tryFrom((string) $p['to_shipment_status']);
            $out[] = [
                'text' => __('Shipment status: :from → :to', [
                    'from' => $from?->name ?? (string) $p['from_shipment_status'],
                    'to' => $to?->name ?? (string) $p['to_shipment_status'],
                ]),
                'variant' => 'subtle',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function documentRemovedBadges(array $p): array
    {
        $out = [];
        if (filled($p['document_type_label'] ?? null)) {
            $out[] = ['text' => __('Type: :t', ['t' => (string) $p['document_type_label']]), 'variant' => 'subtle'];
        } elseif (filled($p['document_type'] ?? null)) {
            $type = ShipmentDocumentType::tryFrom((string) $p['document_type']);
            $out[] = ['text' => __('Type: :t', ['t' => $type?->label() ?? (string) $p['document_type']]), 'variant' => 'subtle'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function documentFileRemovedBadges(array $p): array
    {
        return [['text' => __('Attached file removed'), 'variant' => 'subtle']];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function workshopSentBadges(array $p): array
    {
        $out = [];
        if (filled($p['workshop_name'] ?? null)) {
            $out[] = ['text' => __('Workshop: :w', ['w' => (string) $p['workshop_name']]), 'variant' => 'subtle'];
        }
        if (filled($p['from_shipment_status_label'] ?? null)) {
            $out[] = ['text' => __('Previous status: :s', ['s' => (string) $p['from_shipment_status_label']]), 'variant' => 'outline'];
        } elseif (filled($p['from_shipment_status'] ?? null)) {
            $s = ShipmentStatus::tryFrom((string) $p['from_shipment_status']);
            $out[] = ['text' => __('Previous status: :s', ['s' => $s?->name ?? (string) $p['from_shipment_status']]), 'variant' => 'outline'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function workshopReturnedBadges(array $p): array
    {
        if (filled($p['to_shipment_status_label'] ?? null)) {
            return [['text' => __('Restored status: :s', ['s' => (string) $p['to_shipment_status_label']]), 'variant' => 'subtle']];
        }
        if (filled($p['to_shipment_status'] ?? null)) {
            $s = ShipmentStatus::tryFrom((string) $p['to_shipment_status']);

            return [['text' => __('Restored status: :s', ['s' => $s?->name ?? (string) $p['to_shipment_status']]), 'variant' => 'subtle']];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{text: string, variant?: string}>
     */
    private function genericScalarBadges(array $p): array
    {
        $skip = ['message', 'source', 'prealert_id', 'reference_no', 'uploaded_by', 'user_id', 'invoice_id', 'invoice_item_id', 'shipment_document_id'];
        $out = [];
        foreach ($p as $key => $value) {
            if (in_array($key, $skip, true) || $value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $out[] = [
                'text' => $key.': '.(is_scalar($value) ? (string) $value : json_encode($value)),
                'variant' => 'outline',
            ];
        }

        return $out;
    }
}
