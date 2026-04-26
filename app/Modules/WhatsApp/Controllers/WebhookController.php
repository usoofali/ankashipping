<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Jobs\ProcessIncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Meta Webhook Verification Handshake.
     */
    public function verify(Request $request): JsonResponse|string
    {
        $verifyToken = config('whatsapp.verify_token');

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return (string) $challenge;
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle incoming Meta API payloads.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::channel('whatsapp')->info('Incoming Webhook Payload', $payload);

        // Dispatch job to process the message asynchronously
        ProcessIncomingMessage::dispatch($payload);

        return response()->json(['status' => 'success']);
    }
}
