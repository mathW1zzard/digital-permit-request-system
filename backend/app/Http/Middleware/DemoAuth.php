<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * WEEK 13 STAND-IN FOR LOGIN. Anyone can type any email into this header,
 * so it proves nothing about identity. Week 14 replaces it with Sanctum tokens.
 */
final class DemoAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->header('X-Demo-User');
        $user = is_string($email) ? User::where('email', $email)->first() : null;

        if (! $user) {
            throw new AuthenticationException();
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
