<?php

namespace App\Enums;

enum DigiflazzTransactionType: string
{
    case Prepaid = 'prepaid';
    case Postpaid = 'postpaid';

    public function label(): string
    {
        return match ($this) {
            self::Prepaid => 'Prabayar',
            self::Postpaid => 'Pascabayar',
        };
    }
}
