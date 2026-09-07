<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class WhatsAppPendingConfirmation
{
    public static function get(string $key): ?array
    {
        $row = DB::table('whatsapp_pending_confirmations')->where('operator_key', hash('sha256', $key))->where('expires_at', '>', now())->first();
        return $row === null ? null : json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    }

    public static function put(string $key, array $payload, $expiresAt): void
    {
        WhatsAppOperatorLease::transaction(function () use ($key, $payload, $expiresAt): void {
            $existing = self::get($key);
            if ($existing !== null && ($existing['order_id'] ?? null) !== ($payload['order_id'] ?? null)) {
                throw new RuntimeException('Masih ada transaksi menunggu konfirmasi. Ketik BATAL terlebih dahulu.');
            }
            $order = \App\Models\TopupOrder::query()->lockForUpdate()->findOrFail($payload['order_id']);
            if ($order->payment_status !== \App\Enums\TopupPaymentStatus::Pending
                || $order->fulfillment_status !== \App\Enums\TopupFulfillmentStatus::WaitingPayment
                || $order->expires_at?->isPast()) {
                throw new RuntimeException('Transaksi tidak lagi menunggu konfirmasi. Buat permintaan baru.');
            }
            if ($order->expires_at === null || $order->expires_at->greaterThan($expiresAt)) {
                $order->update(['expires_at' => $expiresAt]);
            }
            DB::table('whatsapp_pending_confirmations')->upsert([
                'operator_key' => hash('sha256', $key),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'expires_at' => $expiresAt,
            ], ['operator_key'], ['payload', 'expires_at']);
        });
    }

    public static function forget(string $key): void
    {
        WhatsAppOperatorLease::transaction(fn () => DB::table('whatsapp_pending_confirmations')->where('operator_key', hash('sha256', $key))->delete());
    }
}
