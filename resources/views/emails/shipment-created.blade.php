<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A new shipment has been successfully created and is now active in our system.') }}

**{{ __('Shipment Details:') }}**
- **{{ __('Reference No') }}:** {{ $shipment->reference_no }}
- **{{ __('Vehicle') }}:** {{ $shipment->vehicle?->year }} {{ $shipment->vehicle?->make }} {{ $shipment->vehicle?->model }}
- **{{ __('Origin Port') }}:** {{ $shipment->originPort?->name }} ({{ $shipment->originPort?->state?->name }})
- **{{ __('Destination Port') }}:** {{ $shipment->destinationPort?->name }} ({{ $shipment->destinationPort?->state?->name }})

<x-mail::button :url="route('shipments.show', $shipment->id, absolute: true)">
{{ __('View Shipment Status') }}
</x-mail::button>

@if (! empty($setting->address) || ! empty($setting->phone) || $location !== '')
<br>
{{ __('Company details:') }}

@if (! empty($setting->address))
- {{ __('Address') }}: {{ $setting->address }}
@endif
@if (! empty($setting->phone))
- {{ __('Phone') }}: {{ $setting->phone }}
@endif
@if ($location !== '')
- {{ __('Location') }}: {{ $location }}
@endif
@endif

{{ __('Thank you for choosing :companyName.', ['companyName' => $companyName]) }}

{{ __('Regards,') }}<br>
{{ $companyName }}
</x-mail::message>
