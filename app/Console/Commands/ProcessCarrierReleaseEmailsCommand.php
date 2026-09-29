<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CarrierEmailParserService;
use App\Services\CarrierReleaseFulfillmentService;
use App\Services\ImapMailboxService;
use Illuminate\Console\Command;

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
            $this->error('Failed to connect or authenticate to IMAP server. Check credentials in .env.');

            return self::FAILURE;
        }

        $results = [];
        $fulfilledCount = 0;
        $skippedCount = 0;

        foreach ($folders as $folder) {
            $this->line("Scanning folder: <info>{$folder}</info>...");
            if (! $mailboxService->selectFolder($folder)) {
                $this->warn("Could not select folder '{$folder}', skipping.");

                continue;
            }

            $uids = $unseenOnly
                ? $mailboxService->getUnseenUids($limit)
                : $mailboxService->getCandidateReleaseUids($limit, $days);

            if (empty($uids)) {
                $this->line("  No matching release emails found in {$folder}.");

                continue;
            }

            $this->line(sprintf('  Found %d message(s) to check in %s. Scanning for carrier releases...', count($uids), $folder));

            foreach ($uids as $uid) {
                $rawEmail = $mailboxService->fetchMessageByUid($uid);
                if (! $rawEmail) {
                    continue;
                }

                $releaseData = $parserService->parseRawEmail($rawEmail);
                if (! $releaseData) {
                    // Not a carrier release email (e.g. general notification, billing, personal inquiry)
                    // Left unread per design
                    continue;
                }

                $outcome = $fulfillmentService->processRelease($releaseData, $dryRun);

                if ($outcome['mark_as_seen'] && ! $dryRun) {
                    $mailboxService->markAsSeen($uid);
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

        $mailboxService->disconnect();

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
