<?php

use App\Exceptions\InvalidTransition;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\LimitRequestSize;
use App\Http\Middleware\RequireHttps;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\StructuredLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only the local TLS proxy may say a request arrived over HTTPS (X-Forwarded-Proto).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        // Global, not API-only (defect D2): unknown paths and wrong methods never reach the API group,
        // but their error responses still need a correlation ID and the security headers.
        $middleware->prepend([CorrelationId::class, SecurityHeaders::class, LimitRequestSize::class]);
        $middleware->api(prepend: [RequireHttps::class]);
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->booted(function (): void {
        // Defect D6: every signed-in user gets a request budget, not only the sign-in form.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('permit.api_rate_per_minute'))
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([InvalidTransition::class]); // an expected 409, not a fault

        $exceptions->report(function (Throwable $e) {
            StructuredLog::event('exception.unhandled', [
                'exception' => $e::class,
                'at' => basename($e->getFile()).':'.$e->getLine(), // class and location only: messages can contain SQL or personal data
            ], 'error');
        });

        // One safe error shape for every API failure. Details stay in the logs.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $error = fn (int $status, string $code, string $message, ?array $details = null) => response()->json([
                'error' => array_filter([
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                    'correlation_id' => StructuredLog::correlationId(),
                ], fn ($value) => $value !== null),
            ], $status);

            $response = match (true) {
                $e instanceof ValidationException => $error(422, 'validation_failed', 'Some fields need attention.', $e->errors()),
                $e instanceof AuthenticationException => $error(401, 'unauthenticated', 'Sign in to continue.'),
                $e instanceof InvalidTransition => $error(409, 'invalid_transition', $e->userMessage()),
                $e instanceof HttpExceptionInterface => $error($e->getStatusCode(), 'http_'.$e->getStatusCode(), match ($e->getStatusCode()) {
                    403 => 'You are not allowed to do this.',
                    404 => 'That item was not found.',
                    409 => 'This action is not available right now.',
                    405 => 'This action is not supported here.',
                    413 => 'The request is too large.',
                    426 => 'Use HTTPS to connect to this service.',
                    429 => 'Too many requests. Try again shortly.',
                    default => 'The request could not be completed.',
                }),
                default => $error(500, 'server_error', 'Something went wrong on our side. Quote the correlation ID if you report it.'),
            };
            if ($e instanceof HttpExceptionInterface) {
                $response->headers->add($e->getHeaders()); // e.g. Retry-After on 429, Allow on 405
            }

            return $response;
        });
    })->create();
