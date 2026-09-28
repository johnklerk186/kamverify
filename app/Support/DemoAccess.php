<?php

namespace App\Support;

use App\Models\User;

/**
 * Stateless token for demo access. The kv_demo cookie carries an
 * HMAC signature bound to the demo user — no session or storage
 * needed, survives restarts and cache clears, and is worthless for
 * any other account.
 */
class DemoAccess
{
    public const COOKIE = 'kv_demo';

    public static function enabled(): bool
    {
        return (bool) config('app.demo_enabled');
    }

    public static function tokenFor(User $user): string
    {
        return hash_hmac('sha256', 'kv-demo:' . $user->id . ':' . $user->email, (string) config('app.key'));
    }

    public static function tokenMatches(User $user, ?string $token): bool
    {
        return $token !== null && hash_equals(self::tokenFor($user), $token);
    }
}
