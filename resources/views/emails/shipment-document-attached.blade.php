<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif

# {{ $notificationTitle }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('Your shipment :ref has new file(s) attached.', ['ref' => $shipment->reference_no]) }}

**{{ __('Details') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Document type') }}:** {{ $documentLabel }}
- **{{ __('Files') }}:** {{ $fileCount }}

@if ($fromShipmentStatus && $toShipmentStatus && $fromShipmentStatus !== $toShipmentStatus)
- **{{ __('Shipment status') }}:** {{ $fromShipmentStatus->name }} → {{ $toShipmentStatus->name }}
@endif

@if ($downloadLinks !== [])
**{{ __('Download') }}**
@foreach ($downloadLinks as $link)
<x-mail::button :url="$link['url']">
{{ __('Download: :name', ['name' => $link['name']]) }}
</x-mail::button>
@endforeach
@endif

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View shipment') }}
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
