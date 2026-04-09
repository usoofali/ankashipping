@props([
    'variant' => 'solid',
])

@php
    $panelClass = match ($variant) {
        'dashed' => 'rounded-sm border border-dashed border-zinc-300 bg-white px-6 py-12 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900',
        'form' => 'rounded-sm border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-8',
        default => 'overflow-x-auto rounded-sm border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900',
    };
@endphp

<div {{ $attributes->class([$panelClass]) }}>
    {{ $slot }}
</div>
