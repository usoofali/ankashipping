@props([
    'name',
    'label',
    'value' => '',
    'required' => false,
])

<!-- Load CSS and JS natively to guarantee they work without build issues -->
@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@27.1.3/dist/css/intlTelInput.css">
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@27.1.3/dist/js/intlTelInput.min.js"></script>
@endonce

<flux:field x-data="{
    init() {
        const input = this.$refs.input;
        const initialize = () => {
            if (!window.intlTelInput) {
                setTimeout(initialize, 50);
                return;
            }
            this.iti = window.intlTelInput(input, {
                initialCountry: 'auto',
                geoIpLookup: callback => {
                    fetch('https://ipapi.co/json')
                        .then(res => res.json())
                        .then(data => callback(data.country_code))
                        .catch(() => callback('us'));
                },
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@27.1.3/dist/js/utils.js',
                hiddenInput: () => '{{ $name }}',
            });
            
            if ('{{ $value }}') {
                this.iti.setNumber('{{ $value }}');
            }
        };
        initialize();
    }
}" class="w-full" wire:ignore>
    <flux:label :badge="$required ? '*' : null">{{ $label }}</flux:label>

    <div class="mt-2 w-full" wire:ignore>
        <input 
            x-ref="input"
            type="tel"
            name="{{ $name }}"
            value="{{ $value }}"
            autocomplete="tel"
            {{ $attributes->merge(['class' => 'block w-full rounded-md border-0 py-2 pl-[52px] text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-accent sm:text-sm sm:leading-6 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700 dark:focus:ring-accent']) }}
            @if($required) required @endif
        />
    </div>

    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
        {{ __('Format: Select country code and enter number (e.g. +234 801 234 5678)') }}
    </div>

    <flux:error :name="$name" />
</flux:field>

<style>
    .iti { width: 100%; display: block; }
    .iti__country-list { z-index: 50; }
    /* Dark mode adjustments for intl-tel-input */
    .dark .iti__country-list { background-color: var(--color-zinc-800); color: var(--color-zinc-200); border-color: var(--color-zinc-700); }
    .dark .iti__country.iti__highlight { background-color: var(--color-zinc-700); }
    .dark .iti__selected-dial-code { color: var(--color-zinc-300); }
    .dark .iti__selected-country { background-color: var(--color-zinc-900); }
    .dark .iti__search-input { background-color: var(--color-zinc-900); border-color: var(--color-zinc-700); color: var(--color-zinc-200); }
</style>
