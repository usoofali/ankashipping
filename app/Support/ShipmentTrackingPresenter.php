<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ShipmentDocumentType;
use App\Models\Shipment;
use App\Models\ShipmentDocumentFile;
use App\Models\ShipmentTracking;

final class ShipmentTrackingPresenter
{
    /**
     * @return list<array{text: string, variant?: string, href?: string}>
     */
    public function badges(ShipmentTracking $tracking, Shipment $shipment): array
    {
        $m = is_array($tracking->metadata) ? $tracking->metadata : [];
        $source = (string) ($m['source'] ?? '');
        $badges = [];

        if (($m['driver_label'] ?? null) !== null && $m['driver_label'] !== '') {
            $badges[] = ['text' => __('Driver: :d', ['d' => (string) $m['driver_label']]), 'variant' => 'subtle'];
        } elseif (isset($m['driver_id'])) {
            $badges[] = ['text' => __('Driver ID: :id', ['id' => (string) $m['driver_id']]), 'variant' => 'outline'];
        }

        if ($source === 'shipment_show_attach_document') {
            $badges = array_merge($badges, $this->documentAttachBadges($m, $shipment));
        }

        if ($source === 'shipment_show_to_workshop') {
            if (filled($m['workshop_name'] ?? null)) {
                $badges[] = ['text' => __('Workshop: :w', ['w' => (string) $m['workshop_name']]), 'variant' => 'subtle'];
            }
            if (filled($m['from_shipment_status_label'] ?? null)) {
                $badges[] = ['text' => __('From status: :s', ['s' => (string) $m['from_shipment_status_label']]), 'variant' => 'outline'];
            }
        }

        if ($source === 'shipment_show_from_workshop') {
            if (filled($m['to_shipment_status_label'] ?? null)) {
                $badges[] = ['text' => __('Restored status: :s', ['s' => (string) $m['to_shipment_status_label']]), 'variant' => 'subtle'];
            }
        }

        if (filled($m['source'] ?? null)) {
            $badges[] = ['text' => __('Source: :s', ['s' => (string) $m['source']]), 'variant' => 'outline'];
        }

        if (isset($m['created_by'])) {
            $badges[] = ['text' => __('Recorded by user #:id', ['id' => (string) $m['created_by']]), 'variant' => 'outline'];
        }

        return $badges;
    }

    /**
     * @param  array<string, mixed>  $m
     * @return list<array{text: string, variant?: string, href?: string}>
     */
    private function documentAttachBadges(array $m, Shipment $shipment): array
    {
        $out = [];
        if (filled($m['document_type_label'] ?? null)) {
            $out[] = ['text' => __('Type: :t', ['t' => (string) $m['document_type_label']]), 'variant' => 'subtle'];
        } elseif (filled($m['document_type'] ?? null)) {
            $type = ShipmentDocumentType::tryFrom((string) $m['document_type']);
            $out[] = ['text' => __('Type: :t', ['t' => $type?->label() ?? (string) $m['document_type']]), 'variant' => 'subtle'];
        }

        $ids = $m['shipment_document_file_ids'] ?? [];
        $numericIds = is_array($ids) ? array_values(array_filter($ids, 'is_numeric')) : [];
        if ($numericIds !== []) {
            $out[] = ['text' => __('Files: :n', ['n' => (string) count($numericIds)]), 'variant' => 'outline'];
        }

        if (filled($m['vehicle_is'] ?? null)) {
            $out[] = ['text' => __('Vehicle condition: :v', ['v' => (string) $m['vehicle_is']]), 'variant' => 'subtle'];
        }

        $downloadIndex = 0;
        foreach ($numericIds as $fileId) {
            $file = ShipmentDocumentFile::query()->find((int) $fileId);
            if ($file === null) {
                continue;
            }
            $downloadIndex++;
            $out[] = [
                'text' => __('Download file :num', ['num' => (string) $downloadIndex]),
                'variant' => 'subtle',
                'href' => ShipmentDocumentSignedDownloadUrl::for($shipment, $file),
            ];
        }

        return $out;
    }
}
