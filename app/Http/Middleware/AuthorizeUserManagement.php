<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeUserManagement
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $target = $request->route('user') ?? $request->route('id');
        $readsOwnProfile = in_array($request->method(), ['GET', 'HEAD'], true)
            && $target !== null && (string) $target === (string) $user->id;
        if ($user->role !== 'admin' && ! $readsOwnProfile) {
            return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses.'], 403);
        }

        return $next($request);
    }
}
