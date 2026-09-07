<?php

namespace App\Listeners;

use App\Events\ServiceOrderStatusChanged;
use App\Jobs\SendServiceOrderWhatsAppNotification;

class QueueServiceOrderWhatsAppNotification
{
    public function handle(ServiceOrderStatusChanged $event): void
    {
        SendServiceOrderWhatsAppNotification::dispatch(
            $event->orderId,
            $event->historyId,
            $event->previous,
            $event->next,
        );
    }
}
