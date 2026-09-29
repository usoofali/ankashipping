<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Models\City;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Role::findOrCreate('shipper');
});

test('shipper display_name returns company_name when present', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);
    $shipper = Shipper::factory()->create([
        'user_id' => $user->id,
        'company_name' => 'Acme Shipping Ltd',
    ]);

    expect($shipper->display_name)->toBe('Acme Shipping Ltd')
        ->and($shipper->name)->toBe('Acme Shipping Ltd');
});

test('shipper display_name falls back to user name when company_name is null', function (): void {
    $user = User::factory()->create(['name' => 'Musa bako']);
    $shipper = Shipper::factory()->create([
        'user_id' => $user->id,
        'company_name' => null,
    ]);

    expect($shipper->display_name)->toBe('Musa bako')
        ->and($shipper->name)->toBe('Musa bako');
});

test('shipper mutates undefined or empty company_name to null and falls back to user name', function (): void {
    $user = User::factory()->create(['name' => 'Dankullu Global Links Ltd']);
    $shipper = Shipper::factory()->create([
        'user_id' => $user->id,
        'company_name' => 'UNDEFINED',
    ]);

    expect($shipper->company_name)->toBeNull()
        ->and($shipper->display_name)->toBe('Dankullu Global Links Ltd');
});

test('create new user action sanitizes empty or undefined company_name to null', function (): void {
    $country = Country::factory()->create(['iso2' => 'US']);
    $state = State::factory()->create(['country_id' => $country->id, 'code' => 'TX']);
    $city = City::factory()->create(['state_id' => $state->id, 'name' => 'Houston']);

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'S AND G AUTO MOBILE SERVICE LTD',
        'email' => 'sandg@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => true,
        'company_name' => 'undefined',
        'phone' => '+12025550143',
        'address' => '123 Test St',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);

    $shipper = $user->shipper;
    expect($shipper)->not->toBeNull()
        ->and($shipper->company_name)->toBeNull()
        ->and($shipper->display_name)->toBe('S AND G AUTO MOBILE SERVICE LTD');
});
