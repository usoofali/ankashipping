<?php

use App\Concerns\HandlesShipperGeoSelects;
use App\Concerns\ProfileValidationRules;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use HandlesShipperGeoSelects;
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public string $company_name = '';
    public string $address = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';

        if ($user->hasRole('shipper') && $user->shipper) {
            $this->company_name = (string) ($user->shipper->company_name ?? '');
            $this->address = (string) ($user->shipper->address ?? '');
            $this->country_id = $user->shipper->country_id ? (int) $user->shipper->country_id : null;
            $this->state_id = $user->shipper->state_id ? (int) $user->shipper->state_id : null;
            $this->city_id = $user->shipper->city_id ? (int) $user->shipper->city_id : null;
        }
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = $this->profileRules($user->id);

        if ($user->hasRole('shipper')) {
            $rules['company_name'] = ['nullable', 'string', 'max:255'];
            $rules['address'] = ['nullable', 'string', 'max:500'];
            $rules['country_id'] = ['nullable', 'integer', 'exists:countries,id'];
            $rules['state_id'] = ['nullable', 'integer', 'exists:states,id'];
            $rules['city_id'] = ['nullable', 'integer', 'exists:cities,id'];
        }

        $validated = $this->validate($rules);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Persist phone and business info on related record
        $phone = $validated['phone'] ?? null;

        if ($user->hasRole('shipper')) {
            $shipperData = [
                'phone' => $phone,
                'company_name' => $validated['company_name'] ?? null,
                'address' => $validated['address'] ?? null,
                'country_id' => $validated['country_id'] ?? null,
                'state_id' => $validated['state_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
            ];

            if ($user->shipper) {
                $user->shipper->update($shipperData);
            } else {
                $user->shipper()->create($shipperData);
            }
        } elseif ($user->hasAnyRole(['staff_admin', 'staff_operator', 'whatsapp_agent'])) {
            if ($user->staff) {
                $user->staff->update(['phone' => $phone]);
            } else {
                $user->staff()->create(['phone' => $phone]);
            }
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function countries()
    {
        return Country::query()->orderBy('name')->get();
    }

    #[Computed]
    public function states()
    {
        if ($this->country_id === null) {
            return State::query()->whereRaw('0 = 1')->get();
        }

        return State::query()
            ->where('country_id', $this->country_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function cities()
    {
        if ($this->state_id === null) {
            return City::query()->whereRaw('0 = 1')->get();
        }

        return City::query()
            ->where('state_id', $this->state_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your personal and business details')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:input wire:model="phone" :label="__('Phone (+ country code)')" icon="phone" type="tel" autocomplete="tel" />

            @if (auth()->user()?->hasRole('shipper'))
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-6">
                    <div>
                        <flux:heading size="base" weight="semibold">{{ __('Company & Business Details') }}</flux:heading>
                        <flux:subheading>{{ __('Your business address and location details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="company_name" :label="__('Company Name (Optional)')" type="text" autocomplete="organization" />

                    <flux:input wire:model="address" :label="__('Business Address')" type="text" autocomplete="street-address" />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:select wire:model.live="country_id" :label="__('Country')">
                            <flux:select.option value="">{{ __('Select Country') }}</flux:select.option>
                            @foreach ($this->countries as $country)
                                <flux:select.option :value="$country->id">{{ $country->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="state_id" :label="__('State / Region')">
                            <flux:select.option value="">{{ __('Select State') }}</flux:select.option>
                            @foreach ($this->states as $state)
                                <flux:select.option :value="$state->id">{{ $state->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="city_id" :label="__('City')">
                            <flux:select.option value="">{{ __('Select City') }}</flux:select.option>
                            @foreach ($this->cities as $city)
                                <flux:select.option :value="$city->id">{{ $city->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>