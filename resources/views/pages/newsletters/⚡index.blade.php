<?php

declare(strict_types=1);

namespace App\Livewire\Newsletters;

use App\Jobs\SendIndividualNewsletterJob;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Newsletters')] class extends Component {
    use WithPagination, WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingNewsletterId = null;

    // Form fields
    public string $subject = '';
    public string $body = '';
    public ?string $url = null;
    public string $mailer = 'newsletter';


    public function mount(): void
    {
        // Only staff/admin can manage newsletters
        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'admin', 'staff']) || auth()->user()->staff()->exists(), 403);
    }

    #[Computed]
    public function newsletters()
    {
        return Newsletter::query()
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function shippers(): Collection
    {
        return User::role('shipper')->get();
    }

    public function openCreateModal(): void
    {
        $this->reset(['subject', 'body', 'url', 'mailer', 'editingNewsletterId']);
        $this->showCreateModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'url' => 'nullable|url',
            'mailer' => 'required|string|in:newsletter,google',

        ]);

        if ($this->editingNewsletterId) {
            Newsletter::find($this->editingNewsletterId)->update($validated);
            $this->notification()->success(__('Newsletter draft updated.'));
        } else {
            Newsletter::create($validated);
            $this->notification()->success(__('Newsletter draft created.'));
        }

        $this->reset(['showCreateModal', 'showEditModal']);
    }

    public function edit(int $id): void
    {
        $newsletter = Newsletter::findOrFail($id);
        $this->editingNewsletterId = $newsletter->id;
        $this->subject = $newsletter->subject;
        $this->body = $newsletter->body;
        $this->url = $newsletter->url;
        $this->mailer = $newsletter->mailer;
        $this->showCreateModal = true;
    }

    public function delete(int $id): void
    {
        Newsletter::findOrFail($id)->delete();
        $this->notification()->success(__('Newsletter deleted.'));
    }

    public function confirmSend(int $id): void
    {
        $this->dialog()->confirm([
            'title' => __('Are you sure?'),
            'description' => __('This will send the newsletter to :count shippers.', ['count' => $this->shippers->count()]),
            'icon' => 'question',
            'accept' => [
                'label' => __('Yes, send now'),
                'method' => 'send',
                'params' => $id,
            ],
            'reject' => [
                'label' => __('Cancel'),
            ],
        ]);
    }

    public function send(int $id): void
    {
        $newsletter = Newsletter::findOrFail($id);
        $shippers = $this->shippers;

        if ($shippers->isEmpty()) {
            $this->notification()->warning(__('No shippers found to send to.'));
            return;
        }

        $jobs = $shippers->map(fn($user) => new SendIndividualNewsletterJob($user, $newsletter));

        Bus::batch($jobs)
            ->name("Newsletter: {$newsletter->subject}")
            ->dispatch();

        $newsletter->update([
            'sent_at' => now(),
            'recipients_count' => $shippers->count(),
        ]);

        $this->notification()->success(__('Newsletter batch dispatched successfully.'));
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-8">
        <x-crud.page-header :heading="__('Newsletters')" :subheading="__('Draft and send announcements to your shippers.')" icon="envelope" class="mb-0!" />
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Draft Newsletter') }}</flux:button>
    </div>

    <x-crud.panel class="p-6">
        <flux:table :paginate="$this->newsletters">
            <flux:table.columns>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Mailer') }}</flux:table.column>
                <flux:table.column>{{ __('Recipients') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->newsletters as $newsletter)
                    <flux:table.row :key="$newsletter->id">
                        <flux:table.cell class="font-medium">
                            <div class="flex flex-col">
                                <span>{{ $newsletter->subject }}</span>
                                <span class="text-xs text-zinc-500 line-clamp-1">{{ Str::limit($newsletter->body, 50) }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" inset="left">{{ $newsletter->mailer }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="indigo" inset="left">{{ $newsletter->recipients_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($newsletter->sent_at)
                                <flux:badge size="sm" color="green" inset="left">{{ __('Sent') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" inset="left">{{ __('Draft') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            {{ $newsletter->created_at->diffForHumans() }}
                        </flux:table.cell>
                        <flux:table.cell align="right">
                            <flux:dropdown align="end" variant="ghost">
                                <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                <flux:menu>
                                    <flux:menu.item icon="paper-airplane" wire:click="confirmSend({{ $newsletter->id }})">{{ __('Send Now') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $newsletter->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $newsletter->id }})">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>

    <flux:modal wire:model="showCreateModal" class="md:max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="envelope" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ $editingNewsletterId ? __('Edit Newsletter') : __('Draft Newsletter') }}</flux:heading>
                    <flux:subheading>{{ __('Create a new announcement for your shippers.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="subject" :label="__('Subject')" required />
                <flux:textarea wire:model="body" :label="__('Message Body')" rows="10" required />
                <flux:input wire:model="url" :label="__('Call to Action URL (Optional)')" icon="link" placeholder="https://..." />
                
                <flux:radio.group
                    wire:model="mailer"
                    :label="__('Send Via')"
                    :description="__('Select the sending stack. Newsletter uses the dedicated roundrobin accounts (news1/2/3@ankshipping.com).')"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
                    <flux:radio
                        value="newsletter"
                        :label="__('Newsletter Accounts (Recommended)')"
                        :description="__('Cycles evenly: news1@, news2@, news3@ankshipping.com')"
                    />
                    <flux:radio
                        value="google"
                        :label="__('Google Workspace')"
                        :description="__('Uses your configured Google Workspace account.')"
                    />
                </flux:radio.group>

            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Draft') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-crud.page-shell>
