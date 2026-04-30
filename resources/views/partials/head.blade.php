<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title . ' - ' . config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

<wireui:scripts />

@if (file_exists(public_path('build/manifest.json')))
    <link rel="stylesheet" href="{{ asset('build/assets/app-CbgVMOtQ.css') }}">
    <script type="module" src="{{ asset('build/assets/app-COUEiJ29.js') }}"></script>
@else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
@fluxAppearance