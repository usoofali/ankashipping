<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Newsletter as NewsletterMail;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class NewsletterService
{
    /** Seconds of delay added per recipient to stagger SMTP delivery. */
    private const DELAY_STEP_SECONDS = 5;

    /**
     * Queue newsletter emails for all given recipients with staggered delays.
     *
     * Each subsequent recipient is delayed by an additional DELAY_STEP_SECONDS
     * so that Zoho SMTP never receives a burst of concurrent connections.
     *
     * @return int Number of queued emails
     */
    public function sendBulk(iterable $recipients, string $title, string $body, ?string $url = null, string $mailer = 'newsletter'): int
    {
        $resolvedMailer = SystemSetting::current()->getMailerFor($mailer);
        $delay = 0;
        $queued = 0;

        foreach ($recipients as $recipient) {
            $email = $recipient instanceof User ? $recipient->email : $recipient;
            if (empty($email)) {
                continue;
            }

            $mailable = (new NewsletterMail($title, $body, $url))
                ->delay(now()->addSeconds($delay));

            Mail::mailer($resolvedMailer)
                ->to($email)
                ->queue($mailable);

            $delay += self::DELAY_STEP_SECONDS;
            $queued++;
        }

        return $queued;
    }
}
