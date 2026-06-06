<?php

declare(strict_types=1);

use App\Mail\Newsletter as NewsletterMail;
use App\Models\Newsletter;
use App\Models\Shipper;
use App\Models\User;
use App\Services\NewsletterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');
});

test('NewsletterService queues emails with staggered delays', function (): void {
    Carbon::setTestNow(now());
    Mail::fake();

    $user1 = User::factory()->create(['email' => 'shipper1@example.com']);
    $user1->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $user1->id]);

    $user2 = User::factory()->create(['email' => 'shipper2@example.com']);
    $user2->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $user2->id]);

    $user3 = User::factory()->create(['email' => 'shipper3@example.com']);
    $user3->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $user3->id]);

    $service = new NewsletterService;
    $queuedCount = $service->sendBulk(
        recipients: collect([$user1, $user2, $user3]),
        title: 'Weekly Update',
        body: 'Here is the weekly update.',
        url: 'https://example.com',
        mailer: 'newsletter'
    );

    expect($queuedCount)->toBe(3);

    // Verify first recipient (delay 0s)
    Mail::assertQueued(NewsletterMail::class, function ($mail) use ($user1): bool {
        return $mail->hasTo($user1->email) && $mail->delay && $mail->delay->timestamp === now()->timestamp;
    });

    // Verify second recipient (delay 5s)
    Mail::assertQueued(NewsletterMail::class, function ($mail) use ($user2): bool {
        return $mail->hasTo($user2->email) && $mail->delay && $mail->delay->timestamp === now()->addSeconds(5)->timestamp;
    });

    // Verify third recipient (delay 10s)
    Mail::assertQueued(NewsletterMail::class, function ($mail) use ($user3): bool {
        return $mail->hasTo($user3->email) && $mail->delay && $mail->delay->timestamp === now()->addSeconds(10)->timestamp;
    });

    Carbon::setTestNow();
});

test('Volt component triggers NewsletterService sending and updates sent_at', function (): void {
    Carbon::setTestNow(now());
    Mail::fake();

    $adminUser = User::factory()->create();
    $adminUser->assignRole('super_admin');

    $user1 = User::factory()->create(['email' => 'shipper1@example.com']);
    $user1->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $user1->id]);

    $newsletter = Newsletter::create([
        'subject' => 'Draft Subject',
        'body' => 'Draft Body',
        'url' => 'https://example.com/draft',
        'mailer' => 'newsletter',
    ]);

    $this->actingAs($adminUser);

    Volt::test('pages::newsletters.⚡index')
        ->call('send', $newsletter->id)
        ->assertHasNoErrors()
        ->assertDispatched('wireui:notification');

    $newsletter->refresh();
    expect($newsletter->sent_at)->not->toBeNull()
        ->and($newsletter->recipients_count)->toBe(1);

    Mail::assertQueued(NewsletterMail::class, function ($mail) use ($user1): bool {
        return $mail->hasTo($user1->email);
    });

    Carbon::setTestNow();
});
