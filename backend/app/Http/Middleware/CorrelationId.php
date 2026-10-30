<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-Id');
        // Accept the client's ID only if it is short and plain, so it cannot inject text into logs.
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();
        app()->instance('correlation_id', $id);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id);

        return $response;
    }
}
