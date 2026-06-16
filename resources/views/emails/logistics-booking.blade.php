<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ $shipment->booked_without_title ? __('Booked Without Title') : __('Logistics Updated') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

@if($shipment->booked_without_title)
{{ __('The logistics and booking information for shipment :ref has been completed, but the shipment was booked WITHOUT a Title Document.', [
    'ref' => $shipment->reference_no,
]) }}

**{{ __('ACTION REQUIRED: Please provide the Title Document for this shipment as soon as possible.') }}**
@else
{{ __('The logistics and booking information for shipment :ref has been updated.', [
    'ref' => $shipment->reference_no,
]) }}
@endif

{{ __('Please find the latest Dock Receipt attached to this email.') }}

**{{ __('Shipment Details') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Status') }}:** {{ $shipment->shipment_status?->name }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment Details') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the logistics for shipment ' . $shipment->reference_no)" color="success">
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
