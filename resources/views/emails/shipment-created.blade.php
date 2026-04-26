<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A new shipment has been successfully created and is now active in our system.') }}

**{{ __('Shipment Details:') }}**
- **{{ __('Reference No') }}:** {{ $shipment->reference_no }}
@if($shipment->shipping_mode?->value === 'container')
- **{{ __('Total Vehicles') }}:** {{ $shipment->vehicles->count() }}
@else
- **{{ __('VIN') }}:** {{ $shipment->vehicles->first()?->vin }}
- **{{ __('Vehicle') }}:** {{ $shipment->vehicles->first()?->year }} {{ $shipment->vehicles->first()?->make }} {{ $shipment->vehicles->first()?->model }}
@endif
- **{{ __('Origin Port') }}:** {{ $shipment->originPort?->name }} ({{ $shipment->originPort?->state?->name }})
- **{{ __('Destination Port') }}:** {{ $shipment->destinationPort?->name }} ({{ $shipment->destinationPort?->state?->name }})

<x-mail::button :url="route('shipments.show', $shipment->id, absolute: true)">
{{ __('View Shipment Status') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about my shipment ' . $shipment->reference_no)" color="success">
{{ __('Chat on WhatsApp') }}
</x-mail::button>

---
Thanks,
**{{ $companyName }}**

@if (! empty($setting->address) || ! empty($setting->phone) || ! empty($location))
<p style="color: #718096; font-size: 0.75rem; line-height: 1.25rem;">
    {{ $setting->address }} {{ $location }}<br>
    {{ $setting->phone }}
</p>
@endif
</x-mail::message>
