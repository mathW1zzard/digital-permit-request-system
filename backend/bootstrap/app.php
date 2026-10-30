<?php

use App\Exceptions\InvalidTransition;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\DemoAuth;
use App\Http\Middleware\RequireRole;
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
        // Runs before route-model binding, so a stranger gets 401, never a 404 that reveals data.
        $middleware->api(prepend: [CorrelationId::class, DemoAuth::class]);
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([InvalidTransition::class]); // an expected 409, not a fault

        $exceptions->report(function (Throwable $e) {
            StructuredLog::event('exception.unhandled', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'at' => basename($e->getFile()).':'.$e->getLine(),
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

            return match (true) {
                $e instanceof ValidationException => $error(422, 'validation_failed', 'Some fields need attention.', $e->errors()),
                $e instanceof AuthenticationException => $error(401, 'unauthenticated', 'Sign in to continue.'),
                $e instanceof InvalidTransition => $error(409, 'invalid_transition', 'This action is not available while the permit is '.str_replace('_', ' ', $e->from->value).'.'),
                $e instanceof HttpExceptionInterface => $error($e->getStatusCode(), 'http_'.$e->getStatusCode(), match ($e->getStatusCode()) {
                    403 => 'You are not allowed to do this.',
                    404 => 'That item was not found.',
                    409 => 'This action is not available right now.',
                    429 => 'Too many requests. Try again shortly.',
                    default => 'The request could not be completed.',
                }),
                default => $error(500, 'server_error', 'Something went wrong on our side. Quote the correlation ID if you report it.'),
            };
        });
    })->create();
