<?php

namespace App\Jobs;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Models\TopupOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessTopupOrder implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [65, 120, 300];

    public int $uniqueFor = 300;

    public function __construct(public readonly int $topupOrderId) {}

    public function uniqueId(): string
    {
        return (string) $this->topupOrderId;
    }

    public function handle(DigiflazzClient $client): void
    {
        $lock = Cache::lock('topup-execution:'.$this->topupOrderId, 240);
        if (! $lock->get()) {
            $this->release(30);
            return;
        }
        try {
            $this->process($client);
        } finally {
            $lock->release();
        }
    }

    private ?string $executionToken = null;

    private function applyUpdates(array $updates): bool
    {
        return DB::transaction(function () use ($updates): bool {
            $order = TopupOrder::query()->lockForUpdate()->find($this->topupOrderId);
            if ($order === null || $order->execution_token !== $this->executionToken || $order->payment_status !== TopupPaymentStatus::Paid
                || in_array($order->fulfillment_status, [TopupFulfillmentStatus::Success, TopupFulfillmentStatus::Failed], true)) {
                return false;
            }
            $order->update(array_merge($updates, ['execution_token' => null, 'execution_expires_at' => null]));
            return true;
        });
    }

    private function process(DigiflazzClient $client): void
    {
        $result = DB::transaction(function (): ?array {
            $locked = TopupOrder::query()->lockForUpdate()->find($this->topupOrderId);

            if ($locked === null
                || $locked->payment_status !== TopupPaymentStatus::Paid
                || in_array($locked->fulfillment_status, [TopupFulfillmentStatus::Success, TopupFulfillmentStatus::Failed], true)
            ) {
                return null;
            }

            if ($locked->execution_token !== null && $locked->execution_expires_at !== null
                && \Illuminate\Support\Carbon::parse($locked->execution_expires_at)->isFuture()) {
                return null;
            }
            $this->executionToken = (string) \Illuminate\Support\Str::uuid();
            $locked->update([
                'execution_token' => $this->executionToken,
                'execution_expires_at' => now()->addSeconds(300),
                'fulfillment_status' => TopupFulfillmentStatus::Processing,
                'digiflazz_reference' => $locked->digiflazz_reference ?: $locked->order_number,
            ]);

            $checkStatus = $locked->transaction_type === DigiflazzTransactionType::Postpaid
                && $locked->provider_payment_requested_at !== null;

            if ($locked->transaction_type === DigiflazzTransactionType::Postpaid && ! $checkStatus) {
                $locked->update(['provider_payment_requested_at' => now()]);
            }

            return [$locked->fresh(), $checkStatus];
        });

        if ($result === null) {
            return;
        }

        /** @var TopupOrder $order */
        [$order, $checkStatus] = $result;

        try {
            if ($order->transaction_type === DigiflazzTransactionType::Postpaid) {
                $data = $checkStatus ? $client->statusPostpaid($order) : $client->payPostpaid($order);
            } else {
                $data = $client->topup($order);
            }
        } catch (Throwable $exception) {
            $updates = [
                'fulfillment_status' => TopupFulfillmentStatus::Queued,
                'provider_message' => 'Koneksi ke provider akan dicoba kembali.',
            ];

            if (! $checkStatus && $order->transaction_type === DigiflazzTransactionType::Postpaid
                && $exception instanceof RequestException
                && $exception->response->clientError()
                && ! in_array($exception->response->status(), [408, 429], true)
            ) {
                $updates['provider_payment_requested_at'] = null;
            }

            $this->applyUpdates($updates);

            throw $exception;
        }

        $status = strtolower((string) ($data['status'] ?? 'pending'));
        $updates = [
            'provider_rc' => filled($data['rc'] ?? null) ? (string) $data['rc'] : null,
            'provider_message' => filled($data['message'] ?? null) ? (string) $data['message'] : null,
            'serial_number' => filled($data['sn'] ?? null) ? (string) $data['sn'] : null,
        ];

        $event = null;
        if ($status === 'sukses') {
            $updates['fulfillment_status'] = TopupFulfillmentStatus::Success;
            $updates['fulfilled_at'] = now();
            $event = 'success';
        } elseif ($status === 'gagal') {
            $updates['fulfillment_status'] = TopupFulfillmentStatus::Failed;
            $event = 'failed';
        } else {
            $updates['fulfillment_status'] = TopupFulfillmentStatus::ProviderPending;
        }

        $applied = $this->applyUpdates($updates);
        if ($applied && $event !== null) {
            SendTopupWhatsAppNotification::dispatch($order->id, $event);
            SendTopupDiscordNotification::dispatch($order->id, $event);
        }
    }
}
