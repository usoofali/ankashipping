<?php

declare(strict_types=1);

namespace App\Livewire\FailedJobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Failed Jobs')] class extends Component {
    use WithPagination, WireUiActions;

    public bool $showRetryModal = false;
    public bool $showRetryAllModal = false;
    public bool $showForgetModal = false;
    public bool $showFlushModal = false;
    public ?string $selectedJobId = null;

    public function mount(): void
    {
        // Only staff/admin can manage failed jobs
        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'admin', 'staff']) || auth()->user()->staff()->exists(), 403);
    }

    public function getFailedJobsProperty()
    {
        // Get paginated jobs directly from the DB
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(15);
    }

    public function confirmRetry(string $id): void
    {
        $this->selectedJobId = $id;
        $this->showRetryModal = true;
    }

    public function retry(): void
    {
        if (!$this->selectedJobId) return;

        try {
            Artisan::call('queue:retry', ['id' => $this->selectedJobId]);
            $this->notification()->success(__('Job pushed back onto the queue.'));
        } catch (\Throwable $e) {
            $this->notification()->error(__('Failed to retry job: :message', ['message' => $e->getMessage()]));
        }

        $this->showRetryModal = false;
        $this->selectedJobId = null;
    }

    public function confirmRetryAll(): void
    {
        $this->showRetryAllModal = true;
    }

    public function retryAll(): void
    {
        $failedJobs = DB::table('failed_jobs')->pluck('uuid');
        
        $successCount = 0;
        $failedCount = 0;

        foreach ($failedJobs as $uuid) {
            try {
                Artisan::call('queue:retry', ['id' => $uuid]);
                $successCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        if ($failedCount > 0) {
            $this->notification()->warning(__(':success retried, :failed skipped (e.g. models deleted).', [
                'success' => $successCount,
                'failed' => $failedCount,
            ]));
        } else {
            $this->notification()->success(__('All failed jobs pushed back onto the queue.'));
        }

        $this->showRetryAllModal = false;
    }

    public function confirmForget(string $id): void
    {
        $this->selectedJobId = $id;
        $this->showForgetModal = true;
    }

    public function forget(): void
    {
        if (!$this->selectedJobId) return;

        Artisan::call('queue:forget', ['id' => $this->selectedJobId]);
        $this->notification()->success(__('Failed job removed.'));
        
        $this->showForgetModal = false;
        $this->selectedJobId = null;
    }

    public function confirmFlush(): void
    {
        $this->showFlushModal = true;
    }

    public function flush(): void
    {
        Artisan::call('queue:flush');
        $this->notification()->success(__('Failed jobs list cleared.'));
        $this->showFlushModal = false;
    }
}; ?>

<div>
    <x-crud.page-shell>
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <x-crud.page-header :heading="__('Failed Queue Jobs')" :subheading="__('Monitor and retry background tasks that failed to execute properly.')" icon="exclamation-triangle" class="mb-0!" />

        <div class="flex items-center gap-2">
            @if ($this->failedJobs->total() > 0)
                <flux:button variant="primary" icon="arrow-path" wire:click="confirmRetryAll">{{ __('Retry All') }}
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="confirmFlush">{{ __('Flush All') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($this->failedJobs->isEmpty())
        <x-crud.empty-state icon="check-circle" :title="__('All clear')" :description="__('There are currently no failed background jobs.')" />
    @else
        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->failedJobs">
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Job Context') }}</flux:table.column>
                    <flux:table.column>{{ __('Exception Details') }}</flux:table.column>
                    <flux:table.column>{{ __('Failed At') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->failedJobs as $job)
                        <flux:table.row :key="$job->id">
                            <flux:table.cell class="font-mono text-xs text-zinc-500">
                                {{ substr((string) $job->uuid, 0, 8) }}...
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $payload = json_decode((string) $job->payload, true);
                                    $jobName = $payload['displayName'] ?? $payload['job'] ?? 'Unknown Job';
                                    $connection = $job->connection;
                                    $queue = $job->queue;
                                @endphp
                                <div class="flex flex-col">
                                    <span class="font-medium whitespace-normal break-words"
                                        title="{{ $jobName }}">{{ class_basename($jobName) }}</span>
                                    <span class="text-xs text-zinc-500">
                                        <span class="font-mono">{{ $connection }}</span> / <span
                                            class="font-mono">{{ $queue }}</span>
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="max-w-md">
                                    <div class="whitespace-normal break-words text-xs font-mono bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400 p-2 rounded border border-red-100 dark:border-red-500/20"
                                        title="{{ $job->exception }}">
                                        {{ explode("\n", (string) $job->exception)[0] }}
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($job->failed_at)->format('M j, Y g:i A') }}
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        <flux:menu.item icon="arrow-path" wire:click="confirmRetry('{{ $job->uuid }}')">
                                            {{ __('Retry Job') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="confirmForget('{{ $job->uuid }}')">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    @endif
    </x-crud.page-shell>

    {{-- Modals --}}
    <flux:modal wire:model="showRetryModal" class="max-w-md">
        <form wire:submit="retry" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Retry Job?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will push the job back onto the queue to be attempted again.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Yes, retry') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showRetryAllModal" class="max-w-md">
        <form wire:submit="retryAll" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Retry All Jobs?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will push all failed jobs back onto the queue. Jobs with deleted models will be skipped.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Yes, retry all') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showForgetModal" class="max-w-md">
        <form wire:submit="forget" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Failed Job?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will permanently remove the failed job record. It cannot be recovered.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Yes, delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showFlushModal" class="max-w-md">
        <form wire:submit="flush" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Flush All Failed Jobs?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will completely clear the failed jobs table. Are you sure?') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Yes, flush all') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>