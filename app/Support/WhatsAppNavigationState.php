<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class WhatsAppNavigationState
{
    public static function get(string $key): mixed
    {
        $row = DB::table('whatsapp_navigation_states')->where('state_key', hash('sha256', $key))->where('expires_at', '>', now())->first();
        return $row === null ? null : json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR)['state'];
    }

    public static function put(string $key, mixed $value, mixed $expiresAt): void
    {
        WhatsAppOperatorLease::transaction(fn () => DB::table('whatsapp_navigation_states')->upsert([
            'state_key' => hash('sha256', $key),
            'payload' => json_encode(['version' => 1, 'state' => $value], JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
        ], ['state_key'], ['payload', 'expires_at']));
    }

    public static function forget(string $key): void
    {
        WhatsAppOperatorLease::transaction(fn () => DB::table('whatsapp_navigation_states')->where('state_key', hash('sha256', $key))->delete());
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }
}
