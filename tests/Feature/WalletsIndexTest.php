<?php

declare(strict_types=1);

use App\Models\Shipper;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Permission::findOrCreate('wallets.view');
    Role::findByName('super_admin')->givePermissionTo('wallets.view');
});

test('wallets management page search queries both company name and owner name and email without sql error', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipperUser = User::factory()->create([
        'name' => 'Aliyu Mohammed',
        'email' => 'aliyu@example.com',
    ]);
    $shipper = Shipper::factory()->create([
        'user_id' => $shipperUser->id,
        'company_name' => 'Aliyu Logistics',
    ]);
    $wallet = Wallet::factory()->create([
        'shipper_id' => $shipper->id,
        'balance' => 1500.00,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('pages::financials.wallets.index');

    // Search by user name
    $component->set('search', 'Aliyu');
    expect($component->instance()->wallets->items())->toHaveCount(1);
    expect($component->instance()->wallets->items()[0]->id)->toBe($wallet->id);

    // Search by email
    $component->set('search', 'aliyu@example.com');
    expect($component->instance()->wallets->items())->toHaveCount(1);
    expect($component->instance()->wallets->items()[0]->id)->toBe($wallet->id);
});
