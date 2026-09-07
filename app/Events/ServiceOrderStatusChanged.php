<?php

namespace App\Events;

use App\Enums\ServiceOrderStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $historyId,
        public readonly ServiceOrderStatus $previous,
        public readonly ServiceOrderStatus $next,
    ) {}
}
