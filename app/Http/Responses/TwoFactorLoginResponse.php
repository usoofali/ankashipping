<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $intended = session()->get('url.intended');

        if ($intended && $this->isLivewireInternalUrl($intended)) {
            session()->forget('url.intended');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login', route('dashboard')));
    }

    /**
     * Determine if the intended URL points to an internal Livewire endpoint.
     */
    protected function isLivewireInternalUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return str_contains($path, '/livewire-')
            || str_ends_with($path, '/update')
            || str_ends_with($path, '/upload-file');
    }
}
