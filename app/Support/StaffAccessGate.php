<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffAccessGate
{
    public const SESSION_KEY = 'staff_access_gate.verified_at';

    public function isConfigured(): bool
    {
        return trim((string) config('auth.staff_access.code_hash')) !== '';
    }

    public function verify(string $code): bool
    {
        $hash = trim((string) config('auth.staff_access.code_hash'));

        if ($hash === '') {
            return false;
        }

        try {
            return Hash::check($code, $hash);
        } catch (RuntimeException) {
            return false;
        }
    }

    public function hasValidSession(Request $request): bool
    {
        $verifiedAt = (int) $request->session()->get(self::SESSION_KEY, 0);
        $ttlSeconds = max(1, (int) config('auth.staff_access.ttl_minutes', 60)) * 60;

        if ($verifiedAt <= 0 || $verifiedAt < now()->timestamp - $ttlSeconds) {
            $request->session()->forget(self::SESSION_KEY);

            return false;
        }

        return true;
    }

    public function authorize(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
    }
}
