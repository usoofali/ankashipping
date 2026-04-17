<?php

declare(strict_types=1);

namespace App\Livewire\Pages\Vehicles;

use App\Livewire\Pages\Vehicles;
 
use App\Models\Vehicle;
use App\Support\VehicleDocumentFileDownloadResponder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Vehicle Details')] class extends Component {
    public Vehicle $vehicle;

    public function mount(Vehicle $vehicle): void
    {
        $this->vehicle = $vehicle->load([
            'shipment',
            'trackings' => fn($q) => $q->latest('recorded_at'),
            'vehicleDocuments.files.uploader',
            'driver',
            'workshop'
        ]);
    }

    public function downloadDocument(\App\Models\VehicleDocumentFile $file): StreamedResponse
    {
        return VehicleDocumentFileDownloadResponder::stream($this->vehicle, $file);
    }
}; ?>

<x-crud.page-shell>
    <x-crud.page-header :heading="$vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model" :subheading="__('Full vehicle details, tracking history, and documents.')">
        <x-slot name="actions">
            @if($vehicle->shipment_id)
                <flux:button variant="ghost" :href="route('shipments.show', $vehicle->shipment_id)" wire:navigate
                    icon="cube">
                    {{ __('View Shipment') }}
                </flux:button>
            @endif
            <flux:button variant="primary" :href="route('vehicles.index')" wire:navigate icon="arrow-left">
                {{ __('Back to Vehicles') }}
            </flux:button>
        </x-slot>
    </x-crud.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Vehicle Info --}}
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-6 flex items-center gap-2">
                    <flux:icon.information-circle class="size-5 text-indigo-500" />
                    {{ __('Vehicle Information') }}
                </flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">{{ __('VIN') }}
                        </flux:text>
                        <flux:text class="font-mono text-sm font-bold">{{ $vehicle->vin ?: '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                            {{ __('Lot Number') }}
                        </flux:text>
                        <flux:text class="font-bold">{{ $vehicle->lot_number ?: '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">{{ __('Status') }}
                        </flux:text>
                        <flux:badge size="sm" color="indigo" variant="subtle">
                            {{ $vehicle->tracking_status?->name ?? '—' }}
                        </flux:badge>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                            {{ __('Year / Make / Model') }}
                        </flux:text>
                        <flux:text>{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">{{ __('Color') }}
                        </flux:text>
                        <flux:text>{{ $vehicle->color ?: '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                            {{ __('Odometer') }}
                        </flux:text>
                        <flux:text>{{ $vehicle->odometer ? number_format((float) $vehicle->odometer) : '—' }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">{{ __('Keys') }}
                        </flux:text>
                        <flux:badge size="sm" :color="$vehicle->car_keys ? 'emerald' : 'amber'" variant="subtle">
                            {{ $vehicle->car_keys ? __('Yes') : __('No') }}
                        </flux:badge>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                            {{ __('Title Status') }}
                        </flux:text>
                        <flux:text>{{ $vehicle->doc_type ?: '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                            {{ __('Vehicle Is') }}
                        </flux:text>
                        <flux:badge size="sm" color="zinc" variant="outline">{{ $vehicle->vehicle_is?->label() ?? '—' }}
                        </flux:badge>
                    </div>
                </div>

                @if($vehicle->primary_damage || $vehicle->secondary_damage)
                    <div class="mt-6 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                                    {{ __('Primary Damage') }}
                                </flux:text>
                                <flux:text>{{ $vehicle->primary_damage ?: '—' }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">
                                    {{ __('Secondary Damage') }}
                                </flux:text>
                                <flux:text>{{ $vehicle->secondary_damage ?: '—' }}</flux:text>
                            </div>
                        </div>
                    </div>
                @endif

                @if($vehicle->highlights)
                    <div class="mt-4">
                        <flux:text class="text-xs font-medium uppercase text-zinc-500 tracking-wider">{{ __('Highlights') }}
                        </flux:text>
                        <flux:text>{{ $vehicle->highlights }}</flux:text>
                    </div>
                @endif
            </x-crud.panel>

            {{-- Photo Gallery Slider --}}
            @php $photos = $vehicle->copartCarPhotoUrls(); @endphp
            @if(count($photos) > 0)
                <x-crud.panel class="p-0 overflow-hidden">
                    <div class="relative group aspect-video w-full bg-zinc-100 dark:bg-zinc-900"
                        x-data="{ activeSlide: 0, slides: @js($photos) }">

                        <template x-for="(slide, index) in slides" :key="index">
                            <div x-show="activeSlide === index" x-transition.opacity.duration.300ms
                                class="absolute inset-0">
                                <img :src="slide" class="w-full h-full object-cover">
                                {{-- Full Screen View Link --}}
                                <a :href="slide" target="_blank"
                                    class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/40 transition-opacity">
                                    <flux:icon.magnifying-glass-plus class="text-white size-8" />
                                </a>
                            </div>
                        </template>

                        {{-- Navigation Controls --}}
                        <button type="button" x-show="slides.length > 1"
                            @click="activeSlide = activeSlide === 0 ? slides.length - 1 : activeSlide - 1"
                            class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-2 backdrop-blur-sm transition z-10 opacity-0 group-hover:opacity-100">
                            <flux:icon.chevron-left class="size-6" />
                        </button>

                        <button type="button" x-show="slides.length > 1"
                            @click="activeSlide = activeSlide === slides.length - 1 ? 0 : activeSlide + 1"
                            class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-2 backdrop-blur-sm transition z-10 opacity-0 group-hover:opacity-100">
                            <flux:icon.chevron-right class="size-6" />
                        </button>

                        {{-- Pagination Dots --}}
                        <div x-show="slides.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                            <template x-for="(slide, index) in slides" :key="'dot-'+index">
                                <button type="button" @click="activeSlide = index"
                                    :class="activeSlide === index ? 'bg-white scale-110 px-3' : 'bg-white/50 w-2'"
                                    class="h-2 rounded-full transition-all shadow-sm"></button>
                            </template>
                        </div>

                        {{-- Header Overlay --}}
                        <div
                            class="absolute top-0 inset-x-0 p-4 bg-gradient-to-b from-black/60 to-transparent flex items-center gap-2">
                            <flux:icon.camera class="size-5 text-indigo-400" />
                            <flux:heading size="sm" class="text-white!">{{ __('Auction Photos') }}</flux:heading>
                            <flux:badge size="xs" color="indigo" class="ml-auto">{{ count($photos) }} {{ __('Photos') }}
                            </flux:badge>
                        </div>
                    </div>
                </x-crud.panel>
            @endif

            {{-- Vehicle Tracking Timeline (Moved from Shipment Show) --}}
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-6 flex items-center gap-2">
                    <flux:icon.truck class="size-5 text-emerald-500" />
                    {{ __('Tracking History') }}
                </flux:heading>

                @if($vehicle->trackings->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No tracking recorded for this vehicle.') }}</flux:text>
                @else
                    <div
                        class="space-y-6 relative before:absolute before:inset-y-0 before:left-3 before:w-px before:bg-zinc-200 dark:before:bg-zinc-800">
                        @foreach($vehicle->trackings as $tracking)
                            <div class="pl-8 relative">
                                <div
                                    class="absolute left-1.5 top-1.5 size-3 rounded-full bg-emerald-500 border-2 border-white dark:border-zinc-900 shadow-sm">
                                </div>
                                <div class="flex items-center justify-between gap-4 mb-1">
                                    <flux:badge color="emerald" size="sm" variant="subtle">
                                        {{ $tracking->status->name }}
                                    </flux:badge>
                                    <flux:text size="xs" class="text-zinc-400 font-mono">
                                        {{ $tracking->recorded_at?->format('M d, Y H:i') }}
                                    </flux:text>
                                </div>
                                <flux:text size="sm">{{ $tracking->note }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-crud.panel>

        </div>

        <div class="space-y-6">
            {{-- Shipment Context --}}
            @if($vehicle->shipment)
                <x-crud.panel class="p-6">
                    <flux:heading size="sm" class="mb-4 flex items-center gap-2">
                        <flux:icon.cube class="size-4 text-indigo-500" />
                        {{ __('Associated Shipment') }}
                    </flux:heading>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center bg-zinc-50 dark:bg-white/5 p-3 rounded-lg">
                            <flux:text class="text-xs font-semibold uppercase text-zinc-500">{{ __('Ref #') }}</flux:text>
                            <a href="{{ route('shipments.show', $vehicle->shipment) }}" wire:navigate
                                class="text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $vehicle->shipment->reference_no }}
                            </a>
                        </div>
                        <div class="flex justify-between items-center">
                            <flux:text class="text-xs font-semibold uppercase text-zinc-500">{{ __('Status') }}</flux:text>
                            <flux:badge color="indigo" size="xs" variant="subtle">
                                {{ $vehicle->shipment->shipmentStatusDisplay() }}
                            </flux:badge>
                        </div>
                    </div>
                </x-crud.panel>
            @endif

            {{-- Vehicle Documents --}}
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                    <flux:icon.paper-clip class="size-5 text-indigo-500" />
                    {{ __('Documents') }}
                </flux:heading>

                @if($vehicle->vehicleDocuments->isEmpty())
                    <flux:text class="text-zinc-500 text-sm italic">{{ __('No documents attached.') }}</flux:text>
                @else
                    <div class="space-y-4">
                        @foreach($vehicle->vehicleDocuments as $doc)
                            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-4 last:border-0 last:pb-0">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text size="sm" class="font-bold text-indigo-900 dark:text-indigo-200">
                                        {{ $doc->document_type?->label() }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-zinc-400">
                                        {{ $doc->created_at->format('M d, Y') }}
                                    </flux:text>
                                </div>
                                @if($doc->notes)
                                    <flux:text size="xs" class="text-zinc-500 mb-2 italic">
                                        "{{ $doc->notes }}"
                                    </flux:text>
                                @endif
                                <div class="space-y-2 mt-2">
                                    @foreach($doc->files as $file)
                                        <div
                                            class="flex items-center justify-between gap-3 p-2 rounded-lg bg-zinc-50 dark:bg-white/5 group">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <flux:icon.document class="size-4 shrink-0 text-zinc-400" />
                                                <flux:text size="xs" class="truncate font-medium">
                                                    {{ $file->original_name }}
                                                </flux:text>
                                            </div>
                                            <flux:button variant="ghost" size="xs" square icon="arrow-down-tray"
                                                wire:click="downloadDocument({{ $file->id }})" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-crud.panel>

            {{-- Driver & Logistics Info --}}
            <x-crud.panel class="p-6">
                <flux:heading size="sm" class="mb-4 flex items-center gap-2">
                    <flux:icon.user-circle class="size-4 text-indigo-500" />
                    {{ __('Assigned Driver') }}
                </flux:heading>
                @if($vehicle->driver)
                    <div class="space-y-2">
                        <flux:text class="font-bold text-sm">{{ $vehicle->driver->company ?: $vehicle->driver->name }}
                        </flux:text>
                        <flux:text size="xs" class="text-zinc-500">{{ $vehicle->driver->phone }}</flux:text>
                    </div>
                @else
                    <flux:text size="sm" class="text-zinc-500 italic">{{ __('No driver assigned') }}</flux:text>
                @endif

                <flux:heading size="sm" class="mt-6 mb-4 flex items-center gap-2">
                    <flux:icon.building-office class="size-4 text-indigo-500" />
                    {{ __('Assigned Workshop') }}
                </flux:heading>
                @if($vehicle->workshop)
                    <div class="space-y-2">
                        <flux:text class="font-bold text-sm">{{ $vehicle->workshop->name }}</flux:text>
                        <flux:text size="xs" class="text-zinc-500">{{ $vehicle->workshop->location }}</flux:text>
                    </div>
                @else
                    <flux:text size="sm" class="text-zinc-500 italic">{{ __('No workshop assigned') }}</flux:text>
                @endif
            </x-crud.panel>
        </div>
    </div>
</x-crud.page-shell>