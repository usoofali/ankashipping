@props([
    'maxWidth' => 'max-w-xl',
])

<div {{ $attributes->class(['grid gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700', $maxWidth]) }}>
    {{ $slot }}
</div>
