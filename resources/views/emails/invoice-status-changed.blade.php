<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Invoice Completed') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

{{ __('The invoice for your shipment :ref has been :status.', [
    'ref' => $shipment->reference_no,
    'status' => $toStatus->name,
]) }}

{{ __('Please find the attached invoice and Bill of Lading for your reference.') }}

**{{ __('Shipment Details') }}**
- **{{ __('Reference') }}:** {{ $shipment->reference_no }}
- **{{ __('Status') }}:** {{ $toStatus->name }}
- **{{ __('Invoice #') }}:** {{ $invoice->invoice_number }}

@if($toStatus === \App\Enums\InvoiceStatus::Completed)
{{ __('You can now proceed to make payment through your dashboard.') }}

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment & Pay') }}
</x-mail::button>
@else
<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment') }}
</x-mail::button>
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
