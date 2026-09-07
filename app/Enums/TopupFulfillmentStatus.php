<?php

namespace App\Enums;

enum TopupFulfillmentStatus: string
{
    case WaitingPayment = 'waiting_payment';
    case Queued = 'queued';
    case Processing = 'processing';
    case ProviderPending = 'provider_pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::WaitingPayment => 'Menunggu pembayaran',
            self::Queued => 'Masuk antrean',
            self::Processing => 'Sedang diproses',
            self::ProviderPending => 'Menunggu provider',
            self::Success => 'Top up berhasil',
            self::Failed => 'Top up gagal',
        };
    }
}
