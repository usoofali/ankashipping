<?php

declare(strict_types=1);

use App\Actions\Financial\ApproveWalletTopUpAction;
use App\Actions\Financial\RejectWalletTopUpAction;
use App\Models\WalletTopUp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Wallet Funding Approvals')] class extends Component {
    use WithPagination;
    use WireUiActions;

    public $statusFilter = 'pending';
    
    public bool $showRejectModal = false;
    public ?int $rejectingId = null;
    public string $rejectionReason = '';

    public bool $showApproveModal = false;
    public ?int $approvingId = null;

    #[Computed]
    public function topUps()
    {
        return WalletTopUp::with(['shipper', 'approver'])
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);
    }

    public function confirmApprove(int $id)
    {
        $this->authorize('wallet_top_ups.approve');
        $this->approvingId = $id;
        $this->showApproveModal = true;
    }

    public function approve(ApproveWalletTopUpAction $action)
    {
        $this->authorize('wallet_top_ups.approve');

        $topUp = WalletTopUp::findOrFail($this->approvingId);
        
        try {
            $action->execute(Auth::user(), $topUp);
            $this->notification()->success('Top-Up Approved', 'The wallet has been successfully funded.');
            
            $this->showApproveModal = false;
            $this->approvingId = null;
            unset($this->topUps);
        } catch (\Exception $e) {
            $this->notification()->error('Approval Failed', $e->getMessage());
        }
    }

    public function confirmReject(int $id)
    {
        $this->authorize('wallet_top_ups.reject');

        $this->rejectingId = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function reject(RejectWalletTopUpAction $action)
    {
        $this->authorize('wallet_top_ups.reject');

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000']
        ]);

        $topUp = WalletTopUp::findOrFail($this->rejectingId);

        try {
            $action->execute(Auth::user(), $topUp, $this->rejectionReason);
            $this->notification()->success('Top-Up Rejected');
            $this->showRejectModal = false;
            $this->rejectingId = null;
            $this->rejectionReason = '';
            unset($this->topUps);
        } catch (\Exception $e) {
            $this->notification()->error('Rejection Failed', $e->getMessage());
        }
    }
};
?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Wallet Funding Approvals')" :subheading="__('Review and verify shipper top-up requests.')" icon="banknotes" class="!mb-0" />
            
            <div class="flex items-center gap-3">
                <flux:select wire:model.live="statusFilter" placeholder="Filter by Status" class="w-48">
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="approved">Approved</flux:select.option>
                    <flux:select.option value="rejected">Rejected</flux:select.option>
                    <flux:select.option value="">All Statuses</flux:select.option>
                </flux:select>
            </div>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->topUps">
                <flux:table.columns>
                    <flux:table.column icon="calendar">Date</flux:table.column>
                    <flux:table.column icon="building-office">Shipper</flux:table.column>
                    <flux:table.column icon="currency-dollar">Amount</flux:table.column>
                    <flux:table.column icon="hashtag">Reference</flux:table.column>
                    <flux:table.column icon="document-text">Receipt</flux:table.column>
                    <flux:table.column icon="exclamation-circle">Status</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @forelse($this->topUps as $topUp)
                    <flux:table.row :key="$topUp->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $topUp->created_at->format('M d, Y H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $topUp->shipper->company_name ?? 'Shipper #'.$topUp->shipper_id }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-bold text-emerald-600 dark:text-emerald-400">
                            ${{ number_format($topUp->amount, 2) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ Str::limit($topUp->reference ?: '-', 15) }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ Storage::url($topUp->receipt_path) }}" target="_blank" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <flux:icon.arrow-top-right-on-square class="size-4" /> View
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($topUp->status->value === 'pending')
                                <flux:badge color="amber" size="sm">Pending</flux:badge>
                            @elseif($topUp->status->value === 'approved')
                                <flux:badge color="emerald" size="sm">Approved</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Rejected</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="right">
                            @if($topUp->status->value === 'pending')
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('wallet_top_ups.approve')
                                            <flux:menu.item wire:click="confirmApprove({{ $topUp->id }})" icon="check" class="text-emerald-600 hover:text-emerald-700">Approve</flux:menu.item>
                                        @endcan
                                        @can('wallet_top_ups.reject')
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="confirmReject({{ $topUp->id }})" icon="x-mark" variant="danger">Reject</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            @elseif($topUp->status->value === 'rejected')
                                <div class="text-right">
                                    <span class="block text-xs text-zinc-500">By {{ $topUp->approver?->name ?? 'Admin' }} ({{ $topUp->approver?->roles?->first()?->name ?? 'Staff' }})</span>
                                    <span class="block text-xs text-red-500 mt-0.5" title="{{ $topUp->rejection_reason }}">Hover for reason</span>
                                </div>
                            @else
                                <span class="text-xs text-zinc-500">By {{ $topUp->approver?->name ?? 'Admin' }} ({{ $topUp->approver?->roles?->first()?->name ?? 'Staff' }})</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            No top-ups found matching your criteria.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>
    </x-crud.page-shell>

    <!-- Approve Modal -->
    <flux:modal wire:model="showApproveModal" class="max-w-md">
        <form wire:submit="approve" class="space-y-6">
            <div>
                <flux:heading size="lg">Approve Funding Request</flux:heading>
                <flux:subheading>
                    Are you sure you want to officially approve this top-up? The shipper's wallet will be securely credited in the ledger. This action cannot be easily reversed.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Approve Request</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Reject Modal -->
    <flux:modal wire:model="showRejectModal" class="md:w-[32rem]">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <flux:heading size="lg">Reject Top-Up</flux:heading>
                <flux:subheading>Please provide a reason for rejecting this funding request.</flux:subheading>
            </div>

            <flux:textarea wire:model="rejectionReason" label="Rejection Reason" required rows="3" placeholder="e.g. Receipt image is blurry, or funds not verified in bank." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Confirm Rejection</flux:button>
            </div>
        </form>
    </flux:modal>

</div>
