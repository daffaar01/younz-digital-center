<?php

namespace App\Listeners;

use App\Events\ServiceOrderStatusChanged;
use App\Jobs\SendServiceOrderDiscordNotification;

class QueueServiceOrderDiscordNotification
{
    public function handle(ServiceOrderStatusChanged $event): void
    {
        SendServiceOrderDiscordNotification::dispatch(
            orderId: $event->orderId,
            event: 'order.status_changed',
            historyId: $event->historyId,
            previous: $event->previous,
            next: $event->next,
        );
    }
}
