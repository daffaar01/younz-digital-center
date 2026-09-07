<?php

namespace App\Support;

use App\Models\TopupOrder;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppTopupStatus
{
    public function answer(string $message, string $senderJid, string $chatJid): ?string
    {
        if (! preg_match('/\Acek\s+(?:transaksi|order|status)\b/iu', trim($message))) {
            return null;
        }
        $denied = 'Transaksi tidak ditemukan atau tidak terhubung dengan nomor WhatsApp ini. Gunakan nomor WhatsApp yang dipakai saat memesan.';
        if ($senderJid !== $chatJid || ! preg_match('/\A([1-9]\d{7,14})@s\.whatsapp\.net\z/', $senderJid, $sender)) {
            return $denied;
        }
        $phone = $this->phone($sender[1]);
        $key = 'whatsapp:status:'.hash('sha256', $phone);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return 'Terlalu banyak pengecekan status. Tunggu satu menit lalu coba lagi.';
        }
        RateLimiter::hit($key, 60);
        if (! preg_match('/\Acek\s+(?:transaksi|order|status)\s+(TOP-\d{8}-\d{4,10})\z/iu', trim($message), $match)) {
            return 'Gunakan: *Cek transaksi TOP-20260906-0002*. Masukkan nomor transaksi dari pesan konfirmasi.';
        }
        $order = TopupOrder::query()->where('order_number', strtoupper($match[1]))->first();
        if ($order === null || $phone === '' || ! hash_equals($phone, $this->phone((string) $order->customer_phone))) {
            return $denied;
        }

        return '*Status Transaksi '.$order->order_number."*\n"
            .'Pembayaran: '.$order->payment_status->label()."\n"
            .'Pemenuhan: '.$order->fulfillment_status->label()."\n"
            .'Tujuan: '.$order->maskedDestination()."\n\n"
            .'Status berdasarkan pembaruan terakhir yang tersimpan. Pengecekan ini tidak membuat pembayaran atau pembelian baru.';
    }

    private function phone(string $value): string
    {
        if (! preg_match('/\A[+\d\s().-]+\z/', $value)) {
            return '';
        }
        $value = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($value, '00')) {
            $value = substr($value, 2);
        }
        if (str_starts_with($value, '0')) {
            $value = '62'.substr($value, 1);
        }

        return preg_match('/\A[1-9]\d{7,14}\z/', $value) ? $value : '';
    }
}
