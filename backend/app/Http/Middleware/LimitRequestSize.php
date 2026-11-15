<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 15 hardening (defect D7): refuse request bodies larger than the API ever needs.
 * The PHP install here allows 2 GB per request, so the application sets its own limit
 * instead of relying on the server's post_max_size.
 */
final class LimitRequestSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('permit.max_body_kb') * 1024;
        $declared = (int) $request->headers->get('Content-Length', '0');

        if ($declared > $limit || strlen($request->getContent()) > $limit) {
            abort(413);
        }

        return $next($request);
    }
}
