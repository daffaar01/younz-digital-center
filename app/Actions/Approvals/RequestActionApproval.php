<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Jobs\SendApprovalDiscordNotification;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestActionApproval
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(User $requester, ApprovalType $type, ?Model $subject, array $payload, string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($requester, $type, $subject, $payload, $reason) {
            if ($subject && ApprovalRequest::query()
                ->where('type', $type->value)
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('status', ApprovalStatus::Pending->value)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages(['approval' => 'Masih ada permintaan sejenis yang menunggu persetujuan untuk data ini.']);
            }

            $now = now();
            $approval = ApprovalRequest::create([
                'request_number' => $this->numbers->next('APR'),
                'type' => $type,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'requested_by' => $requester->id,
                'status' => ApprovalStatus::Pending,
                'payload' => $payload,
                'reason' => $reason,
                'requested_at' => $now,
                'expires_at' => $now->copy()->addHours((int) config('approvals.expires_after_hours', 24)),
            ]);
            $approval->events()->create([
                'actor_id' => $requester->id,
                'from_status' => null,
                'to_status' => ApprovalStatus::Pending->value,
                'notes' => $reason,
                'metadata' => ['type' => $type->value],
            ]);
            $this->audit->log('approval.requested', $approval, after: $approval->toArray());

            return $approval;
        }, 3);
    }
}
