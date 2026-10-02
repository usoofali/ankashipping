<?php

use App\Http\Middleware\EnsureSetupIsAccessible;
use App\Http\Middleware\RedirectToSetupIfRequired;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/whatsapp/webhook',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'setup.access' => EnsureSetupIsAccessible::class,
            'setup.redirect' => RedirectToSetupIfRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419) {
                if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                    return response()->json(['message' => 'CSRF token mismatch.'], 419);
                }

                return redirect()->guest(route('login'))->with('error', 'Your session has expired due to inactivity. Please log in again.');
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            return redirect()->guest(route('login'))->with('error', 'Your session has expired due to inactivity. Please log in again.');
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->hasHeader('X-Livewire')) {
                return response()->json(['message' => 'Unauthenticated.'], 419);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest($e->redirectTo($request) ?? route('login'));
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (str_starts_with(trim($request->path(), '/'), 'livewire-') || $request->hasHeader('X-Livewire')) {
                return redirect()->to(auth()->check() ? route('dashboard') : route('login'));
            }
        });
    })->create();
