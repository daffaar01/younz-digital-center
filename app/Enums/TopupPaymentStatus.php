<?php

namespace App\Enums;

enum TopupPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Pembayaran diterima',
            self::Expired => 'Pembayaran kedaluwarsa',
            self::Failed => 'Pembayaran gagal',
            self::Cancelled => 'Pembayaran dibatalkan',
            self::Refunded => 'Dana dikembalikan',
        };
    }
}
