<?php

namespace App\Actions\Approvals;

use App\Models\RefundItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class RefundCalculator
{
    /**
     * @param  array<int|string, array{quantity:mixed, restore_stock?:mixed}>  $requestedItems
     * @return list<array{sale_item_id:int, quantity:int, amount:int, restore_stock:bool}>
     */
    public function calculate(Sale $sale, array $requestedItems): array
    {
        $items = $sale->items->sortBy('id')->values();
        $discountAllocations = $this->discountAllocations($sale);
        $reserved = RefundItem::query()
            ->selectRaw('refund_items.sale_item_id, SUM(refund_items.quantity) as reserved_quantity, SUM(refund_items.amount) as reserved_amount')
            ->join('refunds', 'refunds.id', '=', 'refund_items.refund_id')
            ->where('refunds.sale_id', $sale->id)
            ->whereIn('refunds.status', ['pending', 'completed'])
            ->groupBy('refund_items.sale_item_id')
            ->get()
            ->mapWithKeys(fn (RefundItem $item): array => [
                (int) $item->sale_item_id => [
                    'quantity' => (int) $item->getAttribute('reserved_quantity'),
                    'amount' => (int) $item->getAttribute('reserved_amount'),
                ],
            ]);

        $prepared = [];

        foreach ($requestedItems as $saleItemId => $request) {
            $quantity = (int) ($request['quantity'] ?? 0);
            if ($quantity === 0) {
                continue;
            }

            /** @var SaleItem|null $item */
            $item = $items->firstWhere('id', (int) $saleItemId);
            if (! $item || $quantity < 0) {
                throw ValidationException::withMessages(['items' => 'Item refund tidak valid.']);
            }

            $reservation = $reserved->get($item->id);
            $reservedQuantity = $reservation['quantity'] ?? 0;
            $reservedAmount = $reservation['amount'] ?? 0;
            $remainingQuantity = $item->quantity - $reservedQuantity;
            $netLineAmount = $item->subtotal - ($discountAllocations[$item->id] ?? 0);
            $remainingAmount = $netLineAmount - $reservedAmount;

            if ($quantity > $remainingQuantity) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.quantity" => "Jumlah refund {$item->name} melebihi sisa {$remainingQuantity}.",
                ]);
            }

            $amount = $quantity === $remainingQuantity
                ? $remainingAmount
                : intdiv($netLineAmount * $quantity, $item->quantity);

            $prepared[] = [
                'sale_item_id' => $item->id,
                'quantity' => $quantity,
                'amount' => $amount,
                'restore_stock' => filter_var($request['restore_stock'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu item dan masukkan jumlah refund.']);
        }

        if (collect($prepared)->sum('amount') < 1) {
            throw ValidationException::withMessages(['items' => 'Total nilai refund harus lebih dari nol.']);
        }

        return $prepared;
    }

    /** @return array<int, int> */
    private function discountAllocations(Sale $sale): array
    {
        $items = $sale->items->sortBy('id')->values();
        $allocations = [];

        foreach ($items as $item) {
            $allocations[$item->id] = intdiv($sale->discount * $item->subtotal, max(1, $sale->subtotal));
        }

        $remainingDiscount = $sale->discount - array_sum($allocations);
        foreach ($items->reverse() as $item) {
            if ($remainingDiscount === 0) {
                break;
            }

            $available = $item->subtotal - $allocations[$item->id];
            $addition = min($remainingDiscount, $available);
            $allocations[$item->id] += $addition;
            $remainingDiscount -= $addition;
        }

        return $allocations;
    }
}
