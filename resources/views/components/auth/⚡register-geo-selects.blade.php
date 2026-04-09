<?php

use Livewire\Component;

new class extends Component
{
    public ?int $country_id = null;

    public ?int $state_id = null;

    public ?int $city_id = null;

    public function mount(mixed $initialCountryId = null, mixed $initialStateId = null, mixed $initialCityId = null): void
    {
        $this->country_id = $this->toNullableInt($initialCountryId);
        $this->state_id = $this->toNullableInt($initialStateId);
        $this->city_id = $this->toNullableInt($initialCityId);
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

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
};
?>

<div class="space-y-4">
    <div>
        <x-select
            wire:model.live="country_id"
            name="country_id"
            :label="__('Country')"
            :placeholder="__('Select country')"
            option-value="id"
            option-label="name"
            :async-data="['api' => route('register.geo.countries'), 'alwaysFetch' => true]"
            searchable
            required
        />
        <flux:error name="country_id" />
    </div>

    <div>
        <x-select
            wire:model.live="state_id"
            name="state_id"
            :label="__('State / region')"
            :placeholder="__('Select state')"
            option-value="id"
            option-label="name"
            :async-data="['api' => route('register.geo.states'), 'params' => ['country_id' => $country_id ?? ''], 'alwaysFetch' => true]"
            :readonly="! $country_id"
            searchable
            required
        />
        <flux:error name="state_id" />
    </div>

    <div>
        <x-select
            wire:model.live="city_id"
            name="city_id"
            :label="__('City')"
            :placeholder="__('Select city')"
            option-value="id"
            option-label="name"
            :async-data="['api' => route('register.geo.cities'), 'params' => ['state_id' => $state_id ?? ''], 'alwaysFetch' => true]"
            :readonly="! $state_id"
            searchable
            required
        />
        <flux:error name="city_id" />
    </div>
</div>
