<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

test('staff can edit shipper primary contact name, email, and company details in edit modal', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $country = Country::factory()->create(['name' => 'United States']);
    $state = State::factory()->create(['country_id' => $country->id, 'name' => 'Texas']);
    $city = City::factory()->create(['state_id' => $state->id, 'name' => 'Houston']);

    $shipperUser = User::factory()->create([
        'name' => 'Original Contact',
        'email' => 'original@shipper.com',
    ]);
    $shipper = Shipper::factory()->create([
        'user_id' => $shipperUser->id,
        'company_name' => 'Original Cargo LLC',
        'phone' => '+17135550100',
        'address' => '100 Main St',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'discount_amount' => 15.00,
    ]);

    $component = Volt::test('pages::shippers.⚡index')
        ->call('openEditModal', $shipper->id);

    expect($component->get('ownerName'))->toBe('Original Contact')
        ->and($component->get('ownerEmail'))->toBe('original@shipper.com')
        ->and($component->get('company_name'))->toBe('Original Cargo LLC');

    // Update details including name and email
    $component->set('ownerName', 'Updated Contact Name')
        ->set('ownerEmail', 'updated@shipper.com')
        ->set('company_name', 'Prime Global Freight LLC')
        ->set('address', '500 Portway Suite 200')
        ->call('saveShipper')
        ->assertHasNoErrors();

    // Verify DB updates for both user and shipper
    $shipperUser->refresh();
    expect($shipperUser->name)->toBe('Updated Contact Name')
        ->and($shipperUser->email)->toBe('updated@shipper.com');

    $shipper->refresh();
    expect($shipper->company_name)->toBe('Prime Global Freight LLC')
        ->and($shipper->address)->toBe('500 Portway Suite 200');
});
