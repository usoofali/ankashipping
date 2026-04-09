<?php

declare(strict_types=1);

namespace App\Livewire\EmailLogs;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Email Logs')] class extends Component {
    use WithPagination, WireUiActions;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $selectedLogId = null;
    public bool $showContentModal = false;

    public bool $selectAll = false;
    public array $selectedLogs = [];


    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectAll = false;
        $this->selectedLogs = [];
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selectAll = false;
        $this->selectedLogs = [];
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedLogs = $this->emailLogs->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedLogs = [];
        }
    }

    public function updatedPage(): void
    {
        $this->selectAll = false;
        $this->selectedLogs = [];
    }

    public function updatedSelectedLogs(): void
    {
        $this->selectAll = count($this->selectedLogs) === $this->emailLogs->count();
    }

    public function viewContent(int $logId): void
    {
        $this->selectedLogId = $logId;
        $this->showContentModal = true;
    }

    #[Computed]
    public function selectedLog(): ?EmailLog
    {
        if (! $this->selectedLogId) {
            return null;
        }

        return EmailLog::with('attempts')->find($this->selectedLogId);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => EmailLog::count(),
            'sent' => EmailLog::where('status', 'sent')->count(),
            'failed' => EmailLog::where('status', 'failed')->count(),
            'pending' => EmailLog::where('status', 'pending')->count(),
        ];
    }

    #[Computed]
    public function emailLogs()
    {
        return EmailLog::query()
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('recipient_email', 'like', '%' . $this->search . '%')
                      ->orWhere('subject', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== '', function (Builder $query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(15);
    }

    public function confirmDeleteSelected(): void
    {
        if (empty($this->selectedLogs)) {
            $this->notification()->warning(__('Please select at least one log to delete.'));

            return;
        }

        $this->dialog()->confirm([
            'title' => __('Delete Selected Logs?'),
            'description' => __('Are you sure you want to permanently delete the selected :count logs?', ['count' => count($this->selectedLogs)]),
            'icon' => 'trash',
            'accept' => [
                'label' => __('Yes, delete them'),
                'method' => 'deleteSelected',
            ],
            'reject' => [
                'label' => __('Cancel'),
            ],
        ]);
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedLogs)) {
            return;
        }

        EmailLog::whereIn('id', $this->selectedLogs)->delete();

        $this->notification()->success(__(':count logs deleted successfully.', ['count' => count($this->selectedLogs)]));
        $this->selectedLogs = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->dialog()->confirm([
            'title' => __('Delete Log?'),
            'description' => __('Are you sure you want to permanently delete this email log?'),
            'icon' => 'trash',
            'accept' => [
                'label' => __('Yes, delete it'),
                'method' => 'deleteLog',
                'params' => $id,
            ],
            'reject' => [
                'label' => __('Cancel'),
            ],
        ]);
    }

    public function deleteLog(int $id): void
    {
        $log = EmailLog::find($id);
        if ($log) {
            $log->delete();
            $this->notification()->success(__('Log deleted successfully.'));
        }
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-8">
        <x-crud.page-header :heading="__('Email Logs')" :subheading="__('View history of all outgoing emails and their delivery status.')" icon="envelope-open" class="mb-0!" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Emails Card -->
        <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-zinc-100 dark:bg-zinc-800 rounded-full opacity-50 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                        <flux:icon.envelope-open class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Total Emails') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['total']) }}</div>
            </div>
        </div>

        <!-- Sent Card -->
        <div class="relative overflow-hidden bg-linear-to-br from-white to-emerald-50 dark:from-zinc-900 dark:to-emerald-950/20 border border-emerald-100 dark:border-emerald-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-200 dark:bg-emerald-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400">
                        <flux:icon.check-circle class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">{{ __('Sent') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['sent']) }}</div>
            </div>
        </div>

        <!-- Pending Card -->
        <div class="relative overflow-hidden bg-linear-to-br from-white to-amber-50 dark:from-zinc-900 dark:to-amber-950/20 border border-amber-100 dark:border-amber-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-200 dark:bg-amber-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400">
                        <flux:icon.clock class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Pending') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['pending']) }}</div>
            </div>
        </div>

        <!-- Failed Card -->
        <div class="relative overflow-hidden bg-linear-to-br from-white to-red-50 dark:from-zinc-900 dark:to-red-950/20 border border-red-100 dark:border-red-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-200 dark:bg-red-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400">
                        <flux:icon.x-circle class="w-5 h-5 relative z-10" />
                    </div>
                    <span class="text-sm font-medium text-red-600 dark:text-red-400 uppercase tracking-wider">{{ __('Failed') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['failed']) }}</div>
            </div>
        </div>
    </div>

    <x-crud.panel class="p-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <div class="w-full sm:w-72">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search email or subject...')" />
                </div>
                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="statusFilter" :placeholder="__('All Statuses')">
                        <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                        <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                        <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
                        <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>
            
            @if (count($selectedLogs) > 0)
                <div class="flex items-center gap-2">
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ count($selectedLogs) }} selected</span>
                    <flux:button variant="danger" icon="trash" size="sm" wire:click="confirmDeleteSelected">{{ __('Delete Selected') }}</flux:button>
                </div>
            @endif
        </div>

        <flux:table :paginate="$this->emailLogs">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('Recipient') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->emailLogs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selectedLogs" :value="$log->id" />
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">
                            <div class="flex flex-col">
                                <span>{{ $log->recipient_email }}</span>
                                <span class="text-xs text-zinc-500">{{ class_basename($log->mailable_class) }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="line-clamp-1 max-w-xs" title="{{ $log->subject }}">{{ $log->subject }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($log->status->value === 'sent')
                                <flux:badge size="sm" color="green" inset="left">{{ __('Sent') }}</flux:badge>
                            @elseif ($log->status->value === 'failed')
                                <flux:badge size="sm" color="red" inset="left">{{ __('Failed') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" inset="left">{{ __('Pending') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $log->attempts_count ?? $log->attempts()->count() }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ $log->created_at->format('M j, Y g:i A') }}
                        </flux:table.cell>
                        <flux:table.cell align="right">
                            <flux:dropdown align="end" variant="ghost">
                                <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" wire:click="viewContent({{ $log->id }})">{{ __('View Email') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $log->id }})">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            {{ __('No email logs found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>

    <flux:modal wire:model="showContentModal" class="md:max-w-4xl">
        @if ($this->selectedLog)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Email Details') }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-zinc-50 border border-zinc-200 dark:bg-zinc-800/50 dark:border-zinc-700 rounded-lg p-4">
                    <div><span class="font-medium text-zinc-500">{{ __('To:') }}</span> {{ $this->selectedLog->recipient_email }}</div>
                    <div><span class="font-medium text-zinc-500">{{ __('Subject:') }}</span> {{ $this->selectedLog->subject }}</div>
                    <div>
                        <span class="font-medium text-zinc-500">{{ __('Status:') }}</span> 
                        <span class="capitalize">{{ $this->selectedLog->status->value }}</span>
                    </div>
                    <div><span class="font-medium text-zinc-500">{{ __('Date:') }}</span> {{ $this->selectedLog->created_at->format('M j, Y g:i A') }}</div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="sm">{{ __('Content') }}</flux:heading>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden bg-white">
                        <iframe srcdoc="{{ $this->selectedLog->body }}" class="w-full h-96 border-0" sandbox></iframe>
                    </div>
                </div>

                @if ($this->selectedLog->attempts->isNotEmpty())
                    <div class="space-y-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="sm" class="text-red-500">{{ __('Delivery Attempts & Errors') }}</flux:heading>
                        
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach ($this->selectedLog->attempts as $attempt)
                                <div class="bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 rounded-lg p-3 text-sm">
                                    <div class="text-red-700 dark:text-red-400 font-medium mb-1 flex justify-between">
                                        <span>{{ __('Attempt at:') }} {{ $attempt->attempted_at->format('M j, Y g:i:s A') }}</span>
                                    </div>
                                    <div class="text-red-600 dark:text-red-300 whitespace-pre-wrap font-mono text-xs">{{ $attempt->exception_message }}</div>
                                    @if ($attempt->smtp_response)
                                        <div class="mt-2 text-red-600 dark:text-red-300 font-medium">{{ __('SMTP Response:') }}</div>
                                        <div class="text-red-600 dark:text-red-300 whitespace-pre-wrap font-mono text-xs">{{ $attempt->smtp_response }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</x-crud.page-shell>
