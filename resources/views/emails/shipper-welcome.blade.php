<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('Your shipper account for :company is ready.', ['company' => $shipper->company_name]) }}

{{ __('Welcome to :companyName.', ['companyName' => $companyName]) }}

@if (! empty($setting->address) || ! empty($setting->phone) || $location !== '')
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

{{ __('Thank you for registering with us.') }}

{{ __('Regards,') }}<br>
{{ $companyName }}
</x-mail::message>
