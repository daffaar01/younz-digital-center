<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Cashier = 'cashier';
    case PrintOperator = 'print_operator';
    case Designer = 'designer';
    case Developer = 'developer';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Cashier => 'Kasir',
            self::PrintOperator => 'Operator Print',
            self::Designer => 'Desainer',
            self::Developer => 'Developer',
            self::Customer => 'Pelanggan',
        };
    }
}
