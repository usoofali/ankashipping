<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('Your shipper account for :company is ready.', ['company' => $shipper->company_name]) }}

{{ __('Welcome to :companyName.', ['companyName' => $companyName]) }}

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I just joined and have a question.')" color="success">
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
