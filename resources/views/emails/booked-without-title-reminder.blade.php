<x-mail::message>
@if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 24px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
</p>
@endif

# {{ __('Title Document Required') }}

{{ __('Hello :name!', ['name' => $notifiable->name]) }}

**{{ __('Please be advised this unit was delivered without a title. Kindly mail the title to below address and send us tracking number and copy of the title. Note: please use FedEx') }}**

---

### {{ __('Shipment & Vehicle Information') }}
- **{{ __('Reference No') }}:** {{ $shipment->reference_no }}
@if($vehicle)
- **{{ __('Vehicle') }}:** {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
- **{{ __('VIN') }}:** `{{ $vehicle->vin }}`
@endif
@if(! empty($shipment->booking_number))
- **{{ __('Booking No') }}:** {{ $shipment->booking_number }}
@endif
@if($originPort)
- **{{ __('Origin Port') }}:** {{ $originPort->name }}
@endif

---

### 📍 {{ __('Terminal Delivery Address') }}
@if($originPort && (! empty($originPort->terminal_name) || ! empty($originPort->terminal_address)))
**{{ $originPort->terminal_name ?: $originPort->name }}**  
{{ $originPort->terminal_address }}  
{{ collect([$originPort->terminal_state, $originPort->terminal_zipcode])->filter()->implode(' ') }}  
@if(! empty($originPort->terminal_phone))
{{ __('Phone') }}: {{ $originPort->terminal_phone }}  
@endif
@if(! empty($originPort->terminal_email))
{{ __('Email') }}: {{ $originPort->terminal_email }}  
@endif
@else
{{ __('Please deliver the title via FedEx to our designated port terminal address. Contact support for exact terminal coordinates.') }}
@endif

---

### 📬 {{ __('How to Send Us the Tracking & Copy') }}
{{ __('You do not need to upload the document yourself. Once you have mailed the title via FedEx, please send us the tracking number and a copy/photo of the title by either:') }}
1. **{{ __('Replying directly to this email') }}** {{ __('with your FedEx tracking number and the attached title copy, OR') }}
2. **{{ __('Sending it to us via WhatsApp') }}** {{ __('using the button below.') }}

<x-mail::button :url="$setting->getWhatsAppUrl('Hi! Here is the title copy and FedEx tracking number for shipment ' . $shipment->reference_no)" color="success">
{{ __('Send Tracking & Copy via WhatsApp') }}
</x-mail::button>

<x-mail::button :url="route('shipments.show', $shipment, absolute: true)">
{{ __('View Shipment Details') }}
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
