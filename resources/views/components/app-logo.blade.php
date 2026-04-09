@props([
'sidebar' => false,
])

@php
    $system_logo = \Illuminate\Support\Facades\Cache::remember('system_logo_src', now()->addMinutes(5), function () {
        try {
            return \App\Models\SystemSetting::current()?->logoSrcForWeb();
        } catch (\Exception $e) {
            return null;
        }
    });
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ 'ANKA SHIPPING' }}" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-14 items-center justify-center rounded-md overflow-hidden {{ !$system_logo ? 'bg-accent-content text-accent-foreground' : '' }}">
            @if ($system_logo)
                <img src="{{ $system_logo }}" class="w-full h-full object-cover">
            @endif
        </x-slot>
    </flux:sidebar.brand>
@endif