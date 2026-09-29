<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CarrierEmailParserService;
use App\Services\CarrierReleaseFulfillmentService;
use App\Services\ImapMailboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ProcessCarrierReleaseEmailsCommand extends Command
{
    protected $signature = 'carrier:process-releases
                            {--file= : Path to a local .eml file to process directly}
                            {--dry-run : Parse and test match without altering database or marking emails as read}
                            {--limit=50 : Maximum number of emails to check per run}
                            {--days=7 : Number of days back to scan for carrier releases}
                            {--folder= : Specific mailbox folder to scan (default: INBOX and Notification)}
                            {--unseen-only : Only check strictly unseen messages}';

    protected $description = 'Fetch and process carrier release emails (Sallaum Telex Releases, Grimaldi/ACL Sea Waybills) from accounts@ankshipping.com';

    public function handle(
        CarrierEmailParserService $parserService,
        CarrierReleaseFulfillmentService $fulfillmentService,
        ImapMailboxService $mailboxService
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $filePath = $this->option('file');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No database records will be modified and no emails will be marked as read.');
        }

        // Mode 1: Process single local .eml file
        if ($filePath) {
            return $this->processLocalFile((string) $filePath, $parserService, $fulfillmentService, $dryRun);
        }

        // Mode 2: Connect to Zoho IMAP mailbox
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $unseenOnly = (bool) $this->option('unseen-only');
        $folderOption = $this->option('folder');

        $folders = $folderOption ? [(string) $folderOption] : $mailboxService->getCandidateFolders();

        $this->info(sprintf(
            'Connecting to accounts@ankshipping.com mailbox via IMAP (folders: %s, limit: %d, scan days: %d)...',
            implode(', ', $folders),
            $limit,
            $days
        ));

        if (! $mailboxService->connect()) {
            $this->error('Failed to connect or authenticate to IMAP server. Check credentials in .env or config.');
            Log::error('ProcessCarrierReleaseEmailsCommand: Failed to connect or authenticate to IMAP server. Check accounts mailbox credentials.');

            return self::FAILURE;
        }

        $results = [];
        $fulfilledCount = 0;
        $skippedCount = 0;

        try {
            foreach ($folders as $folder) {
                $this->line("Scanning folder: <info>{$folder}</info>...");
                if (! $mailboxService->selectFolder($folder)) {
                    $this->warn("Could not select folder '{$folder}', skipping.");
                    Log::warning("ProcessCarrierReleaseEmailsCommand: Could not select IMAP folder '{$folder}', skipping.");

                    continue;
                }

                try {
                    $uids = $unseenOnly
                        ? $mailboxService->getUnseenUids($limit)
                        : $mailboxService->getCandidateReleaseUids($limit, $days);
                } catch (\Throwable $e) {
                    $this->error("Error retrieving UIDs in folder '{$folder}': {$e->getMessage()}");
                    Log::error("ProcessCarrierReleaseEmailsCommand: Error retrieving UIDs in folder '{$folder}': {$e->getMessage()}", [
                        'exception' => $e,
                    ]);

                    continue;
                }

                if (empty($uids)) {
                    $this->line("  No matching release emails found in {$folder}.");

                    continue;
                }

                $this->line(sprintf('  Found %d message(s) to check in %s. Scanning for carrier releases...', count($uids), $folder));
                Log::info(sprintf('ProcessCarrierReleaseEmailsCommand: Found %d message(s) to check in %s.', count($uids), $folder));

                foreach ($uids as $uid) {
                    try {
                        $rawEmail = $mailboxService->fetchMessageByUid($uid);
                    } catch (\Throwable $e) {
                        Log::error("ProcessCarrierReleaseEmailsCommand: Exception fetching message UID {$uid} in {$folder}: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);

                        continue;
                    }

                    if (! $rawEmail) {
                        Log::warning("ProcessCarrierReleaseEmailsCommand: Empty email body returned for UID {$uid} in {$folder}.");

                        continue;
                    }

                    try {
                        $releaseData = $parserService->parseRawEmail($rawEmail);
                    } catch (\Throwable $e) {
                        Log::error("ProcessCarrierReleaseEmailsCommand: Exception parsing raw email UID {$uid} in {$folder}: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);

                        continue;
                    }

                    if (! $releaseData) {
                        // Not a carrier release email (e.g. general notification, billing, personal inquiry)
                        // Left unread per design
                        continue;
                    }

                    try {
                        $outcome = $fulfillmentService->processRelease($releaseData, $dryRun);
                    } catch (\Throwable $e) {
                        Log::error("ProcessCarrierReleaseEmailsCommand: Exception fulfilling release for UID {$uid} (VIN: {$releaseData->vin}): {$e->getMessage()}", [
                            'exception' => $e,
                        ]);

                        continue;
                    }

                    if ($outcome['status'] === 'error') {
                        Log::error("ProcessCarrierReleaseEmailsCommand: Error fulfilling release for UID {$uid} (VIN: {$releaseData->vin}): {$outcome['message']}");
                    }

                    if ($outcome['mark_as_seen'] && ! $dryRun) {
                        try {
                            $mailboxService->markAsSeen($uid);
                        } catch (\Throwable $e) {
                            Log::warning("ProcessCarrierReleaseEmailsCommand: Failed to mark UID {$uid} as seen: {$e->getMessage()}");
                        }
                    }

                    if ($outcome['status'] === 'fulfilled') {
                        $fulfilledCount++;
                    } else {
                        $skippedCount++;
                    }

                    $results[] = [
                        'Folder' => $folder,
                        'UID' => $uid,
                        'Carrier' => $releaseData->carrier,
                        'Type' => $releaseData->releaseType,
                        'VIN' => $releaseData->vin,
                        'BL No' => $releaseData->blNumber ?? '—',
                        'Shipment' => $outcome['shipment']?->reference_no ?? '—',
                        'Status' => $outcome['status'],
                        'Message' => $outcome['message'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->error("ProcessCarrierReleaseEmailsCommand failed unexpectedly: {$e->getMessage()}");
            Log::error("ProcessCarrierReleaseEmailsCommand: Unexpected failure during release scan: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        } finally {
            $mailboxService->disconnect();
        }

        if (! empty($results)) {
            $this->table(
                ['Folder', 'UID', 'Carrier', 'Type', 'VIN', 'BL No', 'Shipment', 'Outcome', 'Details'],
                $results
            );
        } else {
            $this->info('No carrier release emails detected.');
        }

        $this->info(sprintf(
            'Completed processing: %d fulfilled, %d skipped.',
            $fulfilledCount,
            $skippedCount
        ));

        Log::info(sprintf('ProcessCarrierReleaseEmailsCommand: Run completed. %d fulfilled, %d skipped.', $fulfilledCount, $skippedCount));

        return self::SUCCESS;
    }

    protected function processLocalFile(
        string $filePath,
        CarrierEmailParserService $parserService,
        CarrierReleaseFulfillmentService $fulfillmentService,
        bool $dryRun
    ): int {
        $realPath = realpath($filePath) ?: $filePath;
        if (! file_exists($realPath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Parsing local file: {$realPath}");
        $content = file_get_contents($realPath);
        if ($content === false) {
            $this->error("Failed to read file: {$realPath}");

            return self::FAILURE;
        }

        $releaseData = $parserService->parseRawEmail($content);
        if (! $releaseData) {
            $this->error('The provided file was not recognized as a carrier release email (Telex Release or Sea Waybill).');

            return self::FAILURE;
        }

        $outcome = $fulfillmentService->processRelease($releaseData, $dryRun);

        $this->info("Carrier: {$releaseData->carrier} | VIN: {$releaseData->vin} | Outcome: {$outcome['status']}");

        $this->table(
            ['Carrier', 'Type', 'VIN', 'BL No', 'PIN', 'Shipment', 'Outcome', 'Details'],
            [[
                $releaseData->carrier,
                $releaseData->releaseType,
                $releaseData->vin,
                $releaseData->blNumber ?? '—',
                $releaseData->pinNumber ?? '—',
                $outcome['shipment']?->reference_no ?? '—',
                $outcome['status'],
                $outcome['message'],
            ]]
        );

        return self::SUCCESS;
    }
}
