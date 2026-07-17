<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Official Telex Release Notice') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('The official Telex Release text from the ocean carrier for your shipment :ref is now available below.', ['ref' => $shipment->reference_no]) }}

**{{ __('Shipment Summary') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Bill of Lading') }}:** {{ $shipment->bill_of_lading_number ?: '—' }}
- **{{ __('Vessel') }}:** {{ $shipment->vessel_name ?: '—' }} / **{{ __('Voyage') }}:** {{ $shipment->voyage_no ?: '—' }}

**{{ __('Official Carrier Release Text') }}**
<x-mail::panel>
<div style="font-family: monospace; white-space: pre-wrap; font-size: 0.875rem; line-height: 1.5;">
{{ $shipment->telex_release_text }}
</div>
</x-mail::panel>

{{ __('Your cargo is released and ready for pickup at the destination port against proper identification without presentation of Original Bills of Lading.') }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View shipment') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the Telex Release for shipment ' . $shipment->reference_no)" color="success">
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
