<?php

namespace App\Console\Commands;

use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Jobs\ProcessTopupOrder;
use App\Models\TopupOrder;
use Illuminate\Console\Command;
use Throwable;

class RecoverTopupFulfillment extends Command
{
    protected $signature = 'topup:recover-fulfillment';

    protected $description = 'Recover paid topups that still need provider processing, independently of Midtrans.';

    public function handle(): int
    {
        if (! config('services.digiflazz.enabled')) {
            $this->warn('Digiflazz disabled; no jobs dispatched.');
            return self::SUCCESS;
        }
        $failed = false;
        \Illuminate\Support\Facades\DB::table('topup_fulfillment_outbox')->whereNull('published_at')
            ->orderBy('id')->chunkById(100, function ($entries) use (&$failed): void {
                foreach ($entries as $entry) {
                    try {
                        $order = TopupOrder::find($entry->topup_order_id);
                        if ($order !== null && $order->payment_status === TopupPaymentStatus::Paid
                            && ! in_array($order->fulfillment_status, [TopupFulfillmentStatus::Success, TopupFulfillmentStatus::Failed], true)) {
                            \Illuminate\Support\Facades\Bus::dispatch(new ProcessTopupOrder($order->id));
                        }
                        \Illuminate\Support\Facades\DB::table('topup_fulfillment_outbox')->where('id', $entry->id)->where('generation', $entry->generation)->update(['published_at' => now(), 'updated_at' => now()]);
                    } catch (Throwable $error) {
                        report($error);
                        $failed = true;
                    }
                }
            });
        TopupOrder::query()->where('payment_status', TopupPaymentStatus::Paid->value)
            ->whereIn('fulfillment_status', [TopupFulfillmentStatus::Queued->value, TopupFulfillmentStatus::ProviderPending->value, TopupFulfillmentStatus::Processing->value])
            ->where('updated_at', '<', now()->subMinutes(10))
            ->select('id')->chunkById(100, function ($orders) use (&$failed): void {
                foreach ($orders as $order) {
                    try {
                        \Illuminate\Support\Facades\Bus::dispatch(new ProcessTopupOrder($order->id));
                    } catch (Throwable $error) {
                        report($error);
                        $failed = true;
                    }
                }
            });
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
