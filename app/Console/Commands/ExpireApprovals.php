<?php

namespace App\Console\Commands;

use App\Actions\Approvals\ApprovalEngine;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Console\Command;

class ExpireApprovals extends Command
{
    protected $signature = 'approvals:expire';

    protected $description = 'Expire pending human approval requests that passed their deadline';

    public function handle(ApprovalEngine $engine): int
    {
        $expired = 0;

        ApprovalRequest::query()
            ->where('status', ApprovalStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use ($engine, &$expired) {
                if ($engine->expire(ApprovalRequest::findOrFail($id))) {
                    $expired++;
                }
            });

        $this->info("Expired {$expired} approval request(s).");

        return self::SUCCESS;
    }
}
