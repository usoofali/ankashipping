<x-crud.panel class="p-6">
    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
        <flux:icon.list-bullet class="size-5 text-indigo-500" />
        {{ __('Activity Log') }}
    </flux:heading>

    @if($shipment->activityLogs->isEmpty())
        <flux:text class="text-zinc-500">
            {{ __('No activity has been recorded for this shipment yet.') }}
        </flux:text>
    @else
        @php
            $activityLogPresenter = app(\App\Support\ShipmentActivityLogPresenter::class);
        @endphp
        <div class="space-y-3">
            @foreach($shipment->activityLogs->sortByDesc('created_at') as $log)
                <div
                    class="flex items-start gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                    <flux:avatar :name="$log->user?->name ?? 'System'" size="xs"
                        class="bg-zinc-100! text-zinc-700!" />
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <flux:text size="sm" class="font-medium">
                                {{ $log->user?->name ?? __('System') }}
                            </flux:text>
                            <flux:text size="xs" class="text-zinc-500">
                                {{ $log->created_at?->diffForHumans() }}
                            </flux:text>
                        </div>
                        <flux:text size="sm" class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">
                            {{ $activityLogPresenter->title($log) }}
                        </flux:text>
                        @php
                            $activityBadges = $activityLogPresenter->badges($log);
                        @endphp
                        @if(count($activityBadges) > 0)
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($activityBadges as $ab)
                                    <flux:badge size="sm" color="zinc" variant="{{ $ab['variant'] ?? 'subtle' }}">
                                        {{ $ab['text'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-crud.panel>
