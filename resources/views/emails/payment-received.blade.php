<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Payment Received') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('We have successfully received your payment for shipment :ref.', ['ref' => $shipment->reference_no]) }}

**{{ __('Payment Details') }}**
- **{{ __('Amount Paid') }}:** {{ '$' . number_format($paidAmount, 2) }}
- **{{ __('Shipment Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Invoice #') }}:** {{ $invoice->invoice_number }}

{{ __('A copy of your paid invoice has been attached to this email for your records.') }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment Details') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about my payment for shipment ' . $shipment->reference_no)" color="success">
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
