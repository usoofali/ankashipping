<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class VinAuctionApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function searchVin(string $normalizedVin): array
    {
        $key = config('services.copart_iaai.key');
        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('Copart/IAAI RapidAPI key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.copart_iaai.base_url'), '/');
        $host = (string) config('services.copart_iaai.host');
        $timeout = (int) config('services.copart_iaai.timeout', 30);

        $url = $baseUrl.'/search-vin/'.$normalizedVin;

        $response = Http::withHeaders([
            'x-rapidapi-host' => $host,
            'x-rapidapi-key' => $key,
        ])->timeout($timeout)->get($url);

        if ($response->status() === 429) {
            throw new RequestException($response);
        }

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $requestsLeft = $response->header('x-ratelimit-requests-remaining');
        if (is_numeric($requestsLeft)) {
            $data['api_request_left'] = (int) $requestsLeft;
        }

        if (array_key_exists('api_request_left', $data)) {
            Cache::put(
                'vin-api:api-request-left',
                (int) $data['api_request_left'],
                now()->addDay(),
            );
        }

        return $data;
    }
}
