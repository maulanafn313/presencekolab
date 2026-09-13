<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LegacyAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $tokenUser = Auth::guard('sanctum')->user();
            if (! $tokenUser || ! ($tokenUser->currentAccessToken() instanceof PersonalAccessToken)) {
                return response()->json(['ok' => false, 'message' => 'Token tidak valid.'], 401);
            }
            Auth::setUser($tokenUser);

            return $next($request);
        }
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');
            if (! $request->hasSession() || ! is_string($token) || ! hash_equals($request->session()->token(), $token)) {
                return response()->json(['ok' => false, 'message' => 'Sesi keamanan tidak valid.'], 419);
            }
        }
        // 1. Check if user is already authenticated
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Start session if not started (for legacy)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 3. Try legacy session
        if ($request->hasSession()) {
            foreach (['user', 'auth_password_fingerprint'] as $key) {
                if ($request->session()->has($key)) {
                    $_SESSION[$key] = $request->session()->get($key);
                }
            }
        }
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            $user = User::find($_SESSION['user']['id']);
            if ($user && hash_equals(hash('sha256', $user->password), $_SESSION['auth_password_fingerprint'] ?? '')) {
                Auth::setUser($user);

                return $next($request);
            }
        }

        // 4. Try Sanctum guard (for Postman/Tokens)
        try {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                Auth::setUser($user);

                return $next($request);
            }
        } catch (\Exception $e) {
            // Ignore sanctum errors here
        }

        return $next($request);
    }
}
