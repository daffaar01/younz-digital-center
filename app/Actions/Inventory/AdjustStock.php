<?php

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    public function handle(Product $product, int $quantity, string $type, ?User $user, ?string $reason = null, ?Model $reference = null): StockMovement
    {
        return DB::transaction(function () use ($product, $quantity, $type, $user, $reason, $reference) {
            /** @var Product $locked */
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $before = $locked->stock;
            $after = $before + $quantity;

            if ($after < 0 && ! $locked->allow_negative_stock) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$locked->name} tidak mencukupi.",
                ]);
            }

            $locked->update(['stock' => $after]);

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
            ]);
        });
    }
}
