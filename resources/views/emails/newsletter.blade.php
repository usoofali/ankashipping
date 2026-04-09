<x-mail::message>
    @if (! empty($emailLogo))
<p style="text-align:center; margin-bottom: 16px;">
    <img src="{{ $emailLogo }}" alt="{{ $companyName }}" style="max-height:64px; width:auto;">
</p>
@endif
# {{ $title }}

{{ $body }}

@if ($url)
<x-mail::button :url="$url">
{{ __('View Details') }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
