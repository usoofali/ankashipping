<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\BookedWithoutTitleReminderNotification;
use App\Notifications\InvoiceOverdueReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ProcessAutomatedRemindersCommand extends Command
{
    protected $signature = 'reminders:process
                            {--dry-run : Evaluate reminders without modifying records or dispatching notifications}
                            {--type=all : Reminder type to process (titles, invoices, all)}
                            {--limit=100 : Maximum number of reminders to process per type}';

    protected $description = 'Process automated reminders for shipments booked without title (every 3 days) and overdue completed invoices (weekly after 3 weeks)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $type = (string) $this->option('type');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No emails will be sent and no database records will be modified.');
        }

        $titleSent = 0;
        $invoiceSent = 0;
        $rows = [];

        try {
            // 1. Process Title Reminders ("Booked Without Title" - 3-day cycle)
            if (in_array($type, ['all', 'titles'], true)) {
                $titleSent = $this->processTitleReminders($limit, $dryRun, $rows);
            }

            // 2. Process Overdue Invoice Reminders (3 weeks overdue, weekly follow-up)
            if (in_array($type, ['all', 'invoices'], true)) {
                $invoiceSent = $this->processInvoiceReminders($limit, $dryRun, $rows);
            }
        } catch (\Throwable $e) {
            $this->error("ProcessAutomatedRemindersCommand failed: {$e->getMessage()}");
            Log::error("ProcessAutomatedRemindersCommand: Unexpected failure during execution: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }

        if (! empty($rows)) {
            $this->table(
                ['Type', 'Reference', 'Customer / Recipient', 'Subject / Details', 'Aging', 'Action'],
                $rows
            );
        } else {
            $this->info('No pending reminders met the dispatch threshold.');
        }

        $summary = sprintf(
            'ProcessAutomatedRemindersCommand: %d title reminder(s) and %d invoice reminder(s) %s.',
            $titleSent,
            $invoiceSent,
            $dryRun ? 'identified (dry-run)' : 'dispatched'
        );

        $this->info($summary);
        Log::info($summary);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    protected function processTitleReminders(int $limit, bool $dryRun, array &$rows): int
    {
        $sentCount = 0;

        try {
            $shipments = Shipment::query()
                ->where('booked_without_title', true)
                ->where('updated_at', '<=', now()->subDays(3))
                ->with(['shipper.user', 'vehicles', 'originPort'])
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            $this->error("Failed to query booked without title shipments: {$e->getMessage()}");
            Log::error("ProcessAutomatedRemindersCommand: Database error querying title shipments: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return 0;
        }

        foreach ($shipments as $shipment) {
            $recipient = $shipment->shipper?->user;
            $vehicle = $shipment->vehicles->first();
            $vehicleDesc = $vehicle ? "{$vehicle->year} {$vehicle->make} {$vehicle->model} (VIN: {$vehicle->vin})" : '—';
            $aging = $shipment->updated_at ? $shipment->updated_at->diffForHumans() : '—';

            if (! $shipment->shipper) {
                $this->warn("Skipping shipment {$shipment->reference_no}: Shipment has no linked shipper record.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping shipment {$shipment->reference_no} (ID: {$shipment->id}) — Missing linked shipper.");

                continue;
            }

            if (! $recipient) {
                $this->warn("Skipping shipment {$shipment->reference_no}: Shipper does not have an active user account.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping shipment {$shipment->reference_no} — Shipper ID {$shipment->shipper_id} has no linked user account.");

                continue;
            }

            if (empty($recipient->email)) {
                $this->warn("Skipping shipment {$shipment->reference_no}: Shipper user has no email address.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping shipment {$shipment->reference_no} — Shipper user ID {$recipient->id} has no email address.");

                continue;
            }

            $rows[] = [
                'Type' => 'Title Required',
                'Reference' => $shipment->reference_no,
                'Customer / Recipient' => $recipient->email,
                'Subject / Details' => $vehicleDesc,
                'Aging' => $aging,
                'Action' => $dryRun ? '[DRY RUN] Would notify & touch' : 'Notified & Touched',
            ];

            if ($dryRun) {
                $sentCount++;

                continue;
            }

            try {
                $recipient->notify(new BookedWithoutTitleReminderNotification($shipment));

                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $this->getSystemUserId($recipient),
                    'action' => 'title_reminder_sent',
                    'properties' => [
                        'reference_no' => $shipment->reference_no,
                        'recipient' => $recipient->email,
                        'terminal' => $shipment->originPort?->terminal_name ?? $shipment->originPort?->name,
                        'sent_at' => now()->toIso8601String(),
                    ],
                ]);

                // Touch shipment to reset 3-day clock (Option 1)
                $shipment->touch();

                $sentCount++;
                Log::info("ProcessAutomatedRemindersCommand: Dispatched title reminder for shipment {$shipment->reference_no} to {$recipient->email}.");
            } catch (\Throwable $e) {
                $this->error("Failed to send title reminder for {$shipment->reference_no}: {$e->getMessage()}");
                Log::error("ProcessAutomatedRemindersCommand: Error sending title reminder for {$shipment->reference_no} to {$recipient->email}: {$e->getMessage()}", [
                    'shipment_id' => $shipment->id,
                    'reference_no' => $shipment->reference_no,
                    'recipient' => $recipient->email,
                    'exception' => $e,
                ]);
            }
        }

        return $sentCount;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    protected function processInvoiceReminders(int $limit, bool $dryRun, array &$rows): int
    {
        $sentCount = 0;

        try {
            $invoices = Invoice::query()
                ->where('status', InvoiceStatus::Completed)
                ->where('updated_at', '<=', now()->subWeeks(3))
                ->whereHas('shipment', function ($q): void {
                    $q->where('payment_status', '!=', PaymentStatus::Paid);
                })
                ->whereDoesntHave('shipment.activityLogs', function ($q): void {
                    $q->where('action', 'invoice_overdue_reminder_sent')
                        ->where('created_at', '>', now()->subDays(7));
                })
                ->with(['shipment.shipper.user', 'shipment.vehicles'])
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            $this->error("Failed to query overdue invoices: {$e->getMessage()}");
            Log::error("ProcessAutomatedRemindersCommand: Database error querying overdue invoices: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return 0;
        }

        foreach ($invoices as $invoice) {
            $shipment = $invoice->shipment;
            $recipient = $shipment?->shipper?->user;
            $aging = $invoice->updated_at ? $invoice->updated_at->diffForHumans() : '—';
            $details = "Inv #{$invoice->invoice_number} (\${$invoice->total_amount})";

            if (! $shipment) {
                $this->warn("Skipping invoice #{$invoice->invoice_number}: No associated shipment found.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping invoice #{$invoice->invoice_number} (ID: {$invoice->id}) — No associated shipment found.");

                continue;
            }

            if (! $shipment->shipper) {
                $this->warn("Skipping invoice #{$invoice->invoice_number}: Shipment has no linked shipper.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping invoice #{$invoice->invoice_number} for shipment {$shipment->reference_no} — Missing linked shipper.");

                continue;
            }

            if (! $recipient) {
                $this->warn("Skipping invoice #{$invoice->invoice_number}: Shipper has no active user account.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping invoice #{$invoice->invoice_number} for shipment {$shipment->reference_no} — Shipper ID {$shipment->shipper_id} has no linked user account.");

                continue;
            }

            if (empty($recipient->email)) {
                $this->warn("Skipping invoice #{$invoice->invoice_number}: Shipper user has no email address.");
                Log::warning("ProcessAutomatedRemindersCommand: Skipping invoice #{$invoice->invoice_number} for shipment {$shipment->reference_no} — Shipper user ID {$recipient->id} has no email address.");

                continue;
            }

            $rows[] = [
                'Type' => 'Invoice Overdue',
                'Reference' => $shipment->reference_no,
                'Customer / Recipient' => $recipient->email,
                'Subject / Details' => $details,
                'Aging' => $aging,
                'Action' => $dryRun ? '[DRY RUN] Would notify & log' : 'Notified & Logged',
            ];

            if ($dryRun) {
                $sentCount++;

                continue;
            }

            try {
                $recipient->notify(new InvoiceOverdueReminderNotification($shipment, $invoice));

                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $this->getSystemUserId($recipient),
                    'action' => 'invoice_overdue_reminder_sent',
                    'properties' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => $invoice->total_amount,
                        'reference_no' => $shipment->reference_no,
                        'recipient' => $recipient->email,
                        'sent_at' => now()->toIso8601String(),
                    ],
                ]);

                $sentCount++;
                Log::info("ProcessAutomatedRemindersCommand: Dispatched overdue invoice reminder #{$invoice->invoice_number} for {$shipment->reference_no} to {$recipient->email}.");
            } catch (\Throwable $e) {
                $this->error("Failed to send invoice reminder for #{$invoice->invoice_number}: {$e->getMessage()}");
                Log::error("ProcessAutomatedRemindersCommand: Error sending invoice reminder for #{$invoice->invoice_number}: {$e->getMessage()}", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'reference_no' => $shipment->reference_no,
                    'recipient' => $recipient->email,
                    'exception' => $e,
                ]);
            }
        }

        return $sentCount;
    }

    protected function getSystemUserId(?User $fallbackUser = null): int
    {
        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->first();

        return (int) ($admin?->id ?? $fallbackUser?->id ?? 1);
    }
}
