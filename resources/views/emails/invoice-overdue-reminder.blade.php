<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Payment Reminder: Invoice Overdue') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('This is a friendly reminder that invoice :invoice for shipment :ref is currently outstanding and overdue for payment.', [
    'invoice' => '#' . $invoice->invoice_number,
    'ref' => $shipment->reference_no,
]) }}

{{ __('Please find your original invoice attached to this email. We kindly request that you settle this outstanding balance at your earliest convenience.') }}

---

### {{ __('Invoice Details') }}
- **{{ __('Invoice Number') }}:** #{{ $invoice->invoice_number }}
- **{{ __('Shipment Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Amount Due') }}:** ${{ number_format((float) $invoice->total_amount, 2) }}
@if($invoice->issued_at)
- **{{ __('Issued Date') }}:** {{ $invoice->issued_at->format('M d, Y') }}
@endif
@if($invoice->due_at)
- **{{ __('Due Date') }}:** {{ $invoice->due_at->format('M d, Y') }}
@endif

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment & Pay Online') }}
</x-mail::button>

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question regarding overdue invoice #' . $invoice->invoice_number . ' for shipment ' . $shipment->reference_no)" color="success">
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
