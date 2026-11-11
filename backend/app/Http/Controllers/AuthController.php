<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\StructuredLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Week 14 login: password check, then a short-lived Sanctum token. Replaces the X-Demo-User header. */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Throttle per email and address, so guessing one account's password is slow.
        $throttleKey = 'login:'.hash('sha256', Str::lower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            StructuredLog::event('auth.login_throttled', [], 'warning');
            abort(429);
        }

        $user = User::where('email', $data['email'])->first();
        // Same answer for "no such email" and "wrong password", so the API does not confirm which emails exist.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            StructuredLog::event('auth.login_failed', [], 'warning');

            return response()->json(['error' => [
                'code' => 'invalid_credentials',
                'message' => 'Email or password is incorrect.',
                'correlation_id' => StructuredLog::correlationId(),
            ]], 401);
        }

        RateLimiter::clear($throttleKey);
        $minutes = (int) config('sanctum.expiration');
        $token = $user->createToken('web', ['*'], now()->addMinutes($minutes));
        StructuredLog::event('auth.login_succeeded', ['user_id' => $user->id, 'token_id' => $token->accessToken->id]);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $user->only(['id', 'name', 'role']),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        StructuredLog::event('auth.logout', ['user_id' => $request->user()->id, 'token_id' => $token?->id]);
        $token?->delete();

        return response()->json(['data' => ['signed_out' => true]]);
    }
}
