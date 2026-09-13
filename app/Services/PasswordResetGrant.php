<?php

namespace App\Services;

final class PasswordResetGrant
{
    public static function issue(array &$session, string $token, int $userId): void
    {
        $session['verified_password_reset'] = ['digest' => hash('sha256', $token), 'user_id' => $userId, 'expires' => time() + 300];
    }

    public static function allows(array $session, string $token, int $userId): bool
    {
        $grant = $session['verified_password_reset'] ?? [];

        return ($grant['user_id'] ?? null) === $userId && ($grant['expires'] ?? 0) > time()
            && is_string($grant['digest'] ?? null) && hash_equals($grant['digest'], hash('sha256', $token));
    }
}
