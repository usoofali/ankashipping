<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Title Document Attached') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A new title document has been successfully attached to your vehicle with VIN: :vin.', [
    'vin' => $vehicle->vin,
]) }}

{{ __('Please find the attached document(s) for your records.') }}

**{{ __('Vehicle Details') }}**
- **{{ __('VIN') }}:** {{ $vehicle->vin }}
- **{{ __('Year/Make/Model') }}:** {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}

<x-mail::button :url="route('shipments.show', $vehicle->shipment_id, absolute: true)">
{{ __('View Shipment Details') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the title document for vehicle ' . $vehicle->vin)" color="success">
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
