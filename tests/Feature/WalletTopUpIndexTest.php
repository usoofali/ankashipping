<?php

declare(strict_types=1);

use App\Models\Shipper;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');
});

test('admin can see all top ups', function (): void {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('super_admin');

    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();

    $wallet1 = Wallet::factory()->create(['shipper_id' => $shipper1->id]);
    $wallet2 = Wallet::factory()->create(['shipper_id' => $shipper2->id]);

    $topUp1 = WalletTopUp::create([
        'wallet_id' => $wallet1->id,
        'shipper_id' => $shipper1->id,
        'amount' => 100,
        'receipt_path' => 'receipts/1.jpg',
        'status' => 'pending',
    ]);

    $topUp2 = WalletTopUp::create([
        'wallet_id' => $wallet2->id,
        'shipper_id' => $shipper2->id,
        'amount' => 200,
        'receipt_path' => 'receipts/2.jpg',
        'status' => 'pending',
    ]);

    $this->actingAs($adminUser);

    $component = Livewire::test('pages::financials.top-ups.index');

    $topUps = $component->instance()->topUps();
    $topUpIds = collect($topUps->items())->pluck('id');

    expect($topUpIds)->toContain($topUp1->id)
        ->toContain($topUp2->id);
});

test('shipper can only see their own top ups and not other shippers top ups', function (): void {
    $user1 = User::factory()->create();
    $user1->assignRole('shipper');
    $shipper1 = Shipper::factory()->create(['user_id' => $user1->id]);
    $wallet1 = Wallet::factory()->create(['shipper_id' => $shipper1->id]);

    $user2 = User::factory()->create();
    $user2->assignRole('shipper');
    $shipper2 = Shipper::factory()->create(['user_id' => $user2->id]);
    $wallet2 = Wallet::factory()->create(['shipper_id' => $shipper2->id]);

    $topUp1 = WalletTopUp::create([
        'wallet_id' => $wallet1->id,
        'shipper_id' => $shipper1->id,
        'amount' => 100,
        'receipt_path' => 'receipts/1.jpg',
        'status' => 'pending',
    ]);

    $topUp2 = WalletTopUp::create([
        'wallet_id' => $wallet2->id,
        'shipper_id' => $shipper2->id,
        'amount' => 200,
        'receipt_path' => 'receipts/2.jpg',
        'status' => 'pending',
    ]);

    $this->actingAs($user1);

    $component = Livewire::test('pages::financials.top-ups.index');

    $topUps = $component->instance()->topUps();
    $topUpIds = collect($topUps->items())->pluck('id');

    expect($topUpIds)->toContain($topUp1->id)
        ->not->toContain($topUp2->id);
});
