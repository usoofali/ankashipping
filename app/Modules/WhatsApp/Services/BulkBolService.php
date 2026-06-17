<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;
use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Notifications\ShipmentDocumentAttachedNotification;
use App\ShippingWorkflow\ShippingWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BulkBolService
{
    public function __construct(
        protected ShippingWorkflow $workflow
    ) {}

    public function process(string $pdfPath, WhatsAppConversation $conversation, User $user): array
    {
        $results = [
            'total_pages' => 0,
            'matched' => [],
            'unmatched' => [],
            'wrong_status' => [],
            'no_vin' => [],
            'failed' => [],
        ];

        // Ensure pdftotext is available
        if (! trim(shell_exec('which pdftotext 2>/dev/null'))) {
            throw new \Exception('pdftotext is not installed on the system.');
        }

        if (! trim(shell_exec('which gs 2>/dev/null'))) {
            throw new \Exception('Ghostscript (gs) is not installed on the system.');
        }

        // Get total pages by counting form-feed characters (\f) which pdftotext outputs between pages
        $allText = shell_exec('pdftotext -layout '.escapeshellarg($pdfPath).' - 2>/dev/null');
        if ($allText === null) {
            throw new \Exception('Failed to extract text from PDF.');
        }

        $pagesCount = substr_count($allText, "\f");
        if ($pagesCount === 0 && strlen(trim($allText)) > 0) {
            $pagesCount = 1;
        }

        $results['total_pages'] = $pagesCount;

        $tempDir = storage_path('app/temp/bulk-bol/'.uniqid());
        File::makeDirectory($tempDir, 0755, true);

        try {
            // Split all pages at once using Ghostscript
            $gsCmd = sprintf(
                'gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -o %s/page_%%d.pdf %s 2>/dev/null',
                escapeshellarg($tempDir),
                escapeshellarg($pdfPath)
            );
            shell_exec($gsCmd);

            for ($i = 1; $i <= $pagesCount; $i++) {
                $pagePdfPath = "{$tempDir}/page_{$i}.pdf";

                // Extract text for this specific page
                $pageText = shell_exec("pdftotext -layout -f {$i} -l {$i} ".escapeshellarg($pdfPath).' - 2>/dev/null');

                $vin = $this->extractVin((string) $pageText);

                if (! $vin) {
                    $results['no_vin'][] = $i;

                    continue;
                }

                $pageResult = $this->processPage($i, $vin, $pagePdfPath, $user);

                if ($pageResult['status'] === 'matched') {
                    $results['matched'][] = $pageResult;
                } elseif ($pageResult['status'] === 'unmatched') {
                    $results['unmatched'][] = $pageResult;
                } elseif ($pageResult['status'] === 'wrong_status') {
                    $results['wrong_status'][] = $pageResult;
                } elseif ($pageResult['status'] === 'failed') {
                    $results['failed'][] = $pageResult;
                }
            }
        } finally {
            File::deleteDirectory($tempDir);
        }

        return $results;
    }

    protected function extractVin(string $pageText): ?string
    {
        $lines = explode("\n", $pageText);
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'CHASSIS NUMBER')) {
                // Look ahead for the VIN, skipping the HS Code line
                for ($j = $i + 1; $j < count($lines); $j++) {
                    $trimmed = trim($lines[$j]);
                    if ($trimmed !== '' && ! str_starts_with($trimmed, 'HS Code')) {
                        // VIN should be the first token on the line
                        $token = strtoupper(explode(' ', $trimmed)[0]);
                        // Basic VIN format validation (17 chars, no I, O, Q)
                        if (preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $token)) {
                            return $token;
                        }
                        break; // Found a non-empty line but it wasn't a valid VIN
                    }
                }
            }
        }

        return null;
    }

    protected function processPage(int $pageNum, string $vin, string $pagePdfPath, User $user): array
    {
        $vehicle = Vehicle::where('vin', $vin)->with('shipment.shipper')->first();

        if (! $vehicle || ! $vehicle->shipment) {
            return ['status' => 'unmatched', 'vin' => $vin];
        }

        $shipment = $vehicle->shipment;

        if (! $this->workflow->canAttachBL($shipment)) {
            return [
                'status' => 'wrong_status',
                'vin' => $vin,
                'ref' => $shipment->reference_no,
                'status_name' => $shipment->shipment_status->name,
            ];
        }

        try {
            DB::transaction(function () use ($shipment, $vin, $pagePdfPath, $user) {
                // Ensure directory exists
                $targetDir = "shipment-documents/{$shipment->id}";
                if (! File::exists(storage_path("app/public/{$targetDir}"))) {
                    File::makeDirectory(storage_path("app/public/{$targetDir}"), 0755, true);
                }

                // Copy file to public storage
                $fileName = "BOL_{$vin}.pdf";
                $targetPath = "{$targetDir}/{$fileName}";
                File::copy($pagePdfPath, storage_path("app/public/{$targetPath}"));

                // Create document record
                $document = new ShipmentDocument([
                    'document_type' => ShipmentDocumentType::BillOfLading,
                    'notes' => 'Attached via WhatsApp bulk BOL batch',
                    'is_verified' => true,
                    'verified_at' => now(),
                    'verified_by' => $user->id,
                ]);

                $shipment->documents()->save($document);

                // Create file record
                $documentFile = new ShipmentDocumentFile([
                    'path' => $targetPath,
                    'disk' => 'public',
                    'original_name' => $fileName,
                    'mime_type' => 'application/pdf',
                    'size' => File::size(storage_path("app/public/{$targetPath}")),
                ]);
                $document->files()->save($documentFile);

                $fromStatus = $shipment->shipment_status;
                $toStatus = ShipmentStatus::Loaded;

                // Update shipment
                $shipment->update([
                    'shipment_status' => $toStatus,
                    'booked_without_title' => false,
                ]);

                // Record tracking
                $shipment->trackings()->create([
                    'status' => $toStatus,
                    'location' => 'System',
                    'notes' => 'Bill of Lading attached via bulk WhatsApp upload',
                    'recorded_at' => now(),
                    'user_id' => $user->id,
                ]);

                // Log activity
                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id,
                    'action' => 'document_attached',
                    'properties' => [
                        'type' => 'bill_of_lading',
                        'source' => 'whatsapp_bulk',
                        'notes' => 'Attached Bill of Lading via bulk WhatsApp upload',
                    ],
                ]);

                // Notify relevant parties
                $recipients = collect([$user]);
                if ($shipment->shipper && $shipment->shipper->user) {
                    $recipients->push($shipment->shipper->user);
                }

                Notification::send(
                    $recipients->unique('id'),
                    new ShipmentDocumentAttachedNotification(
                        $shipment,
                        $document,
                        1,
                        $fromStatus,
                        $toStatus
                    )
                );
            });

            return [
                'status' => 'matched',
                'vin' => $vin,
                'ref' => $shipment->reference_no,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to attach bulk BOL page', [
                'shipment_id' => $shipment->id,
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'vin' => $vin,
                'ref' => $shipment->reference_no,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function formatSummary(array $results): string
    {
        $matchedCount = count($results['matched']);
        $unmatchedCount = count($results['unmatched']);
        $wrongStatusCount = count($results['wrong_status']);
        $noVinCount = count($results['no_vin']);
        $failedCount = count($results['failed']);

        $summary = "✅ *BOL Batch Processing Complete*\n\n";
        $summary .= "📄 *Pages Processed:* {$results['total_pages']}\n";
        $summary .= "✅ *Matched & Attached:* {$matchedCount}\n";

        if ($unmatchedCount > 0) {
            $summary .= "❌ *Unmatched VINs (no shipment found):* {$unmatchedCount}\n";
        }

        if ($wrongStatusCount > 0) {
            $summary .= "⚠️ *Wrong Status (not DELIVERED):* {$wrongStatusCount}\n";
        }

        if ($noVinCount > 0) {
            $summary .= "🔍 *Pages with No VIN Detected:* {$noVinCount}\n";
        }

        if ($failedCount > 0) {
            $summary .= "🚨 *Failed to Process (System Error):* {$failedCount}\n";
        }

        $summary .= "\n━━━━━━━━━━━━━━━━━━━\n";

        if ($matchedCount > 0) {
            $summary .= "*Matched Shipments:*\n";
            foreach ($results['matched'] as $match) {
                $summary .= "• {$match['ref']} — {$match['vin']}\n";
            }
            $summary .= "\n";
        }

        if ($unmatchedCount > 0) {
            $summary .= "*Unmatched VINs (not in system):*\n";
            foreach ($results['unmatched'] as $unmatched) {
                $summary .= "• {$unmatched['vin']}\n";
            }
            $summary .= "\n";
        }

        if ($wrongStatusCount > 0) {
            $summary .= "*Wrong Status Shipments:*\n";
            foreach ($results['wrong_status'] as $wrong) {
                $summary .= "• {$wrong['ref']} ({$wrong['vin']}) — {$wrong['status_name']}\n";
            }
            $summary .= "\n";
        }

        if ($noVinCount > 0) {
            $summary .= '*No VIN Detected on Pages:* '.implode(', ', $results['no_vin'])."\n";
        }

        if ($failedCount > 0) {
            $summary .= "*Failed Processing:*\n";
            foreach ($results['failed'] as $failed) {
                $summary .= "• {$failed['ref']} ({$failed['vin']})\n";
            }
            $summary .= "\n";
        }

        return trim($summary);
    }
}
