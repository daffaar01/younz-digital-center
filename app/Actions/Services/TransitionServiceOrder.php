<?php

namespace App\Actions\Services;

use App\Enums\ServiceOrderStatus;
use App\Events\ServiceOrderStatusChanged;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionServiceOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(ServiceOrder $order, ServiceOrderStatus $next, ?User $user, ?string $notes = null): ServiceOrder
    {
        $transition = DB::transaction(function () use ($order, $next, $user, $notes) {
            /** @var ServiceOrder $locked */
            $locked = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $previous = $locked->status;

            if (! $previous->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "Status tidak dapat diubah dari {$previous->label()} ke {$next->label()}.",
                ]);
            }

            if ($next === ServiceOrderStatus::AwaitingCustomer && $locked->estimated_price === null && $locked->final_price === null) {
                throw ValidationException::withMessages(['status' => 'Isi estimasi atau harga akhir sebelum meminta persetujuan pelanggan.']);
            }

            if ($previous === ServiceOrderStatus::AwaitingCustomer
                && $next === ServiceOrderStatus::AwaitingPayment
                && $user?->isStaff()
            ) {
                throw ValidationException::withMessages(['status' => 'Persetujuan estimasi harus dilakukan oleh pelanggan.']);
            }

            if ($previous === ServiceOrderStatus::AwaitingPayment && $next === ServiceOrderStatus::Queued) {
                $payable = $locked->final_price ?? $locked->estimated_price ?? 0;
                if ($locked->paid_amount < $payable) {
                    throw ValidationException::withMessages(['status' => 'Pembayaran harus diverifikasi penuh sebelum pesanan masuk antrean.']);
                }
            }

            $locked->update(['status' => $next]);
            $history = $locked->statusHistories()->create([
                'user_id' => $user?->id,
                'from_status' => $previous->value,
                'to_status' => $next->value,
                'notes' => $notes,
            ]);
            $this->audit->log('service_order.status_changed', $locked, ['status' => $previous->value], ['status' => $next->value]);

            return [$locked->fresh(['customer', 'service', 'files', 'statusHistories.user']), $history->id, $previous];
        });

        [$updated, $historyId, $previous] = $transition;
        ServiceOrderStatusChanged::dispatch($updated->id, $historyId, $previous, $next);

        return $updated;
    }
}
