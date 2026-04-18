<div class="grid grid-cols-1 gap-8">
    {{-- Left Track: Shipment Journey --}}
    <x-crud.panel class="p-6">
        <flux:heading size="lg" class="mb-6 flex items-center gap-2">
            <flux:icon.globe-americas class="size-5 text-indigo-500" />
            {{ __('Shipment Journey') }}
        </flux:heading>

        @if($shipment->trackings->isEmpty())
            <flux:text class="text-zinc-500">{{ __('No tracking recorded for this shipment.') }}</flux:text>
        @else
            <div
                class="space-y-6 relative before:absolute before:inset-y-0 before:left-3 before:w-px before:bg-zinc-200 dark:before:bg-zinc-800">
                @foreach($shipment->trackings as $tracking)
                    <div class="pl-8 relative">
                        <div
                            class="absolute left-1.5 top-1.5 size-3 rounded-full bg-indigo-500 border-2 border-white dark:border-zinc-900 shadow-sm">
                        </div>
                        <div class="flex items-center justify-between gap-4 mb-2">
                            <flux:badge color="indigo" size="sm" variant="subtle">{{ $tracking->status->name }}
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
