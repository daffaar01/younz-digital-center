<?php

namespace App\Models;

use App\Enums\DigitalTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DigitalTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DigitalTransactionStatus::class,
            'nominal' => 'integer',
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'admin_fee' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'subject');
    }

    public function getProfitAttribute(): int
    {
        return $this->selling_price + $this->admin_fee - $this->cost_price;
    }
}
