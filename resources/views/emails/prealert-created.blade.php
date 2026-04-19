<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A new prealert has been successfully created for your vehicle.') }}

@if($prealert->shipping_mode?->value === 'container')
**{{ __('Container Details:') }}**
- **{{ __('Vehicles Included') }}:** {{ $prealert->vehicles->count() }}
@if($prealert->shipment)
- **{{ __('Target Container') }}:** {{ $prealert->shipment->reference_no }}
@endif
- **{{ __('Destination Port') }}:** {{ $prealert->destinationPort?->name }} ({{ $prealert->destinationPort?->state?->name }})
@else
**{{ __('Vehicle Details:') }}**
- **{{ __('VIN') }}:** {{ $prealert->vehicles->first()?->vin }}
- **{{ __('Vehicle') }}:** {{ $prealert->vehicles->first()?->year }} {{ $prealert->vehicles->first()?->make }} {{ $prealert->vehicles->first()?->model }}
- **{{ __('Destination Port') }}:** {{ $prealert->destinationPort?->name }} ({{ $prealert->destinationPort?->state?->name }})
@endif

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
