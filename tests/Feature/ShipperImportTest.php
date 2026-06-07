<?php

use App\Models\City;
use App\Models\Consignee;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\State;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup super admin role and user
    $adminRole = Role::findOrCreate('super_admin');
    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole($adminRole);

    // Setup geo references
    $this->country = Country::factory()->create([
        'iso2' => 'US',
    ]);

    $this->state = State::factory()->create([
        'country_id' => $this->country->id,
        'code' => 'CA',
    ]);

    $this->city = City::factory()->create([
        'state_id' => $this->state->id,
        'name' => 'Los Angeles',
    ]);

    Role::findOrCreate('shipper');
});

test('can import shippers with new headers and optional fields', function () {
    $this->actingAs($this->adminUser);

    $csvContent = "name,email,password,company_name,phone,address,country_iso2,state_code,city_name,discount_amount\n".
        "Jane Shipper,jane.shipper@example.com,Password123!,Acme Logistics Inc,+15551234567,123 Harbor Way,US,CA,Los Angeles,15.50\n".
        "John Carrier,john.carrier@example.com,Password123!,Coastal Freight LLC,+15559876543,456 Dock St,US,CA,Los Angeles,\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csvContent);

    Volt::test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors()
        ->assertSet('showImportModal', false);

    // Verify Jane Shipper was created correctly with discount amount
    $janeUser = User::where('email', 'jane.shipper@example.com')->first();
    expect($janeUser)->not->toBeNull()
        ->and($janeUser->name)->toBe('Jane Shipper')
        ->and($janeUser->hasRole('shipper'))->toBeTrue();

    $janeShipper = Shipper::where('user_id', $janeUser->id)->first();
    expect($janeShipper)->not->toBeNull()
        ->and($janeShipper->company_name)->toBe('Acme Logistics Inc')
        ->and($janeShipper->phone)->toBe('+15551234567')
        ->and($janeShipper->discount_amount)->toBe('15.50')
        ->and((int) $janeShipper->country_id)->toBe($this->country->id)
        ->and((int) $janeShipper->state_id)->toBe($this->state->id)
        ->and((int) $janeShipper->city_id)->toBe($this->city->id);

    // Verify Wallet was created
    $janeWallet = Wallet::where('shipper_id', $janeShipper->id)->first();
    expect($janeWallet)->not->toBeNull();

    // Verify default Consignee was created and location propagated
    $janeConsignee = Consignee::where('shipper_id', $janeShipper->id)->where('is_default', true)->first();
    expect($janeConsignee)->not->toBeNull()
        ->and($janeConsignee->name)->toBe('Jane Shipper')
        ->and($janeConsignee->address)->toBe('123 Harbor Way')
        ->and((int) $janeConsignee->country_id)->toBe($this->country->id)
        ->and((int) $janeConsignee->state_id)->toBe($this->state->id);

    // Verify John Carrier was created correctly with default discount amount (0.00)
    $johnUser = User::where('email', 'john.carrier@example.com')->first();
    expect($johnUser)->not->toBeNull();

    $johnShipper = Shipper::where('user_id', $johnUser->id)->first();
    expect($johnShipper)->not->toBeNull()
        ->and($johnShipper->company_name)->toBe('Coastal Freight LLC')
        ->and($johnShipper->discount_amount)->toBe('0.00');
});

test('existing user updates both user and shipper records in a transaction', function () {
    $this->actingAs($this->adminUser);

    // Create an existing user with associated shipper
    $existingUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'existing.shipper@example.com',
    ]);
    $existingUser->assignRole('shipper');

    $existingShipper = Shipper::factory()->create([
        'user_id' => $existingUser->id,
        'company_name' => 'Original Company',
        'phone' => '+1111111111',
        'country_id' => $this->country->id,
        'state_id' => $this->state->id,
        'city_id' => $this->city->id,
    ]);

    $csvContent = "name,email,password,company_name,phone,address,country_iso2,state_code,city_name,discount_amount\n".
        "Updated Name,existing.shipper@example.com,NewPassword123!,Updated Company,+2222222222,456 Updated St,US,CA,Los Angeles,25.00\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csvContent);

    Volt::test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    // Verify user details updated
    $existingUser->refresh();
    expect($existingUser->name)->toBe('Updated Name')
        ->and(Hash::check('NewPassword123!', $existingUser->password))->toBeTrue();

    // Verify shipper details updated
    $existingShipper->refresh();
    expect($existingShipper->company_name)->toBe('Updated Company')
        ->and($existingShipper->phone)->toBe('+2222222222')
        ->and($existingShipper->address)->toBe('456 Updated St')
        ->and($existingShipper->discount_amount)->toBe('25.00');
});

test('prevents staff to shipper cross role pollution', function () {
    $this->actingAs($this->adminUser);

    // Create an existing user with associated Staff record
    $staffUser = User::factory()->create([
        'name' => 'Staff Member',
        'email' => 'staff@example.com',
    ]);
    Staff::factory()->create([
        'user_id' => $staffUser->id,
    ]);

    $csvContent = "name,email,password,company_name,phone,address,country_iso2,state_code,city_name,discount_amount\n".
        "Staff Member,staff@example.com,Password123!,Acme Staff,+1555000000,123 Staff Way,US,CA,Los Angeles,0.00\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csvContent);

    // Run import, it should register 1 error and skip creation
    Volt::test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    // Verify no Shipper profile was created for this user
    expect(Shipper::where('user_id', $staffUser->id)->exists())->toBeFalse();

    // Verify user role was not changed/added
    $staffUser->refresh();
    expect($staffUser->hasRole('shipper'))->toBeFalse();
});

test('can download shipper import template', function () {
    $this->actingAs($this->adminUser);

    $response = $this->get(route('import-templates.geo', 'shippers'));

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

test('can import shippers with pre hashed bcrypt passwords directly', function () {
    $this->actingAs($this->adminUser);

    // standard bcrypt hash for 'Password123!'
    $bcryptHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    $csvContent = "name,email,password,company_name,phone,address,country_iso2,state_code,city_name,discount_amount\n".
        "Hashed Shipper,hashed.shipper@example.com,{$bcryptHash},Acme Hashed LLC,+15559998888,789 Hash Way,US,CA,Los Angeles,5.00\n";

    $file = UploadedFile::fake()->createWithContent('shippers.csv', $csvContent);

    Volt::test('pages::shippers.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    // Verify user was created with the EXACT bcrypt hash without double-hashing
    $user = User::where('email', 'hashed.shipper@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->password)->toBe($bcryptHash);
});

test('can export shippers to csv with Shipper, Company, Email, and Phone columns', function () {
    $this->actingAs($this->adminUser);

    // Create a shipper to export
    $shipperUser = User::factory()->create([
        'name' => 'Alice Exporter',
        'email' => 'alice@example.com',
    ]);
    $shipperUser->assignRole('shipper');
    Shipper::factory()->create([
        'user_id' => $shipperUser->id,
        'company_name' => 'Alice Export Company',
        'phone' => '+18889990000',
        'country_id' => $this->country->id,
        'state_id' => $this->state->id,
        'city_id' => $this->city->id,
    ]);

    $response = Volt::test('pages::shippers.index')
        ->call('exportCsv');

    $response->assertHasNoErrors();

    // Verify it returns a StreamedResponse
    $streamedResponse = $response->effects['download'] ?? null;
    expect($streamedResponse)->not->toBeNull();

    $filename = $streamedResponse['name'] ?? '';
    expect($filename)->toContain('shippers_export_');

    // Retrieve stream contents
    ob_start();
    $response->instance()->exportCsv()->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Shipper,Company,Email,Phone')
        ->and($content)->toContain('"Alice Exporter","Alice Export Company","alice@example.com","+18889990000"');
});
