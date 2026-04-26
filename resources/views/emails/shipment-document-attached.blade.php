<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
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

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the document attached to shipment ' . $shipment->reference_no)" color="success">
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
