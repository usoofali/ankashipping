@props([
    'maxWidth' => 'max-w-5xl',
])

<div {{ $attributes->class(['mx-auto flex w-full flex-col gap-6', $maxWidth]) }}>
    {{ $slot }}
</div>
