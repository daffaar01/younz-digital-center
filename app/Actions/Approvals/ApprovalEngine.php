<?php

namespace App\Actions\Approvals;

use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class ApprovalEngine
{
    /** @var array<string, ApprovalHandler> */
    private array $handlers;

    /** @param iterable<ApprovalHandler> $handlers */
    public function __construct(
        iterable $handlers,
        private readonly AuditLogger $audit,
    ) {
        $this->handlers = [];
        foreach ($handlers as $handler) {
            $this->handlers[$handler->type()->value] = $handler;
        }
    }

    public function approve(ApprovalRequest $approval, User $actor, ?string $notes = null): ApprovalRequest
    {
        $result = DB::transaction(function () use ($approval, $actor, $notes) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            $handler = $this->handler($locked);

            if ($expired = $this->expireIfNeeded($locked, $handler)) {
                return ['approval' => $expired, 'expired' => true];
            }

            $this->assertPending($locked);
            $handler->assertCanApprove($locked, $actor);
            $before = $locked->toArray();
            $handler->approve($locked, $actor);
            $locked->update([
                'status' => ApprovalStatus::Approved,
                'decided_by' => $actor->id,
                'decision_notes' => $notes,
                'decided_at' => now(),
            ]);
            $this->event($locked, $actor, ApprovalStatus::Pending, ApprovalStatus::Approved, $notes);
            $this->audit->log('approval.approved', $locked, $before, $locked->fresh()->toArray());

            return ['approval' => $locked->fresh(), 'expired' => false];
        }, 3);

        if ($result['expired']) {
            throw ValidationException::withMessages(['approval' => 'Permintaan persetujuan sudah kedaluwarsa.']);
        }

        return $result['approval'];
    }

    public function reject(ApprovalRequest $approval, User $actor, string $notes): ApprovalRequest
    {
        $result = DB::transaction(function () use ($approval, $actor, $notes) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            $handler = $this->handler($locked);

            if ($expired = $this->expireIfNeeded($locked, $handler)) {
                return ['approval' => $expired, 'expired' => true];
            }

            $this->assertPending($locked);
            $handler->assertCanReject($locked, $actor);
            $before = $locked->toArray();
            $handler->reject($locked, $actor);
            $locked->update([
                'status' => ApprovalStatus::Rejected,
                'decided_by' => $actor->id,
                'decision_notes' => $notes,
                'decided_at' => now(),
            ]);
            $this->event($locked, $actor, ApprovalStatus::Pending, ApprovalStatus::Rejected, $notes);
            $this->audit->log('approval.rejected', $locked, $before, $locked->fresh()->toArray());

            return ['approval' => $locked->fresh(), 'expired' => false];
        }, 3);

        if ($result['expired']) {
            throw ValidationException::withMessages(['approval' => 'Permintaan persetujuan sudah kedaluwarsa.']);
        }

        return $result['approval'];
    }

    public function expire(ApprovalRequest $approval): bool
    {
        return DB::transaction(function () use ($approval) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);

            return $this->expireIfNeeded($locked, $this->handler($locked)) !== null;
        }, 3);
    }

    private function handler(ApprovalRequest $approval): ApprovalHandler
    {
        return $this->handlers[$approval->type->value]
            ?? throw new LogicException("Approval handler untuk {$approval->type->value} tidak tersedia.");
    }

    private function assertPending(ApprovalRequest $approval): void
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval' => 'Permintaan persetujuan ini sudah diproses.']);
        }
    }

    private function expireIfNeeded(ApprovalRequest $approval, ApprovalHandler $handler): ?ApprovalRequest
    {
        if ($approval->status !== ApprovalStatus::Pending || ! $approval->isExpired()) {
            return null;
        }

        $before = $approval->toArray();
        $handler->expire($approval);
        $approval->update(['status' => ApprovalStatus::Expired, 'decided_at' => now()]);
        $this->event($approval, null, ApprovalStatus::Pending, ApprovalStatus::Expired, 'Kedaluwarsa otomatis.');
        $this->audit->log('approval.expired', $approval, $before, $approval->fresh()->toArray());

        return $approval->fresh();
    }

    private function event(ApprovalRequest $approval, ?User $actor, ApprovalStatus $from, ApprovalStatus $to, ?string $notes): void
    {
        $approval->events()->create([
            'actor_id' => $actor?->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'notes' => $notes,
        ]);
    }
}
