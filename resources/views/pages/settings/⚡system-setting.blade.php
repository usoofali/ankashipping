<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WireUi\Traits\WireUiActions;

new #[Title('System settings')] class extends Component {
    use WireUiActions, WithFileUploads;

    public string $company_name = '';
    public ?string $logo_path = null;
    public TemporaryUploadedFile|null $logo_file = null;
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $zipcode = '';
    public ?int $country_id = null;
    public ?int $state_id = null;
    public ?int $city_id = null;
    public ?string $auction_api_key = null;
    public ?string $whatsapp_api_key = null;
    public string $tracking_delivery_prefix = 'MRF';
    public int $tracking_digits = 5;
    public string $tracking_number_type = 'auto_increment';
    public int $tracking_random_digits = 10;
    public string $preferred_mailer = 'hostinger';


    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin') === true, 403);

        $setting = SystemSetting::current();
        $this->company_name = $setting->company_name ?? '';
        $this->logo_path = $setting->logo_path;
        $this->address = $setting->address ?? '';
        $this->phone = $setting->phone ?? '';
        $this->email = $setting->email ?? '';
        $this->zipcode = $setting->zipcode ?? '';
        $this->country_id = $setting->country_id;
        $this->state_id = $setting->state_id;
        $this->city_id = $setting->city_id;
        $this->auction_api_key = $setting->auction_api_key;
        $this->whatsapp_api_key = $setting->whatsapp_api_key;
        $this->tracking_delivery_prefix = $setting->tracking_delivery_prefix ?? 'MRF';
        $this->tracking_digits = $setting->tracking_digits ?? 5;
        $this->tracking_number_type = $setting->tracking_number_type ?? 'auto_increment';
        $this->tracking_random_digits = $setting->tracking_random_digits ?? 10;
        $this->preferred_mailer = $setting->preferred_mailer ?? 'hostinger';
        if ($this->preferred_mailer === 'failover') {
            $this->preferred_mailer = 'hostinger';
        }

    }


    public function updatedCountryId(): void
    {
        $this->state_id = null;
        $this->city_id = null;
    }

    public function updatedStateId(): void
    {
        $this->city_id = null;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin') === true, 403);

        $validated = $this->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'auction_api_key' => ['nullable', 'string'],
            'whatsapp_api_key' => ['nullable', 'string'],
            'tracking_delivery_prefix' => ['required', 'string', 'max:20'],
            'tracking_digits' => ['required', 'integer', 'min:1', 'max:12'],
            'tracking_number_type' => ['required', 'in:auto_increment,random'],
            'tracking_random_digits' => ['required', 'integer', 'min:1', 'max:20'],
            'preferred_mailer' => ['required', 'string', 'in:hostinger,google'],

        ]);


        $setting = SystemSetting::current();
        $previousLogoPath = $setting->logo_path;

        if ($this->logo_file instanceof TemporaryUploadedFile) {
            $storedPath = $this->logo_file->store('system/logo', 'public');

            $validated['logo_path'] = $storedPath;
            $validated['logo'] = null;

            if (
                is_string($previousLogoPath)
                && $previousLogoPath !== ''
                && $previousLogoPath !== $storedPath
                && Storage::disk('public')->exists($previousLogoPath)
            ) {
                Storage::disk('public')->delete($previousLogoPath);
            }

            $this->logo_file = null;
        } else {
            $validated['logo_path'] = $this->logo_path;
        }

        $setting->update($validated);
        $this->logo_path = $setting->fresh()?->logo_path;

        $this->dialog()->show([
            'icon' => 'success',
            'title' => 'Saved!',
            'description' => 'System settings updated successfully.',
            'width' => 'sm'
        ]);

        // $this->dispatch('saved');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Country>
     */
    #[Computed]
    public function countries()
    {
        return Country::query()->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, State>
     */
    #[Computed]
    public function states()
    {
        return State::query()
            ->when($this->country_id, fn ($query) => $query->where('country_id', $this->country_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, City>
     */
    #[Computed]
    public function cities()
    {
        return City::query()
            ->when($this->state_id, fn ($query) => $query->where('state_id', $this->state_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        if ($this->logo_file instanceof TemporaryUploadedFile) {
            return $this->logo_file->temporaryUrl();
        }

        if (is_string($this->logo_path) && trim($this->logo_path) !== '') {
            return Storage::url(trim($this->logo_path));
        }

        return null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('System settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('System settings')" :subheading="__('Manage platform-wide business and tracking configuration')">
        <form wire:submit="save" class="my-6 w-full max-w-3xl space-y-8">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:input wire:model="company_name" :label="__('Company name')" />
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
                <flux:input wire:model="zipcode" :label="__('Zip code')" />
                <flux:input wire:model="logo_file" type="file" accept="image/*" :label="__('Company logo file')" />
            </div>

            @if ($this->logoPreviewUrl)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="mb-2 text-sm">{{ __('Logo preview') }}</flux:text>
                    <img src="{{ $this->logoPreviewUrl }}" alt="{{ __('Company logo preview') }}" class="max-h-20 w-auto rounded object-contain">
                    @if ($logo_path)
                        <flux:text class="mt-2 text-xs text-zinc-500">{{ __('Stored path: :path', ['path' => $logo_path]) }}</flux:text>
                    @endif
                </div>
            @endif

            <flux:textarea wire:model="address" :label="__('Address')" rows="3" />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <flux:select wire:model.live="country_id" :label="__('Country')">
                    <option value="">{{ __('Select country') }}</option>
                    @foreach ($this->countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="state_id" :label="__('State')" :disabled="! $country_id">
                    <option value="">{{ __('Select state') }}</option>
                    @foreach ($this->states as $state)
                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="city_id" :label="__('City')" :disabled="! $state_id">
                    <option value="">{{ __('Select city') }}</option>
                    @foreach ($this->cities as $city)
                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:input wire:model="auction_api_key" :label="__('Auction API key')" />
                <flux:input wire:model="whatsapp_api_key" :label="__('WhatsApp API key')" />
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Tracking and billing information') }}</flux:heading>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:input wire:model="tracking_delivery_prefix" :label="__('Delivery Prefix')" />
                    <flux:input wire:model="tracking_digits" type="number" min="1" max="12" :label="__('Number of digits in the trace')" />
                    <flux:radio.group
                        wire:model="tracking_number_type"
                        :label="__('Tracking number type')"
                        :description="__('Select one option: Auto increment or Random.')"
                        class="md:col-span-2"
                    >
                        <flux:radio
                            value="auto_increment"
                            :label="__('Auto increment')"
                            :description="__('Uses sequential numbers.')"
                        />
                        <flux:radio
                            value="random"
                            :label="__('Random')"
                            :description="__('Uses random numbers with configured random digits.')"
                        />
                    </flux:radio.group>
                    <flux:input wire:model="tracking_random_digits" type="number" min="1" max="20" :label="__('Number of random digits')" />
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Mail Provider') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500">{{ __('Choose which provider delivers all system emails. Hostinger uses dedicated purpose-specific accounts (operations@, services@, etc.). Google routes everything through your Google Workspace account.') }}</flux:subheading>
                <div class="mt-4">
                    <flux:radio.group
                        wire:model="preferred_mailer"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <flux:radio
                            value="hostinger"
                            :label="__('Hostinger SMTP (Default)')"
                            :description="__('Uses dedicated purpose-specific accounts: operations@, services@, news1/2/3@ etc.')"
                        />
                        <flux:radio
                            value="google"
                            :label="__('Google Workspace')"
                            :description="__('All emails use your Google Workspace account via smtp.gmail.com.')"
                        />
                    </flux:radio.group>
                </div>
            </div>


            <div class="flex items-center gap-3">

                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
