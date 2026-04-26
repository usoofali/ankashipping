<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Wallet Debit Alert') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('This is to inform you that your wallet has been debited for a shipment payment.') }}

**{{ __('Transaction Details') }}**
- **{{ __('Amount Debited') }}:** {{ '$' . number_format($debitedAmount, 2) }}
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Remaining Balance') }}:** {{ '$' . number_format($currentBalance, 2) }}

{{ __('You can view your full transaction history in your wallet dashboard.') }}

<x-mail::button :url="route('shipper.wallet.index', absolute: true)">
{{ __('View Wallet Dashboard') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the debit on my wallet for shipment ' . $shipment->reference_no)" color="success">
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
