<?php

namespace App\Enums;

enum DigitalTransactionStatus: string
{
    case Draft = 'draft';
    case Pending = 'menunggu';
    case Processing = 'diproses';
    case Success = 'berhasil';
    case Failed = 'gagal';
    case Cancelled = 'dibatalkan';
    case Refunded = 'dikembalikan';
}
