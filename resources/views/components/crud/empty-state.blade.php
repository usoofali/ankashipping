@props([
    'icon' => 'bell',
    'title',
    'description' => null,
])

<x-crud.panel variant="dashed" {{ $attributes }}>
    <flux:icon :name="$icon" class="mx-auto mb-3 h-10 w-10 text-zinc-400 dark:text-zinc-500" />
    <flux:heading size="md">{{ $title }}</flux:heading>
    @if (filled($description))
        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ $description }}
        </flux:text>
    @endif

    @isset($actions)
        <div class="mt-6 flex justify-center">
            {{ $actions }}
        </div>
    @endisset
</x-crud.panel>
