@props([
    'name' => 'confirm',
    'title' => '',
    'message' => '',
    'variant' => 'danger',
    'confirmLabel' => null,
    'cancelLabel' => null,
])

@php
    $confirmVariant = $variant === 'danger' ? 'danger' : 'primary';
@endphp

<flux:modal :name="$name" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if (filled($message))
                <flux:subheading class="mt-2">{{ $message }}</flux:subheading>
            @endif
        </div>

        @if ($slot->isNotEmpty())
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $slot }}
            </div>
        @endif
    </div>

    <div class="mt-6 flex justify-end gap-2">
        <flux:modal.close>
            <flux:button variant="filled" type="button">
                {{ $cancelLabel ?? __('Cancel') }}
            </flux:button>
        </flux:modal.close>

        <flux:modal.close>
            <flux:button
                variant="{{ $confirmVariant }}"
                type="button"
                @click="Livewire.dispatch('confirm-modal-confirmed', { name: @js($name) })"
            >
                {{ $confirmLabel ?? __('Confirm') }}
            </flux:button>
        </flux:modal.close>
    </div>
</flux:modal>
