<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

new class extends Component {
    public string $menuPosition = 'top';

    public bool $dropdownOpen = false;

    public int $previousUnreadCount = 0;

    public function mount(string $menuPosition = 'top'): void
    {
        $this->menuPosition = $menuPosition;
        $this->previousUnreadCount = $this->unreadCount();
    }

    public function refreshNotifications(): void
    {
        $currentUnreadCount = $this->unreadCount();

        if ($currentUnreadCount > $this->previousUnreadCount) {
            $this->dropdownOpen = true;
            $this->dispatch('notifications:new-unread', unreadCount: $currentUnreadCount);
        }

        $this->previousUnreadCount = $currentUnreadCount;
    }

    public function unreadCount(): int
    {
        return (int) (auth()->user()?->unreadNotifications()->count() ?? 0);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function unreadItems(): Collection
    {
        /** @var Collection<int, DatabaseNotification> $items */
        $items = auth()->user()?->unreadNotifications()->latest()->limit(10)->get() ?? new Collection;

        return $items;
    }
}; ?>

@php
    $unreadCount = $this->unreadCount();
    $items = $this->unreadItems();
    $badgeLabel = $unreadCount >= 10 ? '9+' : (string) $unreadCount;
    $badgeColorClasses = match (true) {
        $unreadCount === 0 => 'bg-red-500 text-white ring-red-100 dark:ring-red-900/40',
        $unreadCount >= 10 => 'bg-emerald-500 text-white ring-emerald-100 dark:ring-emerald-900/40',
        default => 'bg-amber-500 text-white ring-amber-100 dark:ring-amber-900/40',
    };
@endphp

<div
    class="shrink-0"
    wire:poll.15s="refreshNotifications"
    x-on:pointerdown.capture="primeAudio()"
    x-data="{
        init() {
            if (! window.__ankaNotifAudio) {
                window.__ankaNotifAudio = {
                    soundUrl: '/sounds/notify.mp3',
                    sound: null,
                    lastPlayedAt: 0,
                    audioPrimed: false,
                    pendingSound: false,
                };
            }

            if (window.__ankaNotifSoundSingleton) {

                return;
            }

            window.__ankaNotifSoundSingleton = true;
            const store = () => window.__ankaNotifAudio;

            window.__ankaNotifPrime = () => {
                const s = store();

                if (s.audioPrimed) {

                    return;
                }

                if (! s.sound) {
                    s.sound = new Audio(s.soundUrl);
                    s.sound.preload = 'auto';
                }

                s.audioPrimed = true;

                s.sound.muted = true;
                s.sound.currentTime = 0;

                s.sound.play()
                    .then(() => {
                        s.sound.pause();
                        s.sound.currentTime = 0;
                    })
                    .catch((err) => {
                        console.warn('[notifications] primeAudio: muted play failed', err);
                    })
                    .finally(() => {
                        s.sound.muted = false;

                        if (s.pendingSound) {
                            s.pendingSound = false;
                            window.__ankaNotifPlay();
                        }
                    });
            };

            window.__ankaNotifPlay = () => {
                const s = store();

                if (! s.audioPrimed) {
                    s.pendingSound = true;

                    return;
                }

                const now = Date.now();
                if (now - s.lastPlayedAt < 2000) {

                    return;
                }

                s.lastPlayedAt = now;

                if (! s.sound) {
                    s.sound = new Audio(s.soundUrl);
                    s.sound.preload = 'auto';
                }

                s.sound.muted = false;
                s.sound.currentTime = 0;

                s.sound.play()
                    .then(() => {
                    })
                    .catch((err) => {
                        console.warn('[notifications] play: failed', err);
                    });
            };

            window.addEventListener('notifications:new-unread', (event) => {
                const detail = event?.detail ?? {};
                const count = detail.unreadCount ?? 0;
                const now = Date.now();

                if (! window.__ankaNotifDedupe) {
                    window.__ankaNotifDedupe = { count: null, t: 0 };
                }

                const d = window.__ankaNotifDedupe;
                if (d.count === count && now - d.t < 3000) {

                    return;
                }

                d.count = count;
                d.t = now;

                window.__ankaNotifPlay();
            });

            const primeOnce = () => {
                window.__ankaNotifPrime();
            };

            window.addEventListener('pointerdown', primeOnce, { once: true, passive: true });
            window.addEventListener('touchstart', primeOnce, { once: true, passive: true });
            window.addEventListener('keydown', primeOnce, { once: true });
        },
        primeAudio() {
            if (typeof window.__ankaNotifPrime === 'function') {
                window.__ankaNotifPrime();
            }
        },
    }"
>
    <flux:dropdown wire:model.self="dropdownOpen" :position="$menuPosition" align="end">
        <flux:button
            variant="ghost"
            size="sm"
            :square="true"
            icon="bell"
            class="relative"
            data-test="notifications-menu-button"
            aria-label="{{ __('Notifications') }}"
        >
            <span
                @class([
                    'absolute -end-1 -top-1 z-10 inline-flex h-4 min-w-4 items-center justify-center rounded-sm px-1 text-[9px] font-bold leading-none ring-1 ring-zinc-50 dark:ring-zinc-900',
                    $badgeColorClasses,
                ])
                aria-hidden="true"
            >
                {{ $badgeLabel }}
            </span>
        </flux:button>

        <flux:menu class="max-h-80 min-w-72 overflow-y-auto">
            <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                <flux:heading size="sm" class="px-0">{{ __('Notifications') }}</flux:heading>
                @if ($unreadCount > 0)
                    <flux:badge size="sm" color="zinc">{{ $unreadCount }} {{ __('unread') }}</flux:badge>
                @endif
            </div>
            <flux:menu.separator />

            @if ($items->isEmpty())
                <div class="px-3 py-6 text-center">
                    <flux:text class="text-sm text-zinc-500">{{ __('No unread notifications.') }}</flux:text>
                </div>
            @else
                <flux:menu.radio.group>
                    @foreach ($items as $notification)
                        @php
                            $data = $notification->data;
                            $title = data_get($data, 'title', __('Notification'));
                            $body = data_get($data, 'body', '');
                            $baseUrl = data_get($data, 'url');
                            $href = null;
                            if ($baseUrl) {
                                $sep = str_contains($baseUrl, '?') ? '&' : '?';
                                $href = $baseUrl.$sep.'notification='.urlencode($notification->id);
                            }
                        @endphp
                        @if ($href)
                            <flux:menu.item class="items-start whitespace-normal" :href="$href" wire:navigate>
                                <div class="grid max-w-64 gap-0.5 text-start">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</span>
                                    @if ($body !== '')
                                        <flux:text class="text-xs">{{ $body }}</flux:text>
                                    @endif
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </flux:menu.item>
                        @else
                            <flux:menu.item class="items-start whitespace-normal">
                                <div class="grid max-w-64 gap-0.5 text-start">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</span>
                                    @if ($body !== '')
                                        <flux:text class="text-xs">{{ $body }}</flux:text>
                                    @endif
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </flux:menu.item>
                        @endif
                    @endforeach
                </flux:menu.radio.group>
            @endif

            <flux:menu.separator />
            <flux:menu.radio.group>
                <flux:menu.item icon="arrow-top-right-on-square" :href="route('notifications.index')" wire:navigate>
                    {{ __('View all notifications') }}
                </flux:menu.item>
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>
</div>
