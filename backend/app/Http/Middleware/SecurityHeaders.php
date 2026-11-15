<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 15 hardening (defects D4, D5): browser security headers on every response, including
 * errors and unknown routes, and no "X-Powered-By" header announcing the PHP version.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! headers_sent()) {
            header_remove('X-Powered-By'); // added by PHP itself, so it must be removed here
        }

        $response = $next($request);
        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // The API only returns JSON: nothing may load scripts, frames or other content from it.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        return $response;
    }
}
