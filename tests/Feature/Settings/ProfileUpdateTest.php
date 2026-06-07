<?php

use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('shipper user can update profile including phone', function () {
    // Seed role if not present
    Role::firstOrCreate(['name' => 'shipper', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id, 'phone' => '+12025550143']);

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->set('phone', '+12025550199')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toEqual('New Name');
    expect($user->email)->toEqual('new@example.com');
    expect($user->phone)->toEqual('+12025550199');
});

test('staff user can update profile including phone', function () {
    // Seed role if not present
    Role::firstOrCreate(['name' => 'staff_operator', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    $staff = Staff::factory()->create(['user_id' => $user->id, 'phone' => '+12025550143']);

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'New Staff Name')
        ->set('email', 'staff@example.com')
        ->set('phone', '+12025550188')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toEqual('New Staff Name');
    expect($user->email)->toEqual('staff@example.com');
    expect($user->phone)->toEqual('+12025550188');
});

test('shipper phone must be unique among shippers', function () {
    Role::firstOrCreate(['name' => 'shipper', 'guard_name' => 'web']);

    // Create first shipper
    $otherUser = User::factory()->create();
    $otherUser->assignRole('shipper');
    $otherShipper = Shipper::factory()->create(['user_id' => $otherUser->id, 'phone' => '+12025550111']);

    // Create current shipper
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id, 'phone' => '+12025550222']);

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('phone', '+12025550111') // Taken
        ->call('updateProfileInformation');

    $response->assertHasErrors(['phone']);
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('phone number must start with a plus sign', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('phone', '12025550199') // Missing plus sign
        ->call('updateProfileInformation');

    $response->assertHasErrors(['phone']);
});
