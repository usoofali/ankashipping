<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    protected string $token;

    protected string $phoneNumberId;

    protected string $version;

    public function __construct()
    {
        $this->token = config('whatsapp.token', '');
        $this->phoneNumberId = config('whatsapp.phone_number_id', '');
        $this->version = config('whatsapp.version', 'v18.0');
    }

    /**
     * Send a text message.
     */
    public function sendMessage(string $to, string $text): array
    {
        return $this->request('messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    /**
     * Send a document.
     */
    public function sendDocument(string $to, string $url, string $filename): array
    {
        return $this->request('messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $url,
                'filename' => $filename,
            ],
        ]);
    }

    /**
     * Download media from Meta.
     */
    public function downloadMedia(string $mediaId): ?string
    {
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}";

        $response = Http::withToken($this->token)->get($url);

        if ($response->failed()) {
            return null;
        }

        $mediaUrl = $response->json('url');

        if (! $mediaUrl) {
            return null;
        }

        $fileResponse = Http::withToken($this->token)->get($mediaUrl);

        if ($fileResponse->failed()) {
            return null;
        }

        $extension = $this->getExtension($fileResponse->header('Content-Type'));
        $filename = 'whatsapp/'.uniqid().'.'.$extension;

        Storage::disk('public')->put($filename, $fileResponse->body());

        return $filename;
    }

    /**
     * Download media from Meta and return content directly without saving to disk.
     */
    public function streamMedia(string $mediaId): ?array
    {
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}";

        $response = Http::withToken($this->token)->get($url);

        if ($response->failed()) {
            return null;
        }

        $mediaUrl = $response->json('url');
        $mimeType = $response->json('mime_type');

        if (! $mediaUrl) {
            return null;
        }

        $fileResponse = Http::withToken($this->token)->get($mediaUrl);

        if ($fileResponse->failed()) {
            return null;
        }

        $extension = $this->getExtension($mimeType ?? $fileResponse->header('Content-Type'));
        $filename = 'attachment_'.$mediaId.'.'.$extension;

        return [
            'content' => $fileResponse->body(),
            'mime_type' => $mimeType ?? $fileResponse->header('Content-Type'),
            'filename' => $filename,
        ];
    }

    protected function getExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
    }

    /**
     * Internal request wrapper.
     */
    protected function request(string $endpoint, array $data): array
    {
        if (app()->environment('local')) {
            Log::channel('whatsapp')->info('Outgoing WhatsApp Request', [
                'endpoint' => $endpoint,
                'data' => $data,
            ]);
        }

        $url = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/{$endpoint}";

        try {
            $response = Http::withToken($this->token)->timeout(10)->post($url, $data);

            if ($response->failed()) {
                Log::channel('whatsapp')->error('WhatsApp API Error', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('WhatsApp Connection Error', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
