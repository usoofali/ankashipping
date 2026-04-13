<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
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
