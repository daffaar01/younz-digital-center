<?php

namespace App\Actions\Sales;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutSale
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{product_id:int, quantity:int, discount?:int}>  $items
     * @param  list<array{method:string, amount:int, reference?:string|null}>  $payments
     */
    public function handle(User $cashier, array $items, array $payments, int $discount = 0, ?Customer $customer = null, ?CashSession $cashSession = null, ?string $notes = null): Sale
    {
        return DB::transaction(function () use ($cashier, $items, $payments, $discount, $customer, $cashSession, $notes) {
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'Keranjang tidak boleh kosong.']);
            }

            $productIds = collect($items)->pluck('product_id')->unique()->sort()->values();
            if ($productIds->count() !== count($items)) {
                throw ValidationException::withMessages(['items' => 'Produk yang sama tidak boleh muncul lebih dari satu kali.']);
            }
            $products = Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0;
            $prepared = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];
                $itemDiscount = max(0, (int) ($item['discount'] ?? 0));

                if (! $product || ! $product->is_active || $quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'Produk atau kuantitas tidak valid.']);
                }

                if ($product->stock < $quantity && ! $product->allow_negative_stock) {
                    throw ValidationException::withMessages(['items' => "Stok {$product->name} tidak mencukupi."]);
                }

                $lineBeforeDiscount = $product->selling_price * $quantity;
                if ($itemDiscount > $lineBeforeDiscount) {
                    throw ValidationException::withMessages(['items' => "Diskon {$product->name} melebihi subtotal item."]);
                }

                $lineSubtotal = $lineBeforeDiscount - $itemDiscount;
                $subtotal += $lineSubtotal;
                $prepared[] = compact('product', 'quantity', 'itemDiscount', 'lineSubtotal');
            }

            if ($discount < 0 || $discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'Diskon transaksi tidak valid.']);
            }

            $total = $subtotal - $discount;
            if (collect($payments)->sum(fn (array $payment) => (int) $payment['amount']) !== $total) {
                throw ValidationException::withMessages(['payments' => 'Total pembayaran harus sama dengan total transaksi.']);
            }

            $cashSession = $cashSession ? CashSession::query()->lockForUpdate()->find($cashSession->id) : null;
            if ($cashSession && ($cashSession->status !== 'open' || $cashSession->user_id !== $cashier->id)) {
                throw ValidationException::withMessages(['cash_session_id' => 'Sesi kasir tidak valid.']);
            }

            $sale = Sale::create([
                'invoice_number' => $this->numbers->next('INV'),
                'cash_session_id' => $cashSession?->id,
                'user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'notes' => $notes,
                'completed_at' => now(),
            ]);

            foreach ($prepared as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $sale->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit_price' => $product->selling_price,
                    'cost_price' => $product->cost_price,
                    'quantity' => $line['quantity'],
                    'discount' => $line['itemDiscount'],
                    'subtotal' => $line['lineSubtotal'],
                ]);

                $before = $product->stock;
                $after = $before - $line['quantity'];
                $product->update(['stock' => $after]);
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $cashier->id,
                    'type' => 'sale',
                    'quantity' => -$line['quantity'],
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'reference_type' => $sale->getMorphClass(),
                    'reference_id' => $sale->id,
                    'reason' => $sale->invoice_number,
                ]);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => (int) $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            $this->audit->log('sale.completed', $sale, after: $sale->toArray());

            return $sale->load(['items', 'payments', 'customer']);
        }, 3);
    }
}
