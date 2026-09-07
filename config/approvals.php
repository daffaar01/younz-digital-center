<?php

return [
    'refund_owner_threshold' => (int) env('REFUND_OWNER_THRESHOLD', 500_000),
    'expires_after_hours' => (int) env('APPROVAL_EXPIRES_AFTER_HOURS', 24),
    'require_separate_approver' => env('APPROVAL_REQUIRE_SEPARATE_APPROVER', true),
    'allow_second_owner_bootstrap' => env('APPROVAL_ALLOW_SECOND_OWNER_BOOTSTRAP', true),
];
