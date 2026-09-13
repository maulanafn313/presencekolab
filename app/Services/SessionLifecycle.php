<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionLifecycle
{
    private const KEYS = ['user', 'auth_password_fingerprint', 'verified_password_reset'];

    public function login(Request $request, User|array $user): void
    {
        $data = $user instanceof User ? $user->getAttributes() : $user;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user'] = array_intersect_key($data, array_flip(['id', 'role', 'email', 'nim', 'nama', 'foto_base64']));
        $_SESSION['auth_password_fingerprint'] = hash('sha256', $data['password']);
        if ($request->hasSession()) {
            $request->session()->migrate(true);
        }
        $this->persist($request);
    }

    public function persist(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }
        foreach (self::KEYS as $key) {
            if (isset($_SESSION[$key])) {
                $request->session()->put($key, $_SESSION[$key]);
            } else {
                $request->session()->forget($key);
            }
        }
    }

    public function logout(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_write_close();
        }
    }
}
