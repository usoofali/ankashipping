<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToSetupIfRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup')) {
            return $next($request);
        }

        if ($this->hasSuperAdmin() || $this->isSetupComplete()) {
            return $next($request);
        }

        return redirect()->route('setup');
    }

    protected function hasSuperAdmin(): bool
    {
        try {
            return User::role('super_admin')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function isSetupComplete(): bool
    {
        return File::exists(storage_path('app/setup-complete'));
    }
}
