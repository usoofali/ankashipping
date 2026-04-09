<?php

namespace App\Jobs;

use App\Mail\Newsletter as NewsletterMail;
use App\Models\Newsletter;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendIndividualNewsletterJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Newsletter $newsletter,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailer = SystemSetting::current()->getMailerFor($this->newsletter->mailer);

        Mail::mailer($mailer)
            ->to($this->user->email)
            ->send(new NewsletterMail(
                title: $this->newsletter->subject,
                body: $this->newsletter->body,
                url: $this->newsletter->url
            ));

    }
}
