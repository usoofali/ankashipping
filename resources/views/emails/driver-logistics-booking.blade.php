<x-mail::message>
    @if (!empty($emailLogo))
        <p style="text-align:center; margin-bottom: 24px;">
            <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:80px; width:auto;">
        </p>
    @endif

    # {{ __('New Dock Receipt Available') }}

    {{ __('Hello!') }}

    {{ __('A new Dock Receipt has been generated for shipment :ref.', [
    'ref' => $shipment->reference_no,
]) }}

    {{ __('You can download the document using the button below. This link will expire in 7 days.') }}

    <x-mail::button :url="$signedUrl">
        {{ __('Download Dock Receipt') }}
    </x-mail::button>

    <x-mail::button :url="$setting->getWhatsAppUrl('Hi! I have a question about the logistics for shipment ' . $shipment->reference_no)" color="success">
        {{ __('Chat on WhatsApp') }}
    </x-mail::button>

    **{{ __('Shipment Details') }}**
    - **{{ __('Reference') }}:** {{ $shipment->reference_no }}

    ---
    Thanks,
    **{{ $companyName }}**

    @if (!empty($setting->address) || !empty($setting->phone) || !empty($location))
        <p style="color: #718096; font-size: 0.75rem; line-height: 1.25rem;">
            {{ $setting->address }} {{ $location }}<br>
            {{ $setting->phone }}
        </p>
    @endif
</x-mail::message>