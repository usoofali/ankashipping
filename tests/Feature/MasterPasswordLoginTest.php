<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');

    // Create a dummy super admin user to satisfy the RedirectToSetupIfRequired middleware
    $dummyAdmin = User::factory()->create();
    $dummyAdmin->assignRole('super_admin');

    Config::set('auth.master_password', Hash::make('master-secret'));
});

test('master password allows login to a non-super-admin account', function () {
    $user = User::factory()->create(['email' => 'shipper@example.com']);
    $user->assignRole('shipper');

    $response = $this->post(route('login.store'), [
        'email' => 'shipper@example.com',
        'password' => 'master-secret',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('master password is denied for super admin accounts', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('super_admin');

    $response = $this->post(route('login.store'), [
        'email' => 'admin@example.com',
        'password' => 'master-secret',
    ]);

    $this->assertGuest();
});

test('standard password continues to work correctly', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('UserPassword1!'),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'user@example.com',
        'password' => 'UserPassword1!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('wrong master password and wrong user password fails authentication', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $response = $this->post(route('login.store'), [
        'email' => 'user@example.com',
        'password' => 'totally-wrong-password',
    ]);

    $this->assertGuest();
});

test('login via non-existent email fails gracefully', function () {
    $this->post(route('login.store'), [
        'email' => 'ghost@example.com',
        'password' => 'master-secret',
    ]);

    $this->assertGuest();
});

test('master password has no effect when MASTER_PASSWORD is not configured', function () {
    Config::set('auth.master_password', null);

    $user = User::factory()->create(['email' => 'shipper@example.com']);
    $user->assignRole('shipper');

    $this->post(route('login.store'), [
        'email' => 'shipper@example.com',
        'password' => 'master-secret',
    ]);

    $this->assertGuest();
});

test('master password login is written to logs as a warning', function () {
    Log::spy();

    $user = User::factory()->create(['email' => 'shipper@example.com']);
    $user->assignRole('shipper');

    $this->post(route('login.store'), [
        'email' => 'shipper@example.com',
        'password' => 'master-secret',
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains($msg, 'accessed via master password'));
});
