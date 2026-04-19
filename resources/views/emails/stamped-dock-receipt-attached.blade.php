<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Stamped Dock Receipt Available') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A stamped dock receipt has been successfully attached to your shipment :ref.', [
    'ref' => $shipment->reference_no,
]) }}

{{ __('Please find the attached document(s) for your records.') }}

**{{ __('Shipment Details') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Status') }}:** {{ $shipment->shipment_status?->name }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment Details') }}
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
