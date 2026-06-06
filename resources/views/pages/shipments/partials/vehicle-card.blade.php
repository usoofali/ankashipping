<div wire:key="v-{{ $vehicle->id }}"
    class="relative group bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
    <div class="flex flex-col lg:flex-row">
        {{-- Photo Slider --}}
        <div class="lg:w-[45%] w-full bg-zinc-100 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 relative min-h-[300px] lg:min-h-full"
            x-data="{ activeSlide: 0, slides: @js($vehicle->copartCarPhotoUrls()) }">

            <template x-for="(slide, index) in slides" :key="index">
                <div x-show="activeSlide === index" x-transition.opacity.duration.300ms class="absolute inset-0">
                    <img :src="slide" class="w-full h-full object-cover">
                </div>
            </template>

            {{-- Navigation Controls --}}
            <button type="button" x-show="slides.length > 1"
                @click="activeSlide = activeSlide === 0 ? slides.length - 1 : activeSlide - 1"
                class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-1.5 backdrop-blur-sm transition z-10">
                <flux:icon.chevron-left class="size-5" />
            </button>

            <button type="button" x-show="slides.length > 1"
                @click="activeSlide = activeSlide === slides.length - 1 ? 0 : activeSlide + 1"
                class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-1.5 backdrop-blur-sm transition z-10">
                <flux:icon.chevron-right class="size-5" />
            </button>

            {{-- Pagination Dots --}}
            <div x-show="slides.length > 1" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                <template x-for="(slide, index) in slides" :key="'dot-'+index">
                    <button type="button" @click="activeSlide = index"
                        :class="activeSlide === index ? 'bg-white scale-110' : 'bg-white/50'"
                        class="w-2 h-2 rounded-full transition-all shadow-sm"></button>
                </template>
            </div>

            {{-- Fallback Empty State --}}
            <div x-show="slides.length === 0"
                class="absolute inset-0 flex flex-col items-center justify-center text-zinc-400">
                <flux:icon.photo class="size-12 mb-2 opacity-50" />
                <span class="font-medium text-sm">{{ __('No photos available') }}</span>
            </div>
        </div>

        {{-- Details --}}
        <div class="lg:w-[55%] w-full p-4 lg:p-5 flex flex-col">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h4 class="font-bold text-xl text-zinc-900 dark:text-white leading-tight flex items-center gap-2">
                        {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                        @if($vehicle->workshop_id)
                            <flux:badge color="amber" size="sm" variant="subtle">
                                {{ __('At Workshop') }}
                            </flux:badge>
                        @endif
                    </h4>
                    <p class="text-[8px] text-zinc-500 mt-1 uppercase tracking-widest font-bold">
                        {{ __('Vehicle Identification (VIN)') }}
                    </p>
                    <div class="flex flex-col">
                        <p class="font-mono text-zinc-700 dark:text-zinc-300 font-medium text-sm mt-0.5">
                            {{ $vehicle->vin }}
                        </p>
                        @if($vehicle->tracking_status)
                            <div class="mt-2">
                                <flux:badge size="xs" color="zinc" variant="outline" icon="car-front">
                                    {{ $vehicle->tracking_status->name }}
                                </flux:badge>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex items-start justify-end gap-2 shrink-0">
                    @if(!$shipment->isLocked())
                        <flux:dropdown>
                            <flux:button icon="ellipsis-horizontal" size="sm" variant="outline">
                                {{ __('Actions') }}
                            </flux:button>
                            <flux:menu>
                                @if($this->workflow()->canAssignDriver($shipment, $vehicle))
                                    @can('workflow.assign_driver')
                                        <flux:menu.item icon="user-plus" wire:click="openAssignDriverModal({{ $vehicle->id }})">
                                            {{ __('Assign Driver') }}
                                        </flux:menu.item>
                                    @endcan
                                @endif

                                @if($this->workflow()->canAttachTitle($shipment, $vehicle))
                                    @can('workflow.attach_title')
                                        <flux:menu.item icon="document-text"
                                            wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::TitleDocument->value }}')">
                                            {{ __('Attach Title') }}
                                        </flux:menu.item>
                                    @endcan
                                @endif

                                @if($this->workflow()->canAttachPhotos($shipment, $vehicle))
                                    @can('workflow.upload_photos')
                                        <flux:menu.item icon="photo"
                                            wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::PhotosAndVideos->value }}')">
                                            {{ __('Upload Photos') }}
                                        </flux:menu.item>
                                    @endcan
                                @endif

                                @can('workflow.upload_photos')
                                    <flux:menu.item icon="paper-clip"
                                        wire:click="openAttachVehicleDocumentModal({{ $vehicle->id }}, '{{ \App\Enums\VehicleDocumentType::Other->value }}')">
                                        {{ __('Other Document') }}
                                    </flux:menu.item>
                                @endcan

                                @if(($vehicle->workshop_id && auth()->user()->can('workflow.from_workshop')) || (!$vehicle->workshop_id && auth()->user()->can('workflow.to_workshop')))
                                    <flux:menu.separator />
                                    @if($vehicle->workshop_id)
                                        <flux:menu.item icon="arrow-uturn-left"
                                            wire:click="openFromWorkshopConfirmModal({{ $vehicle->id }})">
                                            {{ __('From Workshop') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item icon="wrench" wire:click="openToWorkshopModal({{ $vehicle->id }})">
                                            {{ __('Send to Workshop') }}
                                        </flux:menu.item>
                                    @endif
                                @endif

                                @can('documents.view')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="eye" wire:click="openVehicleDocumentsModal({{ $vehicle->id }})">
                                        {{ __('View Documents') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </div>

            <div
                class="grid grid-cols-2 sm:grid-cols-3 gap-y-4 gap-x-4 text-xs mt-auto border-t border-zinc-100 dark:border-zinc-700/50 pt-4">
                @if($vehicle->lot_number)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Lot #') }}</p>
                        <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ $vehicle->lot_number }}</p>
                    </div>
                @endif

                @if($vehicle->vehicle_is)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Condition') }}
                        </p>
                        <flux:badge color="indigo" size="sm">{{ $vehicle->vehicle_is->label() }}</flux:badge>
                    </div>
                @endif

                @if($vehicle->workshop)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Workshop') }}
                        </p>
                        <p
                            class="uppercase tracking-widest font-bold text-zinc-900 dark:text-zinc-100 whitespace-normal break-words">
                            {{ $vehicle->workshop->name }}
                        </p>
                    </div>
                @endif

                @if($vehicle->driver)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Driver') }}</p>
                        <div class="flex flex-col">
                            <span class="font-bold text-zinc-900 dark:text-zinc-100 whitespace-normal break-words">
                                {{ $vehicle->driver->company ?: __('Partner Driver') }}
                            </span>
                            <flux:button variant="ghost" size="sm" x-data
                                class="!p-0 !h-auto !min-h-0 text-indigo-600 dark:text-indigo-400 mt-0.5 justify-start"
                                x-on:click.stop="window.navigator.clipboard.writeText('{{ $vehicle->driver->phone }}'); $dispatch('ui-toast', { type: 'success', message: '{{ __('Copied') }}' })">
                                <flux:text color="indigo">{{ $vehicle->driver->phone }}</flux:text>
                                <flux:icon.clipboard-document class="size-3.5 ml-1" />
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if($vehicle->gatepass_pin)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                            {{ __('Gatepass PIN') }}
                        </p>
                        <p class="uppercase tracking-widest font-mono font-bold text-emerald-600 font-semibold">
                            {{ $vehicle->gatepass_pin }}
                        </p>
                    </div>
                @endif

                @if($vehicle->location)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Location') }}
                        </p>
                        <p
                            class="uppercase tracking-widest font-bold text-zinc-900 dark:text-zinc-100 whitespace-normal break-words">
                            {{ $vehicle->location }}
                        </p>
                    </div>
                @endif

                @if($vehicle->auction_name)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Auction') }}
                        </p>
                        <p
                            class="uppercase tracking-widest font-bold text-zinc-900 dark:text-zinc-100 whitespace-normal break-words">
                            {{ $vehicle->auction_name }}
                        </p>
                    </div>
                @endif

                @if($vehicle->weight > 0)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">{{ __('Weight') }}</p>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ number_format($vehicle->weight_kg, 2) }} {{ __('Kg') }}<br>
                            <span class="text-zinc-500 text-[10px]">{{ number_format($vehicle->weight_lb, 2) }}
                                {{ __('Lb') }}</span>
                        </p>
                    </div>
                @endif

                @if($vehicle->measurement > 0)
                    <div>
                        <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                            {{ __('Measurement') }}
                        </p>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ number_format($vehicle->measurement_ft3, 2) }} {{ __('ft³') }}<br>
                            <span class="text-zinc-500 text-[10px]">{{ number_format($vehicle->measurement_vlb, 2) }}
                                {{ __('Vlb') }}</span>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>