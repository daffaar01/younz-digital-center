<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalProductVariant extends Model
{
    protected $fillable = ['label', 'price', 'stock', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
    }

    public function priceLabel(): string
    {
        return $this->price === null ? 'Konfirmasi harga' : 'Rp'.number_format($this->price, 0, ',', '.');
    }

    public function stockLabel(): string
    {
        return $this->stock === null ? 'Konfirmasi stok' : $this->stock.' tersedia';
    }
}
