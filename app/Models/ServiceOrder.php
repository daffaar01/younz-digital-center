<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ServiceOrderStatus $status
 * @property Carbon|null $deadline_at
 * @property Carbon|null $pickup_at
 * @property-read Customer|null $customer
 * @property-read Service|null $service
 */
class ServiceOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['public_token', 'checkout_request_fingerprint', 'midtrans_snap_token'];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'specifications' => 'array',
            'estimated_price' => 'integer',
            'estimate_approved_at' => 'datetime',
            'final_price' => 'integer',
            'paid_amount' => 'integer',
            'refunded_amount' => 'integer',
            'midtrans_snap_token' => 'encrypted',
            'midtrans_redirect_url' => 'encrypted',
            'snap_creation_started_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'payment_confirmation_at' => 'datetime',
            'revision_requests' => 'integer',
            'pickup_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<ServiceFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ServiceFile::class);
    }

    /** @return HasMany<ServiceOrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class);
    }

    public function payableAmount(): ?int
    {
        return $this->final_price ?? $this->estimated_price;
    }

    public function canReleaseResults(): bool
    {
        $payable = $this->payableAmount();

        return $payable !== null
            && $this->payment_status === 'paid'
            && $this->paid_amount >= $payable
            && in_array($this->status, [ServiceOrderStatus::Ready, ServiceOrderStatus::Completed], true);
    }
}
