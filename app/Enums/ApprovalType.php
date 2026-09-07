<?php

namespace App\Enums;

enum ApprovalType: string
{
    case SaleRefund = 'sale_refund';
    case ProductPriceChange = 'product_price_change';
    case StockAdjustment = 'stock_adjustment';
    case EmployeeAccessChange = 'employee_access_change';
    case DigitalTransaction = 'digital_transaction';
    case FinancialExpense = 'financial_expense';

    public function label(): string
    {
        return match ($this) {
            self::SaleRefund => 'Refund penjualan',
            self::ProductPriceChange => 'Perubahan harga produk',
            self::StockAdjustment => 'Penyesuaian stok manual',
            self::EmployeeAccessChange => 'Perubahan hak akses',
            self::DigitalTransaction => 'Pemrosesan PPOB',
            self::FinancialExpense => 'Pencatatan pengeluaran',
        };
    }
}
