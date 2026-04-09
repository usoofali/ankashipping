<?php

namespace App\Mail;

use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Newsletter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {
        $this->subject($this->title);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $emailLogo = $setting->logoSrcForEmail();
        $companyName = $setting->company_name ?: config('app.name');

        return new Content(
            markdown: 'emails.newsletter',
            with: [
                'title' => $this->title,
                'body' => $this->body,
                'companyName' => $companyName,
                'emailLogo' => $emailLogo,
                'url' => $this->url,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
