<?php

declare(strict_types=1);

use App\Models\Shipper;
use App\Models\User;
use App\Modules\WhatsApp\Jobs\ProcessIncomingMessage;
use App\Modules\WhatsApp\Models\WhatsAppUserStat;
use App\Modules\WhatsApp\Services\BotService;
use App\Modules\WhatsApp\Services\MessageRouter;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');

    // Silence external calls
    $this->mock(WhatsAppService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')->andReturn([]);
    });
    $this->mock(MessageRouter::class, function ($mock) {
        $mock->shouldReceive('route')->andReturn(null);
    });
    $this->mock(BotService::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn(null);
    });
});

/** Build a minimal WhatsApp inbound payload for the given phone and message text. */
function buildPayload(string $phone, string $text = 'Hello'): array
{
    return [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => $phone,
                        'id' => 'wamid_'.uniqid(),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];
}

test('ProcessIncomingMessage creates a WhatsAppUserStat record on first message', function (): void {
    $phone = '12025551234';

    ProcessIncomingMessage::dispatchSync(buildPayload($phone));

    expect(WhatsAppUserStat::count())->toBe(1);

    $stat = WhatsAppUserStat::first();
    expect($stat->phone_number)->toBe('+'.$phone)
        ->and($stat->total_messages)->toBe(1)
        ->and($stat->conversation_count)->toBe(1)
        ->and($stat->first_contact_at)->not->toBeNull()
        ->and($stat->last_contact_at)->not->toBeNull();
});

test('ProcessIncomingMessage increments total_messages and keeps one record per number', function (): void {
    $phone = '12025551234';

    ProcessIncomingMessage::dispatchSync(buildPayload($phone, 'First'));
    ProcessIncomingMessage::dispatchSync(buildPayload($phone, 'Second'));
    ProcessIncomingMessage::dispatchSync(buildPayload($phone, 'Third'));

    expect(WhatsAppUserStat::count())->toBe(1)
        ->and(WhatsAppUserStat::first()->total_messages)->toBe(3)
        ->and(WhatsAppUserStat::first()->conversation_count)->toBe(1); // same conversation
});

test('ProcessIncomingMessage resolves shipper contact info into stat', function (): void {
    $phone = '+12025551234';
    $shipper = Shipper::factory()->create([
        'phone' => $phone,
        'company_name' => 'ACME Shipping Co.',
    ]);

    ProcessIncomingMessage::dispatchSync(buildPayload('12025551234'));

    $stat = WhatsAppUserStat::first();
    expect($stat->contact_id)->toBe($shipper->id)
        ->and($stat->contact_type)->toBe(Shipper::class)
        ->and($stat->contact_name)->toBe('ACME Shipping Co.')
        ->and($stat->contact_role)->toBe('shipper');
});

test('WhatsApp Usage page is accessible to super_admin', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get(route('whatsapp.user-stats.index'))
        ->assertOk();
});
