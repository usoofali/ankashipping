<?php

namespace App\Providers;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerNotificationEmailLogging();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerNotificationEmailLogging(): void
    {
        // Listen to ALL outgoing mail (Notifications AND Mailables)
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $message = $event->message;
            $recipients = $message->getTo();
            $recipientEmail = count($recipients) > 0 ? $recipients[0]->getAddress() : 'unknown';

            // Store the intent
            $log = EmailLog::query()->create([
                'mailable_class' => $event->data['__laravel_notification'] ?? 'Mailable',
                'recipient_email' => $recipientEmail,
                'subject' => $message->getSubject(),
                'body' => $message->getHtmlBody() ?: $message->getTextBody(),
                'status' => EmailLogStatus::Pending,
                'meta' => [
                    'mailer' => config('mail.default'),
                ],
            ]);

            // Store the ID in the message so we can find it in MessageSent
            $message->getHeaders()->addTextHeader('X-Email-Log-ID', (string) $log->id);
        });

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $message = $event->message;
            $logId = $message->getHeaders()->get('X-Email-Log-ID')?->getBody();

            if ($logId) {
                EmailLog::query()->whereKey($logId)->update([
                    'status' => EmailLogStatus::Sent,
                ]);
            }
        });

        // Still listen to NotificationFailed for detailed error capture in notifications
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->channel !== 'mail') {
                return;
            }

            $recipientEmail = $event->notifiable->email ?? null;
            if (! is_string($recipientEmail) || $recipientEmail === '') {
                return;
            }

            $errorMessage = is_string($event->data['message'] ?? null)
                ? $event->data['message']
                : (is_string($event->data['error'] ?? null) ? $event->data['error'] : null);

            // Find the pending log if it exists (via recipient and class)
            $emailLog = EmailLog::query()
                ->where('recipient_email', $recipientEmail)
                ->where('mailable_class', $event->notification::class)
                ->where('status', EmailLogStatus::Pending)
                ->latest()
                ->first();

            if ($emailLog) {
                $emailLog->update(['status' => EmailLogStatus::Failed]);
            } else {
                $emailLog = EmailLog::query()->create([
                    'mailable_class' => $event->notification::class,
                    'recipient_email' => $recipientEmail,
                    'status' => EmailLogStatus::Failed,
                ]);
            }

            $emailLog->attempts()->create([
                'exception_message' => $errorMessage,
                'smtp_response' => $errorMessage,
                'attempted_at' => now(),
            ]);
        });
    }
}
