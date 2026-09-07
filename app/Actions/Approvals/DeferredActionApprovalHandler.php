<?php

namespace App\Actions\Approvals;

use App\Actions\Inventory\AdjustStock;
use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalType;
use App\Enums\DigitalTransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\DigitalTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeferredActionApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly ApprovalType $approvalType,
        private readonly AdjustStock $adjustStock,
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    public function type(): ApprovalType
    {
        return $this->approvalType;
    }

    public function assertCanApprove(ApprovalRequest $approval, User $actor): void
    {
        if ($this->approvalType === ApprovalType::EmployeeAccessChange) {
            abort_unless($actor->hasRole('owner'), 403);
        } else {
            abort_unless($actor->hasRole('owner', 'admin'), 403);
        }

        if (config('approvals.require_separate_approver', true)
            && $approval->requested_by === $actor->id
            && ! $this->isSecondOwnerBootstrap($approval, $actor)
        ) {
            throw ValidationException::withMessages(['approval' => 'Pemohon tidak boleh menyetujui permintaannya sendiri.']);
        }
    }

    public function assertCanReject(ApprovalRequest $approval, User $actor): void
    {
        if ($this->approvalType === ApprovalType::EmployeeAccessChange) {
            abort_unless($actor->hasRole('owner'), 403);
        } else {
            abort_unless($actor->hasRole('owner', 'admin'), 403);
        }
    }

    public function approve(ApprovalRequest $approval, User $actor): void
    {
        switch ($this->approvalType) {
            case ApprovalType::ProductPriceChange:
                $this->approveProduct($approval);
                break;
            case ApprovalType::StockAdjustment:
                $this->approveStock($approval, $actor);
                break;
            case ApprovalType::EmployeeAccessChange:
                $this->approveAccess($approval);
                break;
            case ApprovalType::DigitalTransaction:
                $this->approveDigital($approval);
                break;
            case ApprovalType::FinancialExpense:
                $this->approveExpense($approval);
                break;
            default:
                throw new \LogicException('Jenis deferred approval tidak didukung.');
        }
    }

    public function reject(ApprovalRequest $approval, User $actor): void
    {
        $this->cancelDigitalIfNeeded($approval);
    }

    public function expire(ApprovalRequest $approval): void
    {
        $this->cancelDigitalIfNeeded($approval);
    }

    private function approveProduct(ApprovalRequest $approval): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($approval->subject_id);
        $before = $product->toArray();
        $product->update((array) data_get($approval->payload, 'data', []));
        $this->audit->log('product.price_change_approved', $product, $before, $product->fresh()->toArray());
    }

    private function approveStock(ApprovalRequest $approval, User $actor): void
    {
        $product = Product::query()->findOrFail($approval->subject_id);
        $movement = $this->adjustStock->handle(
            $product,
            (int) data_get($approval->payload, 'quantity'),
            (string) data_get($approval->payload, 'movement_type'),
            $actor,
            (string) data_get($approval->payload, 'reason'),
            $approval,
        );
        $this->audit->log('stock.adjustment_approved', $movement, ['stock' => $movement->stock_before], ['stock' => $movement->stock_after]);
    }

    private function approveAccess(ApprovalRequest $approval): void
    {
        $employee = User::query()->lockForUpdate()->findOrFail($approval->subject_id);
        $before = $employee->only(['role', 'is_active']);
        $employee->update((array) data_get($approval->payload, 'data', []));
        $employee->tokens()->delete();
        DB::table('sessions')->where('user_id', $employee->id)->delete();
        $this->audit->log('employee.access_change_approved', $employee, $before, $employee->fresh()->only(['role', 'is_active']));
    }

    private function isSecondOwnerBootstrap(ApprovalRequest $approval, User $actor): bool
    {
        if ($this->approvalType !== ApprovalType::EmployeeAccessChange
            || ! config('approvals.allow_second_owner_bootstrap', true)
            || data_get($approval->payload, 'data.role') !== 'owner'
            || ! data_get($approval->payload, 'data.is_active')
            || $approval->subject_id === $actor->id
        ) {
            return false;
        }

        return User::query()
            ->where('role', 'owner')
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->doesntExist();
    }

    private function approveDigital(ApprovalRequest $approval): void
    {
        $transaction = DigitalTransaction::query()->lockForUpdate()->findOrFail($approval->subject_id);
        $before = $transaction->toArray();
        $target = DigitalTransactionStatus::from((string) data_get($approval->payload, 'target_status'));
        $transaction->update(['status' => $target]);
        $this->audit->log('digital_transaction.approved', $transaction, $before, $transaction->fresh()->toArray());
    }

    private function approveExpense(ApprovalRequest $approval): void
    {
        $data = (array) data_get($approval->payload, 'data', []);
        $expense = Expense::create([
            ...$data,
            'expense_number' => $this->numbers->next('EXP'),
            'user_id' => $approval->requested_by,
        ]);
        $this->audit->log('expense.approved', $expense, after: $expense->toArray());
    }

    private function cancelDigitalIfNeeded(ApprovalRequest $approval): void
    {
        if ($this->approvalType !== ApprovalType::DigitalTransaction) {
            return;
        }

        $transaction = DigitalTransaction::query()->lockForUpdate()->findOrFail($approval->subject_id);
        if ($transaction->status === DigitalTransactionStatus::Pending) {
            $before = $transaction->toArray();
            $transaction->update(['status' => DigitalTransactionStatus::Cancelled]);
            $this->audit->log('digital_transaction.cancelled', $transaction, $before, $transaction->fresh()->toArray());
        }
    }
}
