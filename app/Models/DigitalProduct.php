<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalProduct extends Model
{
    protected $fillable = [
        'name', 'category', 'mark', 'image_path', 'image_disk', 'image_is_upload',
        'stock', 'price', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'image_is_upload' => 'boolean',
            'stock' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function stockLabel(): string
    {
        return $this->stock === null ? 'Konfirmasi stok' : $this->stock.' tersedia';
    }

    public function priceLabel(): string
    {
        return $this->price === null ? 'Konfirmasi harga' : 'Rp'.number_format($this->price, 0, ',', '.');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DigitalProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(bool $staff = false): string
    {
        return $this->image_is_upload
            ? '/backend/v1/'.($staff ? 'staff/' : '').'digital-products/'.$this->getKey().'/image?v=2-'.$this->updated_at?->timestamp
            : $this->image_path;
    }
}
