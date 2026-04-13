<?php

use App\Models\DefaultShipmentSetting;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Ensure permissions exist
    Permission::findOrCreate('default_shipment_settings.view');
    Permission::findOrCreate('default_shipment_settings.update');
});

test('default shipment settings can be updated and are loaded correctly', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['default_shipment_settings.view', 'default_shipment_settings.update']);
    $this->actingAs($user);

    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Wire Transfer', 'slug' => 'wire-transfer']);

    // Initially, payment_method_id should be null or default
    $setting = DefaultShipmentSetting::current();
    expect($setting->payment_method_id)->toBeNull();

    // Test saving via Volt component
    $response = Volt::test('pages::default-shipment-settings.index')
        ->set('payment_method_id', $paymentMethod->id)
        ->call('save');

    $response->assertHasNoErrors();

    // Verify database update
    $setting->refresh();
    expect($setting->payment_method_id)->toBe($paymentMethod->id);

    // Verify component state on reload (this tests the mount() fix)
    $response = Volt::test('pages::default-shipment-settings.index');
    $response->assertSet('payment_method_id', $paymentMethod->id);
});
