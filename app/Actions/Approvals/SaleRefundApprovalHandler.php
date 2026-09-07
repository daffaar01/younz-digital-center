<?php

namespace App\Actions\Approvals;

use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalType;
use App\Enums\RefundStatus;
use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class SaleRefundApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function type(): ApprovalType
    {
        return ApprovalType::SaleRefund;
    }

    public function assertCanApprove(ApprovalRequest $approval, User $actor): void
    {
        if (! $actor->hasRole('owner', 'admin')) {
            abort(403);
        }

        if (config('approvals.require_separate_approver', true) && $approval->requested_by === $actor->id) {
            throw ValidationException::withMessages(['approval' => 'Pemohon tidak boleh menyetujui permintaannya sendiri.']);
        }

        $refund = Refund::query()->where('approval_request_id', $approval->id)->firstOrFail();
        $threshold = (int) config('approvals.refund_owner_threshold', 500000);
        if ($actor->hasRole('admin') && $refund->amount > $threshold) {
            throw ValidationException::withMessages([
                'approval' => 'Refund di atas Rp '.number_format($threshold, 0, ',', '.').' hanya dapat disetujui owner.',
            ]);
        }
    }

    public function assertCanReject(ApprovalRequest $approval, User $actor): void
    {
        if (! $actor->hasRole('owner', 'admin')) {
            abort(403);
        }
    }

    public function approve(ApprovalRequest $approval, User $actor): void
    {
        $refund = Refund::query()->where('approval_request_id', $approval->id)->lockForUpdate()->firstOrFail();
        if ($refund->status !== RefundStatus::Pending) {
            throw ValidationException::withMessages(['approval' => 'Refund ini sudah diproses.']);
        }

        $sale = Sale::query()->lockForUpdate()->findOrFail($refund->sale_id);
        $refund->load(['items.saleItem']);
        $productIds = $refund->items
            ->filter(fn ($item) => $item->restore_stock && $item->saleItem->product_id)
            ->pluck('saleItem.product_id')->unique()->sort()->values();
        $products = Product::withTrashed()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
        $before = $refund->toArray();

        foreach ($refund->items as $refundItem) {
            if (! $refundItem->restore_stock || ! $refundItem->saleItem->product_id) {
                continue;
            }

            $product = $products->get($refundItem->saleItem->product_id);
            if (! $product) {
                throw ValidationException::withMessages(['approval' => "Produk {$refundItem->saleItem->name} tidak ditemukan."]);
            }

            $stockBefore = $product->stock;
            $stockAfter = $stockBefore + $refundItem->quantity;
            $product->update(['stock' => $stockAfter]);
            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => 'return_customer',
                'quantity' => $refundItem->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_type' => $refund->getMorphClass(),
                'reference_id' => $refund->id,
                'reason' => $refund->refund_number,
            ]);
        }

        $refund->update([
            'approved_by' => $actor->id,
            'status' => RefundStatus::Completed,
            'processed_at' => now(),
        ]);
        $refundedTotal = (int) Refund::query()
            ->where('sale_id', $sale->id)
            ->where('status', RefundStatus::Completed->value)
            ->sum('amount');
        $sale->update(['status' => $refundedTotal >= $sale->total ? 'refunded' : 'partially_refunded']);

        $this->audit->log('refund.completed', $refund, $before, $refund->fresh()->toArray(), [
            'sale_id' => $sale->id,
            'restored_stock' => true,
        ]);
    }

    public function reject(ApprovalRequest $approval, User $actor): void
    {
        $refund = Refund::query()->where('approval_request_id', $approval->id)->lockForUpdate()->firstOrFail();
        if ($refund->status !== RefundStatus::Pending) {
            throw ValidationException::withMessages(['approval' => 'Refund ini sudah diproses.']);
        }

        $before = $refund->toArray();
        $refund->update(['status' => RefundStatus::Rejected]);
        $this->audit->log('refund.rejected', $refund, $before, $refund->fresh()->toArray());
    }

    public function expire(ApprovalRequest $approval): void
    {
        $refund = Refund::query()->where('approval_request_id', $approval->id)->lockForUpdate()->firstOrFail();
        if ($refund->status === RefundStatus::Pending) {
            $refund->update(['status' => RefundStatus::Cancelled]);
        }
    }
}
