<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSetupIsAccessible
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSetupComplete() || $this->hasSuperAdmin()) {
            return redirect()->route($request->user() ? 'dashboard' : 'login');
        }

        return $next($request);
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
