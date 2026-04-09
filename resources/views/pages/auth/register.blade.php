@php($systemSetting = \App\Models\SystemSetting::current())

<x-layouts::auth.card>
    <div class="flex flex-col gap-8">
        <x-auth-header
            :title="__('Shipper registration')"
            :description="__('Create your company account to manage shipments and bookings.')"
        />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-8">
        @csrf

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Your account') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Name and email you will use to sign in.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input
                    name="name"
                    :label="__('Your name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :value="old('name')"
                    icon:leading="user"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />

                <flux:input
                    name="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autocomplete="username"
                    :value="old('email')"
                    icon:leading="envelope"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Company') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Optional legal or trading name and how we can reach your team.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input
                    name="company_name"
                    :label="__('Company name (optional)')"
                    type="text"
                    :value="old('company_name')"
                    icon:leading="building-office-2"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />

                <flux:input
                    name="phone"
                    :label="__('Phone')"
                    type="tel"
                    required
                    :value="old('phone')"
                    autocomplete="tel"
                    icon:leading="phone"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />

                <flux:input
                    name="address"
                    :label="__('Address')"
                    type="text"
                    required
                    :value="old('address')"
                    autocomplete="street-address"
                    icon:leading="map-pin"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Business location') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Where your company is based.') }}
                </flux:subheading>
            </div>
            <livewire:auth.register-geo-selects
                :initial-country-id="old('country_id')"
                :initial-state-id="old('state_id')"
                :initial-city-id="old('city_id')"
            />
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Security') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Choose a strong password for your account.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                    icon:leading="lock-closed"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />

                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                    icon:leading="key"
                    class="[&_[data-flux-icon]]:text-sky-600 dark:[&_[data-flux-icon]]:text-cyan-300"
                />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <flux:field variant="inline">
            <flux:checkbox name="terms" value="1" :checked="(bool) old('terms')" />
            <flux:label>
                <span class="text-sm">{{ __('I agree to the') }}</span>
                <flux:link :href="route('terms')" class="text-sm">{{ __('terms of service') }}</flux:link>
                <span class="text-sm">{{ __('and') }}</span>
                <flux:link :href="route('privacy')" class="text-sm">{{ __('privacy policy') }}</flux:link>
                <span class="text-sm">.</span>
            </flux:label>
            <flux:error name="terms" />
        </flux:field>

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Create account') }}</flux:button>
        </div>
    </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
            {{ __('Already have an account?') }}
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth.card>
