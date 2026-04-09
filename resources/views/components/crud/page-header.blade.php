@props([
    'heading',
    'subheading' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="flex min-w-0 flex-col gap-1">
        <flux:heading size="xl">{{ $heading }}</flux:heading>
        @if (filled($subheading))
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $subheading }}
            </flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
