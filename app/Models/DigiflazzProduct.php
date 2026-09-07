<?php

namespace App\Models;

use App\Enums\DigiflazzTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property DigiflazzTransactionType $transaction_type */
class DigiflazzProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_type' => DigiflazzTransactionType::class,
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'provider_admin' => 'integer',
            'provider_commission' => 'integer',
            'buyer_product_status' => 'boolean',
            'seller_product_status' => 'boolean',
            'unlimited_stock' => 'boolean',
            'stock' => 'integer',
            'multi' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('buyer_product_status', true)
            ->where('seller_product_status', true)
            ->where(function (Builder $stock): void {
                $stock->where('transaction_type', DigiflazzTransactionType::Postpaid->value)
                    ->orWhere('unlimited_stock', true)
                    ->orWhere('stock', '>', 0);
            });
    }

    public function isGoPayPrepaid(): bool
    {
        return $this->transaction_type === DigiflazzTransactionType::Prepaid
            && mb_strtolower(trim((string) $this->category)) === 'e-money'
            && in_array(mb_strtolower(trim((string) $this->brand)), ['go pay', 'gopay'], true);
    }

    public function supportsGuidedPhonePurchase(): bool
    {
        return $this->transaction_type === DigiflazzTransactionType::Prepaid
            && (in_array(mb_strtolower(trim((string) $this->category)), ['data', 'pulsa'], true)
                || $this->isGoPayPrepaid());
    }

    /** @return HasMany<TopupOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(TopupOrder::class);
    }
}
