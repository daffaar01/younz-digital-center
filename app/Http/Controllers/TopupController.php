<?php

namespace App\Http\Controllers;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\Digiflazz\DigiflazzClient;
use App\Integrations\Midtrans\MidtransClient;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use App\Support\DigiflazzPricing;
use App\Support\DocumentNumberGenerator;
use App\Support\TopupReceiptPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TopupController extends Controller
{
    public function index(Request $request, DigiflazzClient $digiflazz, MidtransClient $midtrans): View
    {
        $mode = DigiflazzTransactionType::tryFrom((string) $request->query('mode', DigiflazzTransactionType::Prepaid->value))
            ?? DigiflazzTransactionType::Prepaid;
        $query = DigiflazzProduct::query()->available()->where('transaction_type', $mode->value);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('product_name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->query('category'))) {
            $query->where('category', $category);
        }

        if ($brand = trim((string) $request->query('brand'))) {
            $query->where('brand', $brand);
        }

        return view('public.topup.index', [
            'products' => $query->orderBy('category')->orderBy('brand')->orderBy('selling_price')->paginate(18)->withQueryString(),
            'categories' => DigiflazzProduct::query()->available()->where('transaction_type', $mode->value)->distinct()->orderBy('category')->pluck('category'),
            'brands' => DigiflazzProduct::query()->available()->where('transaction_type', $mode->value)->distinct()->orderBy('brand')->pluck('brand'),
            'integrationsReady' => $digiflazz->isConfigured() && $midtrans->isConfigured(),
            'mode' => $mode,
        ]);
    }

    public function catalogApi(Request $request, DigiflazzClient $digiflazz, MidtransClient $midtrans): JsonResponse
    {
        $mode = DigiflazzTransactionType::tryFrom((string) $request->query('mode', DigiflazzTransactionType::Prepaid->value))
            ?? DigiflazzTransactionType::Prepaid;
        $query = DigiflazzProduct::query()->available()->where('transaction_type', $mode->value);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('product_name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }
        if ($category = trim((string) $request->query('category'))) {
            $query->where('category', $category);
        }
        if ($brand = trim((string) $request->query('brand'))) {
            $query->where('brand', $brand);
        }

        $products = $query->orderBy('category')->orderBy('brand')->orderBy('selling_price')
            ->limit(500)
            ->get(['id', 'transaction_type', 'product_name', 'category', 'brand', 'description', 'selling_price'])
            ->map(function (DigiflazzProduct $product): DigiflazzProduct {
                $product->setAttribute('form_type', $this->productFormType($product));

                return $product;
            });

        return response()->json(['data' => [
            'products' => $products,
            'categories' => DigiflazzProduct::query()->available()->where('transaction_type', $mode->value)->distinct()->orderBy('category')->pluck('category'),
            'brands' => DigiflazzProduct::query()->available()->where('transaction_type', $mode->value)->distinct()->orderBy('brand')->pluck('brand'),
            'mode' => ['value' => $mode->value, 'label' => $mode->label()],
            'integrations_ready' => $digiflazz->isConfigured() && $midtrans->isConfigured(),
            'postpaid_admin_fee' => (int) config('services.digiflazz.postpaid_admin_fee'),
        ]]);
    }

    public function storeApi(
        Request $request,
        DocumentNumberGenerator $numbers,
        MidtransClient $midtrans,
        DigiflazzClient $digiflazz,
        AuditLogger $audit,
        DigiflazzPricing $pricing,
    ): JsonResponse {
        if (! $digiflazz->isConfigured() || ! $midtrans->isConfigured()) {
            return response()->json(['message' => 'Layanan top up sedang disiapkan.'], 503);
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'exists:digiflazz_products,id'],
            'destination' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9.+\s\-_]+$/', 'same:destination_confirmation'],
            'destination_confirmation' => ['required', 'string', 'max:40'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_email' => ['required', 'email:rfc', 'max:150'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s-]{9,20}$/'],
            'terms' => ['accepted'],
        ], [
            'destination.same' => 'Konfirmasi nomor tujuan harus sama.',
            'destination.regex' => 'Nomor atau ID tujuan mengandung karakter yang tidak didukung.',
            'terms.accepted' => 'Anda harus menyetujui pengecekan nomor tujuan dan syarat layanan.',
        ]);
        $this->validateProductDestination($validated);

        $order = $this->createTopupOrder($request, $validated, $numbers, $pricing);
        $audit->log('topup.created', $order, metadata: [
            'order_number' => $order->order_number,
            'sku' => $order->sku,
            'total_amount' => $order->total_amount,
        ]);

        try {
            $isPostpaid = $order->transaction_type === DigiflazzTransactionType::Postpaid;
            $order = $this->prepareOrder($order, $midtrans, $digiflazz, createPayment: ! $isPostpaid);

            if ($isPostpaid) {
                $audit->log('topup.postpaid_inquired', $order, metadata: [
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                ]);
            }

            return response()->json([
                'message' => $isPostpaid
                    ? 'Tagihan ditemukan. Periksa detail sebelum membuat pembayaran.'
                    : 'Transaksi dibuat. Lanjutkan ke pembayaran Midtrans.',
                'data' => [
                    'order_number' => $order->order_number,
                    'transaction_type' => $order->transaction_type->value,
                    'product_name' => $order->product_name,
                    'destination' => $order->maskedDestination(),
                    'provider_customer_name' => $order->provider_customer_name,
                    'selling_price' => $order->selling_price,
                    'admin_fee' => $order->admin_fee,
                    'total_amount' => $order->total_amount,
                    'payment_status' => $order->payment_status->value,
                    'fulfillment_status' => $order->fulfillment_status->value,
                ],
                'redirect_url' => $isPostpaid
                    ? $order->temporarySignedUrl('topup.show')
                    : (string) $order->midtrans_redirect_url,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Tagihan belum dapat diperiksa. Periksa data lalu coba lagi.',
                'errors' => $exception->errors(),
                'redirect_url' => $order->temporarySignedUrl('topup.show'),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Cek tagihan atau halaman pembayaran belum dapat dibuat. Periksa data lalu coba lagi.',
                'redirect_url' => $order->temporarySignedUrl('topup.show'),
            ], 502);
        }
    }

    public function access(): View
    {
        return view('public.topup.access');
    }

    public function resolveAccess(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30', 'regex:/^TOP-\d{8}-\d{4}$/i'],
            'customer_email' => ['required', 'email:rfc', 'max:150'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s-]{9,20}$/'],
        ]);
        $order = TopupOrder::query()
            ->where('order_number', Str::upper($data['order_number']))
            ->first();
        $email = Str::lower(trim($data['customer_email']));
        $phone = (string) preg_replace('/\D+/', '', $data['customer_phone']);

        if (! $order instanceof TopupOrder
            || ! hash_equals(Str::lower($order->customer_email), $email)
            || ! hash_equals($order->customer_phone, $phone)
        ) {
            throw ValidationException::withMessages([
                'order_number' => 'Data transaksi tidak cocok. Periksa kembali nomor transaksi, email, dan nomor WhatsApp.',
            ]);
        }

        $audit->log('topup.access_link_regenerated', $order, metadata: [
            'order_number' => $order->order_number,
            'email_fingerprint' => $audit->identifierFingerprint($email),
            'phone_fingerprint' => $audit->identifierFingerprint($phone),
        ]);

        $redirectUrl = $order->temporarySignedUrl('topup.show');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tautan akses transaksi berhasil dibuat.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->away($redirectUrl);
    }

    public function store(
        Request $request,
        DocumentNumberGenerator $numbers,
        MidtransClient $midtrans,
        DigiflazzClient $digiflazz,
        AuditLogger $audit,
        DigiflazzPricing $pricing,
    ): RedirectResponse {
        if (! $digiflazz->isConfigured() || ! $midtrans->isConfigured()) {
            return back()->withInput()->withErrors(['topup' => 'Layanan top up sedang disiapkan. Silakan coba kembali nanti.']);
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'exists:digiflazz_products,id'],
            'destination' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9.+\s\-_]+$/', 'same:destination_confirmation'],
            'destination_confirmation' => ['required', 'string', 'max:40'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_email' => ['required', 'email:rfc', 'max:150'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9\s-]{9,20}$/'],
            'terms' => ['accepted'],
        ], [
            'destination.same' => 'Konfirmasi nomor tujuan harus sama.',
            'destination.regex' => 'Nomor atau ID tujuan mengandung karakter yang tidak didukung.',
            'terms.accepted' => 'Anda harus menyetujui pengecekan nomor tujuan dan syarat layanan.',
        ]);
        $this->validateProductDestination($validated);

        $order = $this->createTopupOrder($request, $validated, $numbers, $pricing);

        $audit->log('topup.created', $order, metadata: [
            'order_number' => $order->order_number,
            'sku' => $order->sku,
            'total_amount' => $order->total_amount,
        ]);

        try {
            $isPostpaid = $order->transaction_type === DigiflazzTransactionType::Postpaid;
            $order = $this->prepareOrder($order, $midtrans, $digiflazz, createPayment: ! $isPostpaid);

            if ($isPostpaid) {
                $audit->log('topup.postpaid_inquired', $order, metadata: [
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                ]);

                return redirect()->away($order->temporarySignedUrl('topup.show'))
                    ->with('status', 'Tagihan ditemukan. Periksa nama pelanggan dan nominal sebelum membayar.');
            }

            return redirect()->away((string) $order->midtrans_redirect_url);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($order->temporarySignedUrl('topup.show'))
                ->withErrors(['payment' => 'Cek tagihan atau halaman pembayaran belum dapat dibuat. Periksa data lalu coba lagi.']);
        }
    }

    public function show(Request $request, TopupOrder $topupOrder): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->statusPayload($topupOrder)]);
        }

        return view('public.topup.show', ['order' => $topupOrder]);
    }

    public function receipt(Request $request, TopupOrder $topupOrder, AuditLogger $audit): View|JsonResponse
    {
        abort_unless($topupOrder->paid_at !== null, 404);
        $audit->log('topup.receipt_viewed', $topupOrder, metadata: ['order_number' => $topupOrder->order_number]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->documentPayload($topupOrder)]);
        }

        return view('public.topup.receipt', ['order' => $topupOrder]);
    }

    public function receiptPdf(TopupOrder $topupOrder, AuditLogger $audit, TopupReceiptPdf $pdf): Response
    {
        abort_unless($topupOrder->paid_at !== null, 404);
        $audit->log('topup.receipt_pdf_downloaded', $topupOrder, metadata: ['order_number' => $topupOrder->order_number]);

        return response($pdf->render($topupOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$topupOrder->order_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function invoice(Request $request, TopupOrder $topupOrder, AuditLogger $audit): View|JsonResponse
    {
        abort_unless($topupOrder->paid_at !== null, 404);
        $audit->log('topup.invoice_viewed', $topupOrder, metadata: ['order_number' => $topupOrder->order_number]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->documentPayload($topupOrder)]);
        }

        return view('public.topup.invoice', ['order' => $topupOrder]);
    }

    public function pay(TopupOrder $topupOrder, MidtransClient $midtrans, DigiflazzClient $digiflazz): RedirectResponse
    {
        if (str_starts_with((string) $topupOrder->source_reference, 'offline:')
            || str_starts_with((string) $topupOrder->source_reference, 'agent:')
        ) {
            return redirect()->away($topupOrder->temporarySignedUrl('topup.show'))->withErrors(['payment' => 'Pembayaran transaksi ini diverifikasi oleh petugas.']);
        }

        if ($topupOrder->payment_status !== TopupPaymentStatus::Pending) {
            return redirect()->away($topupOrder->temporarySignedUrl('topup.show'))->withErrors(['payment' => 'Pesanan ini tidak lagi menunggu pembayaran.']);
        }

        if ($topupOrder->expires_at?->isPast()) {
            $topupOrder->update(['payment_status' => TopupPaymentStatus::Expired]);

            return redirect()->away($topupOrder->temporarySignedUrl('topup.show'))->withErrors(['payment' => 'Batas waktu pembayaran telah berakhir.']);
        }

        try {
            $order = $this->prepareOrder($topupOrder, $midtrans, $digiflazz);

            return redirect()->away((string) $order->midtrans_redirect_url);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($topupOrder->temporarySignedUrl('topup.show'))->withErrors(['payment' => 'Cek tagihan atau halaman pembayaran belum tersedia. Silakan coba lagi.']);
        }
    }

    private function validateProductDestination(array $validated): void
    {
        $product = DigiflazzProduct::query()->available()->find((int) $validated['product_id']);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sudah tidak tersedia. Silakan pilih produk lain.',
            ]);
        }

        $destination = (string) $validated['destination'];
        $digits = preg_replace('/\D+/', '', $destination) ?? '';
        $formType = $this->productFormType($product);

        if ($formType === 'mobile') {
            if (! preg_match('/^\+?[0-9\s-]+$/', $destination) || strlen($digits) < 9 || strlen($digits) > 15) {
                throw ValidationException::withMessages([
                    'destination' => 'Nomor HP harus berisi 9 sampai 15 digit.',
                ]);
            }

            return;
        }

        if (in_array($formType, ['pln_token', 'postpaid'], true)
            && (! ctype_digit($destination) || strlen($digits) < 6 || strlen($digits) > 20)
        ) {
            throw ValidationException::withMessages([
                'destination' => 'Nomor meter atau ID pelanggan harus berisi 6 sampai 20 digit angka.',
            ]);
        }
    }

    private function productFormType(DigiflazzProduct $product): string
    {
        if ($product->transaction_type === DigiflazzTransactionType::Postpaid) {
            return 'postpaid';
        }

        $text = mb_strtolower($product->category.' '.$product->product_name);

        if (str_contains($text, 'pln')) {
            return 'pln_token';
        }

        if (str_contains($text, 'pulsa') || str_contains($text, 'data') || str_contains($text, 'internet')) {
            return 'mobile';
        }

        return 'general';
    }

    /** @param array<string, mixed> $validated */
    private function createTopupOrder(
        Request $request,
        array $validated,
        DocumentNumberGenerator $numbers,
        DigiflazzPricing $pricing,
    ): TopupOrder {
        $product = DigiflazzProduct::query()->available()->findOrFail((int) $validated['product_id']);
        $destination = in_array($this->productFormType($product), ['mobile', 'pln_token', 'postpaid'], true)
            ? preg_replace('/\D+/', '', $validated['destination'])
            : $validated['destination'];
        $fingerprint = hash('sha256', json_encode([
            (int) $validated['product_id'],
            mb_strtolower((string) $destination),
            mb_strtolower($validated['customer_email']),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($validated, $fingerprint, $numbers, $request, $pricing): TopupOrder {
            $existing = TopupOrder::query()->where('idempotency_key', $validated['idempotency_key'])->lockForUpdate()->first();

            if ($existing !== null) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['topup' => 'Permintaan yang sama berisi data berbeda. Muat ulang halaman lalu coba lagi.']);
                }

                return $existing;
            }

            $product = DigiflazzProduct::query()->available()->lockForUpdate()->find($validated['product_id']);
            if ($product === null) {
                throw ValidationException::withMessages(['product_id' => 'Produk sudah tidak tersedia. Silakan pilih produk lain.']);
            }

            $orderNumber = $numbers->next('TOP');
            $isPostpaid = $product->transaction_type === DigiflazzTransactionType::Postpaid;
            $adminFee = $isPostpaid ? $pricing->postpaidAdminFee() : 0;

            return TopupOrder::create([
                'transaction_type' => $product->transaction_type,
                'order_number' => $orderNumber,
                'public_token' => (string) Str::uuid(),
                'user_id' => $request->user()?->getKey(),
                'digiflazz_product_id' => $product->id,
                'idempotency_key' => $validated['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'sku' => $product->buyer_sku_code,
                'product_name' => $product->product_name,
                'category' => $product->category,
                'brand' => $product->brand,
                'destination' => in_array($this->productFormType($product), ['mobile', 'pln_token', 'postpaid'], true)
                    ? preg_replace('/\D+/', '', $validated['destination'])
                    : $validated['destination'],
                'customer_name' => $validated['customer_name'],
                'customer_email' => mb_strtolower($validated['customer_email']),
                'customer_phone' => preg_replace('/\D+/', '', $validated['customer_phone']),
                'cost_price' => $isPostpaid ? 0 : $product->cost_price,
                'selling_price' => $isPostpaid ? 0 : $product->selling_price,
                'admin_fee' => $adminFee,
                'total_amount' => $isPostpaid ? 0 : $product->selling_price + $adminFee,
                'payment_status' => TopupPaymentStatus::Pending,
                'fulfillment_status' => TopupFulfillmentStatus::WaitingPayment,
                'midtrans_order_id' => $orderNumber,
                'digiflazz_reference' => $orderNumber,
                'expires_at' => now()->addMinutes(max(5, (int) config('services.midtrans.expiry_minutes', 60))),
            ]);
        }, 3);
    }

    /** @return array<string, mixed> */
    private function statusPayload(TopupOrder $order): array
    {
        $paid = $order->paid_at !== null;

        return [
            'order_number' => $order->order_number,
            'product_name' => $order->product_name,
            'transaction_type' => [
                'code' => $order->transaction_type->value,
                'label' => $order->transaction_type->label(),
            ],
            'destination' => $order->maskedDestination(),
            'provider_customer_name' => $order->provider_customer_name,
            'bill_details' => $order->transaction_type === DigiflazzTransactionType::Postpaid ? $order->bill_details : null,
            'selling_price' => $order->selling_price,
            'admin_fee' => $order->admin_fee,
            'total_amount' => $order->total_amount,
            'payment_status' => [
                'code' => $order->payment_status->value,
                'label' => $order->payment_status->label(),
            ],
            'fulfillment_status' => [
                'code' => $order->fulfillment_status->value,
                'label' => $order->fulfillment_status->label(),
            ],
            'serial_number' => $order->fulfillment_status === TopupFulfillmentStatus::Success
                ? $order->serial_number
                : null,
            'midtrans_payment_type' => $order->midtrans_payment_type,
            'midtrans_redirect_url' => $order->payment_status === TopupPaymentStatus::Pending
                ? $order->midtrans_redirect_url
                : null,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'refresh_url' => $order->temporarySignedUrl('topup.show'),
            'payment_url' => $order->payment_status === TopupPaymentStatus::Pending
                ? $order->temporarySignedUrl('topup.pay')
                : null,
            'receipt_url' => $paid ? $order->temporarySignedUrl('topup.receipt') : null,
            'receipt_pdf_url' => $paid ? $order->receiptPdfUrl() : null,
            'invoice_url' => $paid ? $order->temporarySignedUrl('topup.invoice') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function documentPayload(TopupOrder $order): array
    {
        $offline = str_starts_with((string) $order->source_reference, 'offline:');
        $whatsApp = str_starts_with((string) $order->source_reference, 'whatsapp:');
        return [
            ...$this->statusPayload($order),
            'sku' => $order->sku,
            'category' => $order->category,
            'brand' => $order->brand,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->maskedCustomerEmail(),
            'customer_phone' => $order->maskedCustomerPhone(),
            'payment_method' => $offline
                ? 'Tunai'
                : str($order->midtrans_payment_type ?: 'Midtrans')->replace('_', ' ')->upper()->toString(),
            'payment_reference' => $offline ? $order->order_number : $order->midtrans_transaction_id,
            'provider_reference' => $order->digiflazz_reference,
            'provider_rc' => $order->provider_rc,
            'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
            'source' => [
                'code' => $offline ? 'offline' : ($whatsApp ? 'whatsapp' : 'web'),
                'label' => $offline ? 'Transaksi toko' : ($whatsApp ? 'WhatsApp' : 'Website'),
            ],
            'merchant' => [
                'name' => (string) config('app.name'),
                'address' => (string) config('services.store.address'),
                'phone' => (string) config('services.whatsapp.display_number'),
                'hours' => (string) config('services.store.open_hours'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function inquirePostpaid(TopupOrder $order, DigiflazzClient $digiflazz): array
    {
        $inquiry = $digiflazz->inquirePostpaid($order);
        $referenceIsDuplicate = (string) ($inquiry['rc'] ?? '') === '49'
            || str_contains(mb_strtolower((string) ($inquiry['message'] ?? '')), 'ref id tidak unik');

        if (! $referenceIsDuplicate) {
            return $inquiry;
        }

        $order->update([
            'digiflazz_reference' => Str::limit($order->order_number.'-'.Str::lower(Str::random(8)), 255, ''),
        ]);

        return $digiflazz->inquirePostpaid($order->fresh());
    }

    private function prepareOrder(
        TopupOrder $order,
        MidtransClient $midtrans,
        DigiflazzClient $digiflazz,
        bool $createPayment = true,
    ): TopupOrder {
        return Cache::lock("topup-payment:{$order->id}", 40)->block(5, function () use ($order, $midtrans, $digiflazz, $createPayment): TopupOrder {
            $fresh = TopupOrder::query()->findOrFail($order->id);

            if ($createPayment && filled($fresh->midtrans_redirect_url)) {
                return $fresh;
            }

            if ($fresh->transaction_type === DigiflazzTransactionType::Postpaid && $fresh->inquired_at === null) {
                $inquiry = $this->inquirePostpaid($fresh, $digiflazz);
                $status = strtolower(trim((string) ($inquiry['status'] ?? '')));
                $cost = max(0, (int) ($inquiry['price'] ?? 0));
                $sellingPrice = max(0, (int) ($inquiry['selling_price'] ?? 0));

                $fresh->update([
                    'provider_rc' => filled($inquiry['rc'] ?? null) ? (string) $inquiry['rc'] : null,
                    'provider_message' => filled($inquiry['message'] ?? null) ? (string) $inquiry['message'] : null,
                ]);

                if ($status !== 'sukses' || $cost <= 0 || $sellingPrice <= 0) {
                    throw ValidationException::withMessages([
                        'payment' => filled($inquiry['message'] ?? null)
                            ? (string) $inquiry['message']
                            : 'Tagihan belum dapat ditemukan. Periksa nomor pelanggan lalu coba lagi.',
                    ]);
                }

                $details = $inquiry['desc'] ?? null;
                $fresh->update([
                    'provider_customer_name' => filled($inquiry['customer_name'] ?? null) ? (string) $inquiry['customer_name'] : null,
                    'bill_details' => is_array($details) ? $details : (filled($details) ? ['description' => (string) $details] : null),
                    'cost_price' => $cost,
                    'selling_price' => $sellingPrice,
                    'total_amount' => $sellingPrice + $fresh->admin_fee,
                    'inquired_at' => now(),
                ]);
                $fresh->refresh();
            }

            if (! $createPayment) {
                return $fresh;
            }

            if ($fresh->total_amount <= 0) {
                throw new \RuntimeException('Nominal pembayaran tidak valid.');
            }

            $payment = $midtrans->createSnapTransaction($fresh);
            $fresh->update([
                'midtrans_snap_token' => $payment['token'],
                'midtrans_redirect_url' => $payment['redirect_url'],
                'midtrans_status' => 'pending',
            ]);

            return $fresh->fresh();
        });
    }
}
