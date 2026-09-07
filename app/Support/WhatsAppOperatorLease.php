<?php

namespace App\Support;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppOperatorLease
{
    /** @var array<string, string> */
    private static array $owners = [];

    public static function run(string $key, Closure $callback): mixed
    {
        $key = hash('sha256', $key);
        $owner = (string) Str::uuid();
        try {
            DB::transaction(fn () => DB::table('whatsapp_operator_leases')->insert(['operator_key' => $key]));
        } catch (UniqueConstraintViolationException) {
            // The permanent anchor already exists.
        }
        $claimed = DB::table('whatsapp_operator_leases')->where('operator_key', $key)
            ->where(fn ($query) => $query->whereNull('owner')->orWhere('expires_at', '<=', now()))
            ->update(['owner' => $owner, 'expires_at' => now()->addMinutes(5)]);
        if (! $claimed) throw new RuntimeException('Permintaan sebelumnya masih diproses. Coba lagi sebentar.');
        self::$owners[$key] = $owner;
        try {
            return $callback();
        } finally {
            unset(self::$owners[$key]);
            DB::table('whatsapp_operator_leases')->where('operator_key', $key)->where('owner', $owner)
                ->update(['owner' => null, 'expires_at' => null]);
        }
    }

    public static function within(string $key, Closure $callback): mixed
    {
        if (isset(self::$owners[hash('sha256', $key)])) {
            self::transaction(fn () => null);
            return $callback();
        }
        return self::run($key, $callback);
    }

    public static function transaction(Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($callback) {
            foreach (self::$owners as $key => $owner) {
                $row = DB::table('whatsapp_operator_leases')->where('operator_key', $key)->lockForUpdate()->first();
                if ($row === null || $row->owner !== $owner || $row->expires_at <= now()->toDateTimeString()) {
                    throw new RuntimeException('Sesi pemrosesan sudah berubah. Ulangi permintaan.');
                }
            }
            return $callback();
        }, $attempts);
    }
}
