<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleSensitiveRequests
{
    public function handle(Request $request, Closure $next)
    {
        $groups = [
            'login' => 'login', 'register' => 'register',
            'forgot_password' => 'recovery', 'verify_otp' => 'recovery', 'reset_password' => 'recovery',
        ];
        $group = match ($request->path()) {
            'api/login' => 'login',
            'api/register' => 'register',
            'api/face/identify', 'api/face/verify' => 'face',
            default => null,
        };
        $actions = [$request->query('ajax'), $request->input('ajax'), $request->query('action'), $request->input('action')];
        $selected = $group ? [$group] : [];
        foreach ($actions as $action) {
            if (is_string($action) && isset($groups[$action])) {
                $selected[] = $groups[$action];
            }
        }
        if (! $selected) {
            return $next($request);
        }
        if (! $request->isMethod('POST')) {
            return response()->json(['ok' => false, 'message' => 'Method not allowed'], 405);
        }

        $email = $request->input('email', '');
        $email = is_string($email) ? strtolower(trim($email)) : '';
        $limits = [];
        foreach (array_unique($selected) as $name) {
            $policy = config('security.request_limits.'.$name);
            $prefix = 'sensitive:'.$name.':';
            $limits[$prefix.hash('sha256', (string) $request->ip())] = $policy['per_ip'];
            if (isset($policy['per_account_ip'])) {
                $limits[$prefix.hash('sha256', $request->ip().'|'.$email)] = $policy['per_account_ip'];
            }
        }
        foreach ($limits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retry = max(1, RateLimiter::availableIn($key));

                return response()->json([
                    'ok' => false,
                    'message' => 'Terlalu banyak percobaan. Silakan tunggu dan coba lagi.',
                    'retry_after' => $retry,
                ], 429)->header('Retry-After', (string) $retry)->header('Cache-Control', 'no-store');
            }
        }
        // Increment before dispatch: the legacy JSON handler calls exit().
        foreach ($limits as $key => $maxAttempts) {
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
