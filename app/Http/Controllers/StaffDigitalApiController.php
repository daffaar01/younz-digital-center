<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Actions\Topup\ApplyMidtransStatus;
use App\Enums\ApprovalType;
use App\Enums\DigiflazzTransactionType;
use App\Enums\DigitalTransactionStatus;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Integrations\Midtrans\MidtransClient;
use App\Jobs\ProcessTopupOrder;
use App\Jobs\SendTopupDiscordNotification;
use App\Models\Customer;
use App\Models\DigiflazzProduct;
use App\Models\DigitalTransaction;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use App\Support\DigiflazzPricing;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StaffDigitalApiController extends Controller
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
        private readonly RequestActionApproval $requestApproval,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $manual = DigitalTransaction::query()->with(['user:id,name', 'customer:id,name'])->latest()->paginate(20, ['*'], 'manual_page')->withQueryString();
        $topups = TopupOrder::query()->latest()->paginate(20, ['*'], 'topup_page')->withQueryString();
        $products = collect(DigiflazzTransactionType::cases())
            ->flatMap(fn (DigiflazzTransactionType $type) => DigiflazzProduct::query()
                ->available()
                ->where('transaction_type', $type->value)
                ->orderBy('category')
                ->orderBy('brand')
                ->orderBy('selling_price')
                ->limit(500)
                ->get(['id', 'transaction_type', 'product_name', 'category', 'brand', 'selling_price']))
            ->values();

        return response()->json(['data' => [
            'transactions' => collect($manual->items())->map(fn (DigitalTransaction $transaction): array => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'type' => $transaction->type,
                'provider' => $transaction->provider,
                'destination' => $this->mask($transaction->destination),
                'nominal' => $transaction->nominal,
                'cost_price' => $transaction->cost_price,
                'selling_price' => $transaction->selling_price,
                'admin_fee' => $transaction->admin_fee,
                'profit' => $transaction->profit,
                'status' => [
                    'code' => $transaction->status->value,
                    'label' => str($transaction->status->value)->replace('_', ' ')->title()->toString(),
                ],
                'user' => $transaction->user?->name,
                'customer' => $transaction->customer?->name,
            ])->values(),
            'topups' => collect($topups->items())->map(fn (TopupOrder $topup): array => [
                'order_number' => $topup->order_number,
                'product_name' => $topup->product_name,
                'category' => $topup->category,
                'brand' => $topup->brand,
                'destination' => $topup->maskedDestination(),
                'total_amount' => $topup->total_amount,
                'cost_price' => $topup->cost_price,
                'profit' => $topup->total_amount - $topup->cost_price,
                'source' => $this->source($topup)['label'],
                'source_code' => $this->source($topup)['code'],
                'transaction_type' => $topup->transaction_type->value,
                'provider_customer_name' => $topup->provider_customer_name,
                'has_payment_url' => filled($topup->midtrans_redirect_url),
                'recovery_url' => $topup->temporarySignedUrl('topup.show'),
                'receipt_url' => $topup->paid_at !== null ? $topup->temporarySignedUrl('topup.receipt') : null,
                'receipt_pdf_url' => $topup->paid_at !== null ? $topup->receiptPdfUrl() : null,
                'invoice_url' => $topup->paid_at !== null ? $topup->temporarySignedUrl('topup.invoice') : null,
                'payment_status' => ['code' => $topup->payment_status->value, 'label' => $topup->payment_status->label()],
                'fulfillment_status' => ['code' => $topup->fulfillment_status->value, 'label' => $topup->fulfillment_status->label()],
                'provider_rc' => $topup->provider_rc,
                'created_at' => $topup->created_at?->toIso8601String(),
            ])->values(),
            'topup_products' => $products,
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'types' => collect(['pulsa', 'paket_data', 'ewallet', 'voucher_game', 'token_pln', 'pln_pascabayar', 'pdam', 'internet'])
                ->map(fn (string $type): array => ['code' => $type, 'label' => str($type)->replace('_', ' ')->title()->toString()])
                ->values(),
            'pagination' => [
                'manual' => ['current_page' => $manual->currentPage(), 'last_page' => $manual->lastPage(), 'total' => $manual->total()],
                'topup' => ['current_page' => $topups->currentPage(), 'last_page' => $topups->lastPage(), 'total' => $topups->total()],
            ],
        ]]);
    }

    public function createOfflineTopup(
        Request $request,
        DigiflazzClient $digiflazz,
        DigiflazzPricing $pricing,
    ): JsonResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:digiflazz_products,id'],
            'destination' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9.+\s\-_]+$/', 'same:destination_confirmation'],
            'destination_confirmation' => ['required', 'string', 'max:40'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s-]{9,20}$/'],
            'idempotency_key' => ['required', 'uuid'],
        ], [
            'destination.same' => 'Konfirmasi nomor tujuan harus sama.',
            'destination.regex' => 'Nomor atau ID tujuan mengandung karakter yang tidak didukung.',
        ]);
        $product = DigiflazzProduct::query()->available()->find((int) $data['product_id']);
        if ($product === null) {
            throw ValidationException::withMessages(['product_id' => 'Produk top up sudah tidak tersedia.']);
        }

        $destination = $this->normalizeDestination($product, $data['destination']);
        $this->validateDestination($product, $destination);
        $phone = (string) preg_replace('/\D+/', '', $data['customer_phone']);
        $fingerprint = hash('sha256', json_encode([
            (int) $data['product_id'],
            mb_strtolower($destination),
            mb_strtolower($data['customer_name']),
            $phone,
        ], JSON_THROW_ON_ERROR));
        $existing = TopupOrder::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['idempotency_key' => 'Kunci idempotency sudah digunakan untuk data transaksi berbeda.']);
            }

            return response()->json([
                'message' => "Permintaan duplikat tidak diproses ulang ({$existing->order_number}).",
                'data' => $this->topupVerification($existing),
            ]);
        }
        $order = DB::transaction(function () use ($data, $destination, $fingerprint, $phone, $pricing, $request): TopupOrder {
            $existing = TopupOrder::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Kunci idempotency sudah digunakan untuk data transaksi berbeda.']);
                }

                return $existing;
            }

            $product = DigiflazzProduct::query()
                ->available()
                ->lockForUpdate()
                ->find((int) $data['product_id']);
            if ($product === null) {
                throw ValidationException::withMessages(['product_id' => 'Produk top up sudah tidak tersedia.']);
            }

            $orderNumber = $this->numbers->next('TOP');
            $isPostpaid = $product->transaction_type === DigiflazzTransactionType::Postpaid;
            $adminFee = $isPostpaid ? $pricing->postpaidAdminFee() : 0;

            return TopupOrder::create([
                'transaction_type' => $product->transaction_type,
                'order_number' => $orderNumber,
                'public_token' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'digiflazz_product_id' => $product->id,
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'source_reference' => 'offline:'.$request->user()->id.':'.$data['idempotency_key'],
                'sku' => $product->buyer_sku_code,
                'product_name' => $product->product_name,
                'category' => $product->category,
                'brand' => $product->brand,
                'destination' => $destination,
                'customer_name' => $data['customer_name'],
                'customer_email' => 'offline-'.$orderNumber.'@example.invalid',
                'customer_phone' => $phone,
                'cost_price' => $isPostpaid ? 0 : $product->cost_price,
                'selling_price' => $isPostpaid ? 0 : $product->selling_price,
                'admin_fee' => $adminFee,
                'total_amount' => $isPostpaid ? 0 : $product->selling_price + $adminFee,
                'payment_status' => TopupPaymentStatus::Pending,
                'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
                'midtrans_order_id' => $orderNumber,
                'digiflazz_reference' => $orderNumber,
            ]);
        }, 3);

        if ($order->transaction_type === DigiflazzTransactionType::Postpaid && $order->inquired_at === null) {
            try {
                $order = $this->inquireOfflinePostpaid($order, $digiflazz);
            } catch (Throwable $exception) {
                if ($order->payment_status === TopupPaymentStatus::Pending
                    && $order->inquired_at === null
                    && $order->total_amount === 0
                ) {
                    $order->delete();
                }

                if ($exception instanceof ValidationException) {
                    throw $exception;
                }

                report($exception);
                throw ValidationException::withMessages([
                    'destination' => $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'Tagihan belum dapat diperiksa. Silakan coba lagi.',
                ]);
            }
        }

        $this->audit->log('topup.offline_created', $order, metadata: [
            'order_number' => $order->order_number,
            'sku' => $order->sku,
            'total_amount' => $order->total_amount,
        ]);

        return response()->json([
            'message' => $order->transaction_type === DigiflazzTransactionType::Postpaid
                ? 'Tagihan '.$order->provider_customer_name.' ditemukan. Total Rp '.number_format($order->total_amount, 0, ',', '.').'. Terima pembayaran tunai lalu verifikasi.'
                : 'Top Up toko dibuat. Verifikasi pembayaran setelah uang diterima.',
            'data' => $this->topupVerification($order),
        ], 201);
    }

    public function verifyTopupPayment(
        Request $request,
        string $orderNumber,
        MidtransClient $midtrans,
        ApplyMidtransStatus $applyStatus,
    ): JsonResponse {
        $this->authorizeAccess($request);
        abort_unless($request->user()->hasRole('owner', 'admin'), 403);
        $order = TopupOrder::query()->where('order_number', $orderNumber)->firstOrFail();

        if ($this->isOffline($order) || $request->boolean('manual_override')) {
            $request->validate(['confirmed' => ['required', 'accepted']]);
            if ($order->transaction_type === DigiflazzTransactionType::Postpaid
                && ($order->inquired_at === null || $order->total_amount <= 0)
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan pascabayar belum berhasil diperiksa dan tidak boleh dibayar.',
                ]);
            }
            $becamePaid = DB::transaction(function () use ($order): bool {
                $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->payment_status === TopupPaymentStatus::Paid) {
                    return false;
                }
                if (! in_array($locked->payment_status, [TopupPaymentStatus::Pending, TopupPaymentStatus::Expired, TopupPaymentStatus::Failed], true)) {
                    throw ValidationException::withMessages(['payment' => 'Status pembayaran tidak dapat diverifikasi lagi.']);
                }

                $locked->update([
                    'payment_status' => TopupPaymentStatus::Paid,
                    'fulfillment_status' => TopupFulfillmentStatus::Queued,
                    'paid_at' => now(),
                    'provider_payment_requested_at' => null,
                ]);

                return true;
            });
            $order->refresh();
            if ($becamePaid) {
                SendTopupDiscordNotification::dispatch($order->id, 'paid');
                ProcessTopupOrder::dispatch($order->id);
            }
            $this->audit->log('topup.manual_payment_verified', $order, metadata: [
                'order_number' => $order->order_number,
                'manual_override' => $request->boolean('manual_override'),
                'payment_transitioned' => $becamePaid,
            ]);

            return response()->json([
                'message' => $becamePaid
                    ? 'Pembayaran manual terverifikasi. Top Up sedang dikirim ke provider.'
                    : 'Pembayaran sudah terverifikasi. Antrean Top Up diperiksa kembali.',
                'data' => $this->topupVerification($order),
            ]);
        }

        if ($order->payment_status === TopupPaymentStatus::Paid) {
            $providerCheck = $order->fulfillment_status === TopupFulfillmentStatus::ProviderPending;
            if (in_array($order->fulfillment_status, [
                TopupFulfillmentStatus::Queued,
                TopupFulfillmentStatus::ProviderPending,
            ], true)) {
                ProcessTopupOrder::dispatch($order->id);
                $order->refresh();
            }
            $this->audit->log('topup.midtrans_reverified', $order, metadata: [
                'transaction_status' => $order->midtrans_status,
                'already_paid' => true,
                'provider_status_checked' => $providerCheck,
            ]);

            return response()->json([
                'message' => $providerCheck
                    ? match ($order->fulfillment_status) {
                        TopupFulfillmentStatus::Success => 'Provider mengonfirmasi Top Up berhasil.',
                        TopupFulfillmentStatus::Failed => 'Provider mengonfirmasi Top Up gagal.',
                        default => 'Status provider sudah diperiksa dan masih menunggu proses.',
                    }
                    : 'Pembayaran sudah terverifikasi. Antrean fulfillment diperiksa kembali.',
                'data' => $this->topupVerification($order),
            ]);
        }

        if (! $midtrans->isConfigured()) {
            return response()->json(['message' => 'Integrasi Midtrans belum dikonfigurasi.'], 503);
        }

        try {
            $payload = $midtrans->getTransactionStatus($order);
            $becamePaid = $applyStatus->handle($order, $payload);
            $order->refresh();
            $this->audit->log('topup.midtrans_reverified', $order, metadata: [
                'transaction_status' => (string) ($payload['transaction_status'] ?? ''),
                'payment_transitioned' => $becamePaid,
            ]);

            return response()->json([
                'message' => $becamePaid
                    ? 'Pembayaran berhasil diverifikasi dan diproses.'
                    : 'Status pembayaran sudah diperbarui dari Midtrans.',
                'data' => $this->topupVerification($order),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $missingTransaction = $exception instanceof RuntimeException
                && $exception->getMessage() === 'Transaksi belum tercatat di Midtrans.';
            $invalidStatusPayload = $exception instanceof RuntimeException
                && $exception->getMessage() === 'Respons status transaksi Midtrans tidak valid.';

            return response()->json([
                'message' => match (true) {
                    $missingTransaction => 'Transaksi pembayaran belum terbentuk di Midtrans. Gunakan tombol Buat pembayaran terlebih dahulu.',
                    $invalidStatusPayload => 'Data status dari Midtrans tidak cocok dengan pesanan ini. Pastikan mode dan Server Key Midtrans sesuai, lalu coba lagi.',
                    default => 'Status pembayaran belum dapat diverifikasi dari Midtrans. Silakan coba lagi.',
                },
                'error_code' => match (true) {
                    $missingTransaction => 'MIDTRANS_TRANSACTION_MISSING',
                    $invalidStatusPayload => 'MIDTRANS_STATUS_INVALID',
                    default => 'MIDTRANS_STATUS_UNAVAILABLE',
                },
            ], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'type' => ['required', 'in:pulsa,paket_data,ewallet,voucher_game,token_pln,pln_pascabayar,pdam,internet'],
            'provider' => ['nullable', 'string', 'max:100'],
            'destination' => ['required', 'string', 'max:100', 'confirmed'],
            'nominal' => ['required', 'integer', 'min:0'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'admin_fee' => ['nullable', 'integer', 'min:0'],
            'provider_reference' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:diproses,berhasil,gagal'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $key = mb_strtolower($data['idempotency_key']);
        $fingerprintData = $data;
        unset($fingerprintData['idempotency_key']);
        ksort($fingerprintData);
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $existing = DigitalTransaction::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['idempotency_key' => 'Kunci idempotency sudah digunakan untuk payload yang berbeda.']);
            }

            return response()->json([
                'message' => "Permintaan duplikat tidak diproses ulang ({$existing->transaction_number}).",
                'data' => ['transaction_number' => $existing->transaction_number],
            ]);
        }
        $target = $data['status'];
        unset($data['status'], $data['idempotency_key']);

        try {
            $transaction = DB::transaction(fn (): DigitalTransaction => DigitalTransaction::create([
                ...$data,
                'transaction_number' => $this->numbers->next('DIG'),
                'user_id' => $request->user()->id,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'status' => DigitalTransactionStatus::Pending,
            ]), 3);
        } catch (QueryException) {
            $existing = DigitalTransaction::query()->where('idempotency_key', $key)->firstOrFail();

            return response()->json([
                'message' => "Permintaan duplikat tidak diproses ulang ({$existing->transaction_number}).",
                'data' => ['transaction_number' => $existing->transaction_number],
            ]);
        }
        $this->audit->log('digital_transaction.created', $transaction, after: $transaction->toArray());
        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::DigitalTransaction,
            $transaction,
            ['target_status' => $target],
            "Pemrosesan {$transaction->type} ke {$transaction->destination}.",
        );

        return response()->json([
            'message' => "Transaksi dicatat dan menunggu persetujuan ({$approval->request_number}).",
            'data' => ['transaction_number' => $transaction->transaction_number],
            'approval' => ['id' => $approval->id, 'request_number' => $approval->request_number],
        ], 202);
    }

    private function normalizeDestination(DigiflazzProduct $product, string $destination): string
    {
        $text = mb_strtolower($product->category.' '.$product->product_name);

        return $product->transaction_type === DigiflazzTransactionType::Postpaid
            || str_contains($text, 'pulsa') || str_contains($text, 'data') || str_contains($text, 'internet') || str_contains($text, 'pln')
            ? (string) preg_replace('/\D+/', '', $destination)
            : $destination;
    }

    private function validateDestination(DigiflazzProduct $product, string $destination): void
    {
        $text = mb_strtolower($product->category.' '.$product->product_name);
        if ($product->transaction_type === DigiflazzTransactionType::Postpaid
            && (! ctype_digit($destination) || strlen($destination) < 4 || strlen($destination) > 20)
        ) {
            throw ValidationException::withMessages(['destination' => 'ID pelanggan pascabayar harus berisi 4 sampai 20 digit angka.']);
        }
        if ((str_contains($text, 'pulsa') || str_contains($text, 'data') || str_contains($text, 'internet'))
            && (! ctype_digit($destination) || strlen($destination) < 9 || strlen($destination) > 15)) {
            throw ValidationException::withMessages(['destination' => 'Nomor HP harus berisi 9 sampai 15 digit.']);
        }
        if (str_contains($text, 'pln') && (! ctype_digit($destination) || strlen($destination) < 6 || strlen($destination) > 20)) {
            throw ValidationException::withMessages(['destination' => 'Nomor meter harus berisi 6 sampai 20 digit angka.']);
        }
    }

    private function inquireOfflinePostpaid(TopupOrder $order, DigiflazzClient $digiflazz): TopupOrder
    {
        $inquiry = $digiflazz->inquirePostpaid($order);
        $referenceIsDuplicate = (string) ($inquiry['rc'] ?? '') === '49'
            || str_contains(mb_strtolower((string) ($inquiry['message'] ?? '')), 'ref id tidak unik');

        if ($referenceIsDuplicate) {
            $order->update([
                'digiflazz_reference' => Str::limit($order->order_number.'-'.Str::lower(Str::random(8)), 255, ''),
            ]);
            $inquiry = $digiflazz->inquirePostpaid($order->fresh());
        }

        $status = mb_strtolower(trim((string) ($inquiry['status'] ?? '')));
        $cost = max(0, (int) ($inquiry['price'] ?? 0));
        $sellingPrice = max(0, (int) ($inquiry['selling_price'] ?? 0));
        $order->update([
            'provider_rc' => filled($inquiry['rc'] ?? null) ? (string) $inquiry['rc'] : null,
            'provider_message' => filled($inquiry['message'] ?? null) ? (string) $inquiry['message'] : null,
        ]);

        if ($status !== 'sukses' || $cost <= 0 || $sellingPrice <= 0) {
            throw ValidationException::withMessages([
                'destination' => filled($inquiry['message'] ?? null)
                    ? (string) $inquiry['message']
                    : 'Tagihan belum ditemukan. Periksa ID pelanggan lalu coba lagi.',
            ]);
        }

        $details = $inquiry['desc'] ?? null;
        $order->update([
            'provider_customer_name' => filled($inquiry['customer_name'] ?? null) ? (string) $inquiry['customer_name'] : 'Pelanggan',
            'bill_details' => is_array($details) ? $details : (filled($details) ? ['description' => (string) $details] : null),
            'cost_price' => $cost,
            'selling_price' => $sellingPrice,
            'total_amount' => $sellingPrice + $order->admin_fee,
            'inquired_at' => now(),
        ]);

        return $order->fresh();
    }

    private function isOffline(TopupOrder $order): bool
    {
        return str_starts_with((string) $order->source_reference, 'offline:');
    }

    private function source(TopupOrder $order): array
    {
        return match (true) {
            $this->isOffline($order) => ['code' => 'offline', 'label' => 'Toko offline'],
            str_starts_with((string) $order->source_reference, 'whatsapp:') => ['code' => 'whatsapp', 'label' => 'WhatsApp'],
            str_starts_with((string) $order->source_reference, 'agent:') => ['code' => 'agent', 'label' => 'Agent PPOB'],
            str_starts_with((string) $order->source_reference, 'legacy:sampitmart:') => ['code' => 'legacy_sampitmart', 'label' => 'Riwayat SampitMart'],
            default => ['code' => 'web', 'label' => 'Web'],
        };
    }

    private function topupVerification(TopupOrder $order): array
    {
        return [
            'order_number' => $order->order_number,
            'source' => $this->source($order),
            'transaction_type' => $order->transaction_type->value,
            'provider_customer_name' => $order->provider_customer_name,
            'total_amount' => $order->total_amount,
            'payment_status' => ['code' => $order->payment_status->value, 'label' => $order->payment_status->label()],
            'fulfillment_status' => ['code' => $order->fulfillment_status->value, 'label' => $order->fulfillment_status->label()],
            'midtrans_status' => $order->midtrans_status,
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isStaff()
                && $request->user()->tokenCan('staff:digital')
                && $request->user()->hasRole('owner', 'admin', 'cashier'),
            403,
        );
    }

    private function mask(?string $value): string
    {
        $value = (string) $value;
        $length = mb_strlen($value);

        return $length <= 5
            ? str_repeat('*', max(0, $length - 2)).mb_substr($value, -2)
            : mb_substr($value, 0, 3).str_repeat('*', max(3, $length - 6)).mb_substr($value, -3);
    }
}
