<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\RefundStatus;
use App\Models\ApprovalRequest;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSaleRefund
{
    public function __construct(
        private readonly RefundCalculator $calculator,
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int|string, array{quantity:mixed, restore_stock?:mixed}>  $items
     */
    public function handle(User $requester, Sale $sale, array $items, string $method, string $reason): Refund
    {
        if (! $requester->hasRole('owner', 'admin', 'cashier')) {
            abort(403);
        }

        return DB::transaction(function () use ($requester, $sale, $items, $method, $reason) {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if (! in_array($lockedSale->status, ['completed', 'partially_refunded'], true)) {
                throw ValidationException::withMessages(['sale' => 'Transaksi ini tidak dapat direfund.']);
            }

            $lockedSale->load('items');
            $prepared = $this->calculator->calculate($lockedSale, $items);
            $amount = collect($prepared)->sum('amount');
            $now = now();

            $approval = ApprovalRequest::create([
                'request_number' => $this->numbers->next('APR'),
                'type' => ApprovalType::SaleRefund,
                'subject_type' => $lockedSale->getMorphClass(),
                'subject_id' => $lockedSale->id,
                'requested_by' => $requester->id,
                'status' => ApprovalStatus::Pending,
                'reason' => $reason,
                'requested_at' => $now,
                'expires_at' => $now->copy()->addHours((int) config('approvals.expires_after_hours', 24)),
            ]);

            $refund = Refund::create([
                'refund_number' => $this->numbers->next('RFD'),
                'sale_id' => $lockedSale->id,
                'approval_request_id' => $approval->id,
                'requested_by' => $requester->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'status' => RefundStatus::Pending,
            ]);

            $refund->items()->createMany($prepared);
            $approval->update([
                'payload' => [
                    'refund_id' => $refund->id,
                    'refund_number' => $refund->refund_number,
                    'amount' => $amount,
                    'method' => $method,
                    'items' => $prepared,
                ],
            ]);
            $approval->events()->create([
                'actor_id' => $requester->id,
                'from_status' => null,
                'to_status' => ApprovalStatus::Pending->value,
                'notes' => $reason,
                'metadata' => ['refund_number' => $refund->refund_number, 'amount' => $amount],
            ]);

            $this->audit->log('approval.requested', $approval, after: $approval->fresh()->toArray());
            $this->audit->log('refund.requested', $refund, after: $refund->load('items')->toArray());

            return $refund->load(['items.saleItem', 'approvalRequest', 'sale']);
        }, 3);
    }
}
