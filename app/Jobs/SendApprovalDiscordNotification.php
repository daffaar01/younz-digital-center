<?php

namespace App\Jobs;

use App\Enums\ApprovalStatus;
use App\Integrations\Discord\DiscordNotifier;
use App\Models\ApprovalRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendApprovalDiscordNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $approvalId, public readonly string $event = 'approval.pending')
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "approval:{$this->approvalId}:{$this->event}:discord";
    }

    public function handle(DiscordNotifier $notifier): void
    {
        $approval = ApprovalRequest::query()->with('requester')->find($this->approvalId);
        if ($approval === null) {
            return;
        }

        $title = match ($this->event) {
            'approval.pending' => 'Approval Menunggu',
            'approval.approved' => 'Approval Disetujui',
            'approval.rejected' => 'Approval Ditolak',
            'approval.expired' => 'Approval Kedaluwarsa',
            default => 'Pembaruan Approval',
        };

        $message = "{$approval->request_number}: {$approval->type->label()}.";
        $notifier->notify(
            event: $this->event,
            title: $title,
            message: $message,
            fields: [
                ['name' => 'Jenis', 'value' => $approval->type->label(), 'inline' => true],
                ['name' => 'Status', 'value' => $approval->status->label(), 'inline' => true],
                ['name' => 'Pemohon', 'value' => $approval->requester?->name ?? 'Sistem', 'inline' => true],
                ['name' => 'Alasan', 'value' => $approval->reason ?: '-', 'inline' => false],
            ],
            reference: $approval->request_number,
            mentionStaff: $this->event === 'approval.pending',
        );
    }
}
