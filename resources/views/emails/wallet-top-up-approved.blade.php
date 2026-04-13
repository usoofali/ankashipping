<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif

# {{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('Your wallet funding request has been approved and your balance has been updated.') }}

<x-mail::panel>
**{{ __('Amount Credited') }}:** ${{ number_format((float) $topUp->amount, 2) }}
</x-mail::panel>

**{{ __('Details') }}:**
- **{{ __('Reference') }}:** {{ $topUp->reference ?: 'N/A' }}
- **{{ __('Date Approved') }}:** {{ $topUp->updated_at->format('M d, Y H:i') }}

<x-mail::button :url="$url">
{{ __('View Wallet Balance') }}
</x-mail::button>

{{ __('Thank you for choosing :companyName.', ['companyName' => $companyName]) }}

{{ __('Regards,') }}<br>
{{ $companyName }}
</x-mail::message>
