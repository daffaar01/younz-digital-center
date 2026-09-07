<?php

namespace App\Models;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property TopupPaymentStatus $payment_status
 * @property TopupFulfillmentStatus $fulfillment_status
 * @property DigiflazzTransactionType $transaction_type
 * @property Carbon|null $paid_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $expires_at
 */
class TopupOrder extends Model
{
    protected $guarded = [];

    protected $hidden = ['idempotency_key', 'request_fingerprint', 'midtrans_snap_token'];

    protected function casts(): array
    {
        return [
            'transaction_type' => DigiflazzTransactionType::class,
            'payment_status' => TopupPaymentStatus::class,
            'fulfillment_status' => TopupFulfillmentStatus::class,
            'destination' => 'encrypted',
            'customer_name' => 'encrypted',
            'customer_email' => 'encrypted',
            'customer_phone' => 'encrypted',
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'admin_fee' => 'integer',
            'total_amount' => 'integer',
            'midtrans_snap_token' => 'encrypted',
            'midtrans_redirect_url' => 'encrypted',
            'provider_customer_name' => 'encrypted',
            'bill_details' => 'encrypted:array',
            'serial_number' => 'encrypted',
            'provider_message' => 'encrypted',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'expires_at' => 'datetime',
            'inquired_at' => 'datetime',
            'provider_payment_requested_at' => 'datetime',
        ];
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $saved = parent::save($options);
            if ($saved && $this->payment_status === TopupPaymentStatus::Paid
                && $this->fulfillment_status === TopupFulfillmentStatus::Queued) {
                $this->getConnection()->table('topup_fulfillment_outbox')->upsert([
                    'topup_order_id' => $this->id,
                    'published_at' => null,
                    'generation' => (string) \Illuminate\Support\Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['topup_order_id'], ['published_at', 'updated_at', 'generation']);
            }
            return $saved;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function temporarySignedUrl(string $route): string
    {
        $days = max(1, min(30, (int) config('services.topup.access_link_days', 7)));

        $relativeUrl = URL::temporarySignedRoute(
            $route,
            now()->addDays($days),
            ['topupOrder' => $this],
            absolute: false,
        );

        return rtrim((string) config('app.frontend_url'), '/').$relativeUrl;
    }

    public function receiptPdfUrl(): string
    {
        return $this->temporarySignedUrl('topup.receipt.pdf');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DigiflazzProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(DigiflazzProduct::class, 'digiflazz_product_id');
    }

    public function maskedDestination(): string
    {
        return $this->maskedValue((string) $this->destination);
    }

    public function maskedCustomerPhone(): string
    {
        return $this->maskedValue((string) $this->customer_phone);
    }

    public function maskedCustomerEmail(): ?string
    {
        $email = trim((string) $this->customer_email);
        if ($email === '' || str_ends_with($email, '@example.invalid') || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }

    private function maskedValue(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 5) {
            return str_repeat('*', max(0, $length - 2)).mb_substr($value, -2);
        }

        return mb_substr($value, 0, 3).str_repeat('*', max(3, $length - 6)).mb_substr($value, -3);
    }
}
