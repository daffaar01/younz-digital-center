<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['base_price' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<ServiceOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
