<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Refuses API traffic that did not arrive over HTTPS, and tells browsers to keep using HTTPS. */
final class RequireHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('permit.require_https') && ! $request->secure()) {
            abort(426);
        }

        $response = $next($request);
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $response->headers->set('Cache-Control', 'no-store'); // API answers may contain permit data

        return $response;
    }
}
