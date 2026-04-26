<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Shipment Loaded') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('Great news! Your shipment :ref has been successfully loaded on the vessel.', [
    'ref' => $shipment->reference_no,
]) }}

{{ __('Please find the Bill of Lading and Invoice attached to this email.') }}

**{{ __('Shipment Details') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Status') }}:** {{ __('Loaded') }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment Details') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about my loaded shipment ' . $shipment->reference_no)" color="success">
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
