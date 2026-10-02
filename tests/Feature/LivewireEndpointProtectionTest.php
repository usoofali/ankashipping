<?php

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Laravel\Fortify\Contracts\LoginResponse;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

test('get request to livewire update path redirects to login for guests', function () {
    $response = $this->get(EndpointResolver::updatePath());

    $response->assertRedirect(route('login'));
});

test('get request to livewire update path redirects to dashboard for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(EndpointResolver::updatePath());

    $response->assertRedirect(route('dashboard'));
});

test('get request to livewire upload path redirects to login for guests', function () {
    $response = $this->get(EndpointResolver::uploadPath());

    $response->assertRedirect(route('login'));
});

test('get request to livewire upload path redirects to dashboard for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(EndpointResolver::uploadPath());

    $response->assertRedirect(route('dashboard'));
});

test('csrf token mismatch returns 419 json for livewire requests', function () {
    $request = Request::create(EndpointResolver::updatePath(), 'POST', [], [], [], [
        'HTTP_X_LIVEWIRE' => '1',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ]);

    $exception = new TokenMismatchException('CSRF token mismatch.');
    $handler = app(ExceptionHandler::class);
    $response = $handler->render($request, $exception);

    expect($response->getStatusCode())->toBe(419);
    expect(json_decode($response->getContent(), true))->toMatchArray([
        'message' => 'CSRF token mismatch.',
    ]);
});

test('authentication exception returns 419 json for livewire requests to trigger client reload', function () {
    $request = Request::create(EndpointResolver::updatePath(), 'POST', [], [], [], [
        'HTTP_X_LIVEWIRE' => '1',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ]);

    $exception = new AuthenticationException('Unauthenticated.');
    $handler = app(ExceptionHandler::class);
    $response = $handler->render($request, $exception);

    expect($response->getStatusCode())->toBe(419);
    expect(json_decode($response->getContent(), true))->toMatchArray([
        'message' => 'Unauthenticated.',
    ]);
});

test('method not allowed exception on livewire path redirects gracefully instead of 405 error', function () {
    $request = Request::create(EndpointResolver::updatePath(), 'PUT');
    $exception = new MethodNotAllowedHttpException(['POST'], 'Method not allowed.');

    $handler = app(ExceptionHandler::class);
    $response = $handler->render($request, $exception);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route('login'));
});

test('login response sanitizes poisoned livewire intended url and redirects to dashboard', function () {
    $user = User::factory()->create();

    session(['url.intended' => url(EndpointResolver::updatePath())]);

    $loginResponse = app(LoginResponse::class);
    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $response = $loginResponse->toResponse($request);

    expect(session()->has('url.intended'))->toBeFalse();
    expect($response->headers->get('Location'))->toBe(route('dashboard'));
});

test('login response preserves legitimate intended url', function () {
    $user = User::factory()->create();

    $legitimateUrl = url('/shipments/10/edit');
    session(['url.intended' => $legitimateUrl]);

    $loginResponse = app(LoginResponse::class);
    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $response = $loginResponse->toResponse($request);

    expect($response->headers->get('Location'))->toBe($legitimateUrl);
});
