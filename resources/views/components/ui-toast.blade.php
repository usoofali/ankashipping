@props([
    'position' => 'top-end',
])

@php
    /** @var array<string, mixed>|null $flash */
    $flash = session('toast');
@endphp

<div
    wire:ignore
    x-data="{
        toasts: [],
        position: @js($position),
        addToast(type, message, timeout = 5000) {
            const id = crypto.randomUUID();
            this.toasts.push({ id, type: type || 'info', message: message || '', timeout });
            if (timeout > 0) {
                setTimeout(() => this.remove(id), timeout);
            }
            return id;
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
        positionClass() {
            return {
                'top-end': 'top-4 end-4',
                'top-start': 'top-4 start-4',
                'bottom-end': 'bottom-4 end-4',
                'bottom-start': 'bottom-4 start-4',
            }[this.position] || 'top-4 end-4';
        },
        styles(type) {
            const t = type || 'info';
            const map = {
                success: 'text-zinc-700 dark:text-zinc-200',
                error: 'text-zinc-700 dark:text-zinc-200',
                danger: 'text-zinc-700 dark:text-zinc-200',
                warning: 'text-zinc-700 dark:text-zinc-200',
                info: 'text-zinc-700 dark:text-zinc-200',
            };
            return map[t] || map.info;
        },
        accent(type) {
            const t = type || 'info';
            const map = {
                success: 'bg-emerald-500',
                error: 'bg-red-500',
                danger: 'bg-red-500',
                warning: 'bg-amber-500',
                info: 'bg-accent',
            };
            return map[t] || map.info;
        },
        init() {
            window.addEventListener('ui-toast', (e) => {
                const d = e.detail || {};
                this.addToast(d.type, d.message, d.timeout ?? 5000);
            });
            if (typeof Livewire !== 'undefined') {
                Livewire.on('notify', (detail) => {
                    const d = Array.isArray(detail) ? detail[0] : detail;
                    this.addToast(d?.type ?? 'info', d?.message ?? '', d?.timeout ?? 5000);
                });
            }
            @if ($flash)
                this.addToast(
                    @json($flash['type'] ?? 'info'),
                    @json($flash['message'] ?? ''),
                    @json($flash['timeout'] ?? 5000),
                );
            @endif
        },
    }"
    class="pointer-events-none fixed z-[100] flex max-w-full flex-col gap-2"
    :class="positionClass()"
    role="region"
    aria-label="{{ __('Notifications') }}"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto relative flex w-full max-w-sm items-center rounded-xl border border-zinc-200 bg-zinc-50/95 p-4 shadow-xs backdrop-blur-sm dark:border-zinc-700 dark:bg-zinc-900/95"
            x-bind:class="styles(toast.type)"
            x-transition:enter="transform-gpu transition duration-250 ease-out"
            x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transform-gpu transition duration-200 ease-in"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            role="alert"
        >
            <div
                class="relative inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md"
                :class="{
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300': toast.type === 'success',
                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': ['error', 'danger'].includes(toast.type),
                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': toast.type === 'warning',
                    'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300': !['success', 'error', 'danger', 'warning'].includes(toast.type),
                }"
            >
                <template x-if="(toast.timeout || 0) > 0">
                    <svg
                        class="pointer-events-none absolute -inset-1 size-9 -rotate-90"
                        viewBox="0 0 32 32"
                        aria-hidden="true"
                    >
                        <circle
                            cx="16"
                            cy="16"
                            r="14"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="opacity-25"
                        />
                        <circle
                            cx="16"
                            cy="16"
                            r="14"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-dasharray="88"
                            stroke-dashoffset="0"
                            x-bind:style="`animation: toast-ring linear ${toast.timeout}ms forwards;`"
                        />
                    </svg>
                </template>
                <flux:icon.check-circle x-show="toast.type === 'success'" variant="mini" class="size-5" />
                <flux:icon.x-circle x-show="['error', 'danger'].includes(toast.type)" variant="mini" class="size-5" />
                <flux:icon.exclamation-triangle x-show="toast.type === 'warning'" variant="mini" class="size-5" />
                <flux:icon.information-circle x-show="!['success', 'error', 'danger', 'warning'].includes(toast.type)" variant="mini" class="size-5" />
            </div>

            <div class="ms-3 min-w-0 flex-1 text-sm font-normal leading-5 text-zinc-700 dark:text-zinc-200" x-text="toast.message"></div>

            <button
                type="button"
                class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent bg-transparent text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-100 dark:focus:ring-zinc-600"
                aria-label="{{ __('Dismiss') }}"
                @click="remove(toast.id)"
            >
                <flux:icon.x-mark variant="mini" class="size-5" />
            </button>

        </div>
    </template>
</div>

<style>
    @keyframes toast-ring {
        from { stroke-dashoffset: 0; }
        to { stroke-dashoffset: 88; }
    }
</style>
