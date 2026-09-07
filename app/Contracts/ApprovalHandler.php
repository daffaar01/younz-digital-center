<?php

namespace App\Contracts;

use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\User;

interface ApprovalHandler
{
    public function type(): ApprovalType;

    public function assertCanApprove(ApprovalRequest $approval, User $actor): void;

    public function assertCanReject(ApprovalRequest $approval, User $actor): void;

    public function approve(ApprovalRequest $approval, User $actor): void;

    public function reject(ApprovalRequest $approval, User $actor): void;

    public function expire(ApprovalRequest $approval): void;
}
