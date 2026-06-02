<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('getMailerFor resolves correctly for hostinger', function () {
    $setting = SystemSetting::factory()->create([
        'preferred_mailer' => 'hostinger',
    ]);

    expect($setting->getMailerFor('operations'))->toBe('operations');
    expect($setting->getMailerFor('booking'))->toBe('booking');
    expect($setting->getMailerFor('services'))->toBe('services');
    expect($setting->getMailerFor('accounts'))->toBe('accounts');
    expect($setting->getMailerFor('noreply'))->toBe('noreply');

    // Mock cache increment for newsletter roundrobin
    Cache::shouldReceive('increment')
        ->once()
        ->with('newsletter_mailer_index')
        ->andReturn(1);

    expect($setting->getMailerFor('newsletter'))->toBe('news1'); // (1 - 1) % 3 is 0 (news1)
});

test('getMailerFor resolves correctly for google', function () {
    $setting = SystemSetting::factory()->create([
        'preferred_mailer' => 'google',
    ]);

    expect($setting->getMailerFor('operations'))->toBe('google_operations');
    expect($setting->getMailerFor('services'))->toBe('google_operations');
    expect($setting->getMailerFor('booking'))->toBe('google_booking');
    expect($setting->getMailerFor('newsletter'))->toBe('google_newsletter');
});

test('getMailerFor resolves correctly for zoho', function () {
    $setting = SystemSetting::factory()->create([
        'preferred_mailer' => 'zoho',
    ]);

    expect($setting->getMailerFor('operations'))->toBe('zoho_operations');
    expect($setting->getMailerFor('booking'))->toBe('zoho_booking');
    expect($setting->getMailerFor('services'))->toBe('zoho_services');
    expect($setting->getMailerFor('accounts'))->toBe('zoho_accounts');
    expect($setting->getMailerFor('noreply'))->toBe('zoho_noreply');

    Cache::shouldReceive('increment')
        ->once()
        ->with('newsletter_mailer_index')
        ->andReturn(2);

    expect($setting->getMailerFor('newsletter'))->toBe('zoho_news2'); // (2 - 1) % 3 is 1 (zoho_news2)
});

test('preferred mailer can be updated to zoho via system settings page', function () {
    $role = Role::findOrCreate('super_admin');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    // Initial system setting in DB starts as null/default
    $setting = SystemSetting::current();
    expect($setting->preferred_mailer)->toBeNull();

    // Update via Volt component
    Volt::test('pages::settings.system-setting')
        ->set('preferred_mailer', 'zoho')
        ->call('save')
        ->assertHasNoErrors();

    expect($setting->fresh()->preferred_mailer)->toBe('zoho');
});
