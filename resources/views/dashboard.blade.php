<x-layouts::app :title="__('Dashboard')">
    @if(auth()->user()->hasRole('shipper'))
        <livewire:pages::dashboard.shipper />
    @else
        <livewire:pages::dashboard.staff />
    @endif
</x-layouts::app>
