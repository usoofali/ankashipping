<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('A new prealert has been successfully created for your vehicle.') }}

**{{ __('Vehicle Details:') }}**
- **{{ __('VIN') }}:** {{ $prealert->vin }}
- **{{ __('Vehicle') }}:** {{ $prealert->vehicle?->year }} {{ $prealert->vehicle?->make }} {{ $prealert->vehicle?->model }}
- **{{ __('Destination Port') }}:** {{ $prealert->destinationPort?->name }} ({{ $prealert->destinationPort?->state?->name }})

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
