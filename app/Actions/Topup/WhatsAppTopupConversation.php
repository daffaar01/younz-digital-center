<?php

namespace App\Actions\Topup;

use App\Enums\DigiflazzTransactionType;
use App\Enums\TopupFulfillmentStatus;
use App\Enums\TopupPaymentStatus;
use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Jobs\ProcessTopupOrder;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use App\Support\WhatsAppPendingConfirmation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

class WhatsAppTopupConversation
{
    public function __construct(
        private readonly WhatsAppGatewayClient $gateway,
        private readonly CreateWhatsAppTopupOrder $createOrder,
        private readonly AuditLogger $audit,
    ) {}

    public function start(string $query, string $phone): void
    {
        if ($query === '') {
            $this->gateway->sendText($phone, implode("\n", [
                '*TOP UP & PEMBAYARAN DIGITAL*',
                '',
                'Format pencarian:',
                '*TOPUP nama produk nomor tujuan*',
                '',
                '*Produk yang tersedia:*',
                '1. Pulsa: Telkomsel, XL, AXIS, Indosat, Tri, Smartfren, dan by.U.',
                '2. Paket data: cari berdasarkan operator atau kata Data.',
                '3. Token PLN: gunakan nomor meter atau ID pelanggan.',
                '4. Voucher game: Mobile Legends, Free Fire, PUBG Mobile, Valorant, Call of Duty Mobile, dan lainnya.',
                '5. Tagihan pascabayar: PLN Pascabayar, BPJS, IndiHome/Speedy, Biznet, MyRepublic, dan PDAM yang tersedia.',
                '',
                '*Contoh pencarian:*',
                '• TOPUP Telkomsel 081234567890',
                '• TOPUP XL Data 081234567890',
                '• TOPUP PLN 12345678901',
                '• TOPUP Mobile Legends 12345678',
                '• TOPUP BPJS Kesehatan 0001234567890',
                '• TOPUP IndiHome 123456789012',
                '',
                '*Langkah berikutnya:*',
                '• Sistem menampilkan maksimal 5 produk yang tersedia.',
                '• Balas *PILIH 1* sampai *PILIH 5* dalam 15 menit.',
                '• Periksa produk, nominal, dan nomor tujuan sebelum memilih.',
                '• Sistem mengirim tautan pembayaran Midtrans.',
                '• Setelah pembayaran terdeteksi, transaksi diproses otomatis ke provider.',
                '',
                'Catatan: nama produk harus sesuai katalog aktif. E-wallet hanya tersedia jika produknya muncul di katalog.',
                'Ketik */menu* untuk kembali ke menu utama.',
            ]));

            return;
        }

        $parts = preg_split('/\s+/u', $query) ?: [];
        $destination = array_pop($parts);
        $search = trim(implode(' ', $parts));
        if (! is_string($destination) || preg_match('/^[A-Za-z0-9.\-_]{4,40}$/', $destination) !== 1 || mb_strlen($search) < 2) {
            $this->gateway->sendText($phone, "Format belum sesuai. Gunakan *TOPUP nama produk nomor tujuan*.\nContoh: *TOPUP XL Data 081234567890*.\nKetik *TOPUP* untuk melihat panduan lengkap.");

            return;
        }

        $products = $this->searchProducts($search);

        if ($products->isEmpty()) {
            $this->gateway->sendText($phone, "Produk tidak ditemukan atau sedang tidak tersedia. Coba nama operator atau kategori yang lebih umum, misalnya *Telkomsel*, *XL Data*, *PLN*, *Mobile Legends*, atau *BPJS Kesehatan*.\n\nKetik *TOPUP* untuk melihat panduan lengkap.");

            return;
        }

        \App\Support\WhatsAppNavigationState::put($this->sessionKey($phone), [
            'destination' => $destination,
            'product_ids' => $products->pluck('id')->all(),
        ], now()->addMinutes(15));

        $lines = ['Pilih produk untuk tujuan *'.$this->mask($destination).'*:'];
        foreach ($products as $index => $product) {
            $price = $product->transaction_type === DigiflazzTransactionType::Postpaid
                ? 'cek tagihan'
                : 'Rp'.number_format($product->selling_price, 0, ',', '.');
            $lines[] = ($index + 1).'. '.$product->product_name.' — '.$price;
        }
        $lines[] = '';
        $lines[] = 'Balas *PILIH 1* sampai *PILIH '.$products->count().'* dalam 15 menit.';
        $lines[] = 'Periksa kembali nomor tujuan sebelum memilih.';
        $this->gateway->sendText($phone, implode("\n", $lines));
    }

    public function serialize(string $phone, \Closure $callback): mixed
    {
        return \App\Support\WhatsAppOperatorLease::run($this->operatorLock($phone), $callback);
    }

    private function operatorLock(string $phone): string
    {
        return 'whatsapp:operator-lock:'.hash('sha256', $this->normalizePhone($phone));
    }

    public function directPurchase(string $message, string $phone, string $messageId, bool $postpaidOnly = false): void
    {
        \App\Support\WhatsAppOperatorLease::within($this->operatorLock($phone), function () use ($message, $phone, $messageId, $postpaidOnly): void {
            if ($this->confirmPending($message, $phone)) return;
            $this->createDirectPurchase($message, $phone, $messageId, $postpaidOnly);
        });
    }

    private function createDirectPurchase(string $message, string $phone, string $messageId, bool $postpaidOnly = false): void
    {
        if (! $this->isWhitelistedOperator($phone)) {
            $this->gateway->sendText($phone, 'Pembelian langsung melalui WhatsApp hanya tersedia untuk nomor operator yang terdaftar.');

            return;
        }

        if ((string) config('services.younz_ppob.whatsapp_confirmation_hash') === '') {
            $this->gateway->sendText($phone, 'Konfirmasi operator belum dikonfigurasi. Hubungi administrator.');

            return;
        }

        $parsed = $postpaidOnly ? $this->parseBillInquiry($message) : $this->parseDirectPurchase($message);
        if ($parsed === null) {
            $this->gateway->sendText($phone, $postpaidOnly
                ? 'Gunakan: *Cek tagihan PDAM Sampit nomor 1234567890*. Tulis nama layanan dan nomor pelanggan. Pengecekan tidak langsung membayar tagihan.'
                : "Format belum sesuai. Gunakan: *Beli pulsa Telkomsel 10.000 ke nomor 081234567890*.\n\nUntuk tagihan, tulis nama layanan pascabayar dan nomor pelanggan.");

            return;
        }

        $products = $this->searchProducts($parsed['search'], 5, $postpaidOnly);
        if ($products->isEmpty()) {
            $this->gateway->sendText($phone, 'Produk tidak ditemukan atau sedang tidak tersedia. Periksa nama operator, nominal, dan kategori pascabayar.');

            return;
        }

        if ($products->count() > 1) {
            $lines = [$postpaidOnly
                ? 'Ada beberapa layanan pascabayar. Ulangi *Cek tagihan nama layanan nomor pelanggan* dengan nama lengkap dari daftar ini:'
                : 'Permintaan cocok dengan beberapa produk. Tulis nama produk dan nominal yang lebih lengkap:'];
            foreach ($products as $product) {
                $price = $product->transaction_type === DigiflazzTransactionType::Postpaid
                    ? 'pascabayar — cek tagihan'
                    : 'Rp'.number_format($product->selling_price, 0, ',', '.');
                $lines[] = '• '.$product->product_name.' — '.$price;
            }
            $this->gateway->sendText($phone, implode("\n", $lines));

            return;
        }

        $explicit = $products->first()->isGoPayPrepaid();
        if ($explicit) {
            $destination = $this->mobileDestination($parsed['destination']);
            if ($destination === null) {
                $this->gateway->sendText($phone, 'Nomor HP GoPay tidak valid. Gunakan nomor 08…, 628…, atau +628….');
                return;
            }
            $parsed['destination'] = $destination;
        }

        try {
            $order = $this->createOrder->handleDirect(
                (int) $products->first()->id,
                $parsed['destination'],
                $phone,
                $messageId,
                fn (TopupOrder $draft) => WhatsAppPendingConfirmation::put($this->confirmationKey($phone), [
                    'order_id' => $draft->id, 'source_reference' => $draft->source_reference, 'explicit' => $explicit,
                ], now()->addMinutes(10)),
            );

            WhatsAppPendingConfirmation::put($this->confirmationKey($phone), [
                'order_id' => $order->id,
                'source_reference' => $order->source_reference,
                'explicit' => $explicit,
            ], now()->addMinutes(10));

            $this->gateway->sendText($phone, implode("\n", [
                $postpaidOnly ? 'Tagihan ditemukan. *Belum dibayar.*' : 'Permintaan pembelian diterima.',
                "Nomor transaksi: *{$order->order_number}*",
                'Produk: '.$order->product_name,
                'Jenis: '.$order->transaction_type->label(),
                'Tujuan: '.$order->maskedDestination(),
                'Total: *Rp'.number_format($order->total_amount, 0, ',', '.').'*',
                '',
                ...($postpaidOnly ? ['Pengecekan selesai tanpa pembayaran. Jika ingin membayar, periksa rincian di atas.', ''] : []),
                $explicit
                    ? 'Jika semua sudah benar, balas *KONFIRMASI <sandi>* untuk memproses transaksi.'
                    : 'Jika semua sudah benar, balas dengan *sandi konfirmasi operator* untuk memproses transaksi.',
                'Sandi hanya diproses dari nomor operator yang terdaftar dan tidak disimpan sebagai teks biasa.',
            ]));
        } catch (RuntimeException $error) {
            $this->gateway->sendText($phone, $error->getMessage());
        } catch (Throwable $error) {
            report($error);
            $this->gateway->sendText($phone, 'Transaksi belum dapat dibuat. Silakan coba kembali beberapa saat lagi.');
        }
    }

    public function guidedInput(string $message, string $phone, string $messageId, ?int $productId = null): bool
    {
        return \App\Support\WhatsAppOperatorLease::within($this->operatorLock($phone), function () use ($message, $phone, $messageId, $productId): bool {
            $key = 'whatsapp:guided:v1:'.hash('sha256', $phone);
            $state = \App\Support\WhatsAppNavigationState::get($key);
            $pending = WhatsAppPendingConfirmation::get($this->confirmationKey($phone));
            $navigation = mb_strtolower(trim($message));
            if (in_array($navigation, ['/menu', 'menu'], true)) {
                \App\Support\WhatsAppNavigationState::forget($key);
                return false;
            }
            if (in_array($navigation, ['kembali', 'ganti nomor', 'ganti paket'], true)) {
                if (is_array($pending) && ! ($pending['explicit'] ?? false)) {
                    $this->gateway->sendText($phone, 'Untuk transaksi BELI/CEK TAGIHAN, ketik BATAL lalu ulangi perintah dengan data baru. Draft belum diubah.');
                    return true;
                }
                $selectedId = $state['product_id'] ?? null;
                if (is_array($pending)) {
                    $selectedId = \App\Support\WhatsAppOperatorLease::transaction(function () use ($pending): ?int {
                        $order = TopupOrder::query()->lockForUpdate()->find($pending['order_id']);
                        if ($order === null || $order->source_reference !== $pending['source_reference']
                            || $order->payment_status !== TopupPaymentStatus::Pending
                            || $order->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment
                            || $order->expires_at?->isPast()) return null;
                        $order->update(['expires_at' => now()->subSecond()]);
                        return $order->digiflazz_product_id;
                    });
                    if ($selectedId === null) {
                        $this->gateway->sendText($phone, 'Transaksi tidak dapat diubah atau sesi sudah kedaluwarsa. Transaksi yang diproses tidak dibatalkan.');
                        return true;
                    }
                    WhatsAppPendingConfirmation::forget($this->confirmationKey($phone));
                }
                if ($selectedId !== null && ($navigation === 'ganti nomor' || ($navigation === 'kembali' && is_array($pending)))) {
                    \App\Support\WhatsAppNavigationState::put($key, ['product_id' => $selectedId], now()->addMinutes(15));
                    $this->gateway->sendText($phone, 'Silakan masukkan nomor HP tujuan yang baru. Draft lama tidak dapat dikonfirmasi lagi. Ketik BATAL untuk membatalkan.');
                    return true;
                }
                \App\Support\WhatsAppNavigationState::forget($key);
                return false;
            }
            if ($navigation === 'batal') {
                if (is_array($pending)) {
                    \App\Support\WhatsAppOperatorLease::transaction(function () use ($pending): void {
                        $order = TopupOrder::query()->lockForUpdate()->find($pending['order_id']);
                        if ($order !== null && $order->source_reference === $pending['source_reference']
                            && $order->payment_status === TopupPaymentStatus::Pending
                            && $order->fulfillment_status === TopupFulfillmentStatus::WaitingPayment) {
                            $order->update(['expires_at' => now()->subSecond()]);
                        }
                    });
                }
                \App\Support\WhatsAppNavigationState::forget($key);
                WhatsAppPendingConfirmation::forget($this->confirmationKey($phone));
                $this->gateway->sendText($phone, 'Sesi dibatalkan. Transaksi yang sudah diproses tidak dibatalkan.');
                return true;
            }
            if ($productId === null && ! is_array($state)) return false;
            if (! $this->isWhitelistedOperator($phone) || blank(config('services.younz_ppob.whatsapp_confirmation_hash'))) {
                \App\Support\WhatsAppNavigationState::forget($key);
                $this->gateway->sendText($phone, 'Pembelian hanya untuk operator terdaftar dengan sandi konfirmasi yang sudah dikonfigurasi.');
                return true;
            }
            if (is_array($pending)) {
                \App\Support\WhatsAppNavigationState::forget($key);
                $this->gateway->sendText($phone, 'Masih ada transaksi menunggu konfirmasi. Selesaikan atau ketik BATAL terlebih dahulu.');
                return true;
            }
            $product = DigiflazzProduct::query()->available()->find($productId ?? $state['product_id']);
            if ($product === null || ! $product->supportsGuidedPhonePurchase()) {
                \App\Support\WhatsAppNavigationState::forget($key);
                $this->gateway->sendText($phone, 'Pilihan tidak tersedia untuk pembelian terpandu. Fitur ini mendukung Data, Pulsa, dan GoPay prabayar berbasis nomor HP.');
                return true;
            }
            if ($productId !== null) {
                \App\Support\WhatsAppNavigationState::put($key, ['product_id' => $product->id], now()->addMinutes(15));
                $this->gateway->sendText($phone, '*'.$product->product_name."*\nSilakan masukkan nomor HP tujuan (08…, 628…, atau +628…).\nKetik BATAL untuk membatalkan. Sesi berlaku 15 menit.");
                return true;
            }
            $destination = $this->mobileDestination(trim($message));
            if ($destination === null) {
                $this->gateway->sendText($phone, 'Nomor HP tidak valid. Masukkan nomor tanpa spasi, contoh 081234567890, atau ketik BATAL.');
                return true;
            }
            try {
                $order = $this->createOrder->handleDirect((int) $product->id, $destination, $phone, $messageId,
                    fn (TopupOrder $draft) => WhatsAppPendingConfirmation::put($this->confirmationKey($phone), [
                        'order_id' => $draft->id, 'source_reference' => $draft->source_reference, 'explicit' => true,
                    ], now()->addMinutes(10)));
                if ($order->payment_status !== TopupPaymentStatus::Pending || $order->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment || $order->expires_at?->isPast()) {
                    throw new RuntimeException('Transaksi tidak lagi menunggu konfirmasi. Buka katalog baru.');
                }
                WhatsAppPendingConfirmation::put($this->confirmationKey($phone), ['order_id' => $order->id, 'source_reference' => $order->source_reference, 'explicit' => true], now()->addMinutes(10));
                \App\Support\WhatsAppNavigationState::forget($key);
                $this->gateway->sendText($phone, "*Konfirmasi Pembelian*\nProduk: ".$order->product_name."\nTujuan: ".$order->maskedDestination()."\nTotal: Rp".number_format($order->total_amount, 0, ',', '.')."\n\nSilakan masukkan sandi dengan format *KONFIRMASI <sandi>*.\nKetik BATAL untuk membatalkan. Belum ada pembelian sebelum konfirmasi.");
            } catch (RuntimeException $error) {
                $this->gateway->sendText($phone, $error->getMessage());
            }
            return true;
        });
    }

    public function confirmIfPending(string $message, string $phone): bool
    {
        return \App\Support\WhatsAppOperatorLease::within($this->operatorLock($phone), fn (): bool => $this->confirmPending($message, $phone));
    }

    private function confirmPending(string $message, string $phone): bool
    {
        $key = $this->confirmationKey($phone);
        $pending = WhatsAppPendingConfirmation::get($key);
        if (! is_array($pending)) {
            return false;
        }

        if (($pending['explicit'] ?? false) && preg_match('/^\+?\d+$/', trim($message))) {
            $this->gateway->sendText($phone, 'Nomor tidak dianggap konfirmasi. Gunakan *KONFIRMASI <sandi>* atau ketik BATAL.');
            return true;
        }
        $code = $this->confirmationCode($message);
        if ($code === null && preg_match('/^(?:BELI|PROSES|BAYAR|CEK\s+TAGIHAN|KONFIRMASI|CONFIRM|SANDI|KODE)\b/iu', trim($message)) !== 1) {
            return false;
        }
        if ($code === null) {
            $this->gateway->sendText($phone, 'Masih ada transaksi yang menunggu konfirmasi. Balas dengan sandi konfirmasi operator, atau tunggu sampai sesi ini kedaluwarsa.');

            return true;
        }

        if (! $this->isWhitelistedOperator($phone)) {
            WhatsAppPendingConfirmation::forget($key);
            $this->gateway->sendText($phone, 'Nomor WhatsApp ini tidak berwenang untuk mengonfirmasi pembelian.');

            return true;
        }

        $hash = (string) config('services.younz_ppob.whatsapp_confirmation_hash');
        if ($hash === '') {
            WhatsAppPendingConfirmation::forget($key);
            $this->gateway->sendText($phone, 'Konfirmasi operator belum dikonfigurasi. Hubungi administrator.');

            return true;
        }

        $rateLimitKey = $this->confirmationRateLimitKey($phone);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->gateway->sendText($phone, 'Terlalu banyak percobaan sandi. Tunggu 10 menit lalu buat permintaan baru.');

            return true;
        }

        try {
            $valid = Hash::check($code, $hash);
        } catch (Throwable) {
            $valid = false;
        }

        if (! $valid) {
            RateLimiter::hit($rateLimitKey, 600);
            $this->gateway->sendText($phone, 'Sandi konfirmasi tidak valid. Transaksi belum diproses.');

            return true;
        }

        try {
            $transitioned = \App\Support\WhatsAppOperatorLease::transaction(function () use ($pending, $key): bool {
                $order = TopupOrder::query()->lockForUpdate()->find((int) ($pending['order_id'] ?? 0));
                if ($order === null || $order->source_reference !== ($pending['source_reference'] ?? null)) {
                    throw new RuntimeException('Transaksi konfirmasi tidak ditemukan. Buat permintaan baru.');
                }

                if ($order->payment_status === TopupPaymentStatus::Paid) {
                    return false;
                }

                if ($order->payment_status !== TopupPaymentStatus::Pending
                    || $order->fulfillment_status !== TopupFulfillmentStatus::WaitingPayment
                ) {
                    throw new RuntimeException('Transaksi tidak lagi menunggu konfirmasi.');
                }

                if ($order->expires_at?->isPast()) {
                    throw new RuntimeException('Sesi transaksi sudah kedaluwarsa. Buat permintaan baru.');
                }

                if ($order->transaction_type === DigiflazzTransactionType::Postpaid
                    && ($order->inquired_at === null
                        || ! $order->inquired_at->isSameDay(now())
                        || $order->total_amount <= 0)
                ) {
                    throw new RuntimeException('Inquiry pascabayar sudah tidak berlaku. Buat permintaan baru.');
                }

                $order->update([
                    'payment_status' => TopupPaymentStatus::Paid,
                    'fulfillment_status' => TopupFulfillmentStatus::Queued,
                    'paid_at' => now(),
                    'provider_payment_requested_at' => null,
                ]);
                WhatsAppPendingConfirmation::forget($key);

                return true;
            }, 3);

            RateLimiter::clear($rateLimitKey);
            $order = TopupOrder::query()->findOrFail((int) $pending['order_id']);
            WhatsAppPendingConfirmation::forget($key);

            if ($transitioned) {
                try {
                    $this->audit->log('topup.whatsapp_operator_confirmed', $order, after: [
                        'payment_status' => $order->payment_status->value,
                        'fulfillment_status' => $order->fulfillment_status->value,
                        'order_number' => $order->order_number,
                    ], metadata: [
                        'channel' => 'whatsapp',
                        'operator_phone_fingerprint' => $this->audit->identifierFingerprint($phone),
                    ]);
                } catch (Throwable $error) {
                    report($error);
                }
                try {
                    ProcessTopupOrder::dispatch($order->id);
                    $this->gateway->sendText($phone, "Konfirmasi diterima. Transaksi *{$order->order_number}* sudah masuk antrean dan akan diproses otomatis oleh provider.");
                } catch (Throwable $error) {
                    report($error);
                    $this->gateway->sendText($phone, "Konfirmasi diterima. Transaksi *{$order->order_number}* sudah tercatat, tetapi antrean provider perlu diperiksa administrator.");
                }
            } else {
                $this->gateway->sendText($phone, "Transaksi *{$order->order_number}* sudah pernah dikonfirmasi dan tidak dieksekusi ulang.");
            }
        } catch (RuntimeException $error) {
            WhatsAppPendingConfirmation::forget($key);
            $this->gateway->sendText($phone, $error->getMessage());
        } catch (Throwable $error) {
            report($error);
            $this->gateway->sendText($phone, 'Status konfirmasi belum dapat ditampilkan. Jangan membuat pembelian ulang; periksa status transaksi atau hubungi administrator.');
        }

        return true;
    }

    private function searchProducts(string $search, int $limit = 5, bool $postpaidOnly = false)
    {
        if (preg_match('/\bgo\s*pay\b/iu', $search)) {
            if ($postpaidOnly) {
                return new \Illuminate\Database\Eloquent\Collection;
            }
            $terms = $this->goPaySearchTokens($search);

            return DigiflazzProduct::query()->available()
                ->where('transaction_type', DigiflazzTransactionType::Prepaid->value)
                ->whereRaw('LOWER(TRIM(category)) = ?', ['e-money'])
                ->whereRaw('LOWER(TRIM(brand)) IN (?, ?)', ['go pay', 'gopay'])
                ->orderBy('selling_price')->orderBy('id')->cursor()
                ->filter(function (DigiflazzProduct $product) use ($terms): bool {
                    $name = $this->goPaySearchTokens($product->product_name);
                    $numbers = array_filter($name, fn ($token) => ctype_digit($token));
                    // A name with multiple numeric values has no unambiguous face value.
                    if (count($numbers) !== 1) return false;
                    $fields = $this->goPaySearchTokens($product->product_name.' '.$product->brand.' '.$product->category);
                    return array_diff($terms, $fields) === [];
                })->take($limit)->collect();
        }

        $terms = array_values(array_filter(preg_split('/\s+/u', trim($search)) ?: []));

        return DigiflazzProduct::query()->available()
            ->when($postpaidOnly, fn ($query) => $query->where('transaction_type', DigiflazzTransactionType::Postpaid->value))
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function ($match) use ($term): void {
                        $match->where('product_name', 'like', "%{$term}%")
                            ->orWhere('brand', 'like', "%{$term}%")
                            ->orWhere('category', 'like', "%{$term}%");
                    });
                }
            })
            ->orderByRaw('case when transaction_type = ? then 0 else 1 end', [DigiflazzTransactionType::Prepaid->value])
            ->orderBy('selling_price')->limit($limit)->get();
    }

    /** @return list<string> */
    private function goPaySearchTokens(string $text): array
    {
        $text = preg_replace('/\bgo\s*pay\b/iu', 'gopay', mb_strtolower($text)) ?? $text;
        $text = preg_replace_callback('/(?<![\p{L}\d.,])\d{1,3}(?:\.\d{3})+(?![\p{L}\d.,])/u',
            fn ($match) => str_replace('.', '', $match[0]), $text) ?? $text;

        return preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function mobileDestination(string $value): ?string
    {
        if (! preg_match('/\A(?:08\d{8,11}|\+?628\d{8,11})\z/', $value)) return null;

        return $this->normalizePhone($value);
    }

    /** @return array{search: string, destination: string}|null */
    private function parseDirectPurchase(string $message): ?array
    {
        if (preg_match('/^(?:beli|proses|bayar)\b[\s:,-]*(.*?)\s+\bke\b\s+(?:nomor|no\.?|#)?\s*(\+?[\d\s().-]{6,32})$/iu', trim($message), $matches) !== 1) {
            return null;
        }

        $search = trim((string) $matches[1]);
        $destination = preg_replace('/\D+/', '', (string) $matches[2]) ?? '';
        if (mb_strlen($search) < 2 || mb_strlen($destination) < 6 || mb_strlen($destination) > 24) {
            return null;
        }

        return ['search' => $search, 'destination' => $destination];
    }

    /** @return array{search: string, destination: string}|null */
    private function parseBillInquiry(string $message): ?array
    {
        $message = preg_replace('/\s+/u', ' ', trim($message)) ?? '';
        if (preg_match('/^cek tagihan\b[\s:,-]*(.*?)\s+(?:(?:dengan|ke|untuk)\s+)?(?:(?:nomor|no\.?|id)(?:\s+pelanggan)?\s*[:#]?\s*)?(\d[\d\s().-]{4,38}\d)$/iu', $message, $matches) !== 1) {
            return null;
        }

        $search = trim($matches[1]);
        $destination = preg_replace('/\D+/', '', $matches[2]) ?? '';
        if (mb_strlen($search) < 2 || strlen($destination) < 6 || strlen($destination) > 24
            || preg_match('/^[\p{L}\p{N} .&()\/-]+$/u', $search) !== 1
        ) {
            return null;
        }

        return ['search' => $search, 'destination' => $destination];
    }

    private function confirmationCode(string $message): ?string
    {
        $message = trim($message);
        if (preg_match('/^(?:konfirmasi|confirm|sandi|kode)\s*[: -]?\s*(\d{6,12})$/iu', $message, $matches) === 1) {
            return (string) $matches[1];
        }

        return preg_match('/^\d{6,12}$/', $message) === 1 ? $message : null;
    }

    private function isWhitelistedOperator(string $phone): bool
    {
        $configured = config('services.younz_ppob.whatsapp_operator_numbers', []);
        if (! is_array($configured)) {
            return false;
        }

        $phone = $this->normalizePhone($phone);

        return in_array($phone, array_map(fn ($number) => $this->normalizePhone((string) $number), $configured), true);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    private function confirmationKey(string $phone): string
    {
        return 'whatsapp:ppob:confirmation:'.hash('sha256', $this->normalizePhone($phone));
    }

    private function confirmationRateLimitKey(string $phone): string
    {
        return 'whatsapp:ppob:confirmation-attempts:'.hash('sha256', $this->normalizePhone($phone));
    }

    public function select(int $selection, string $phone, string $messageId): void
    {
        $key = $this->sessionKey($phone);
        $session = \App\Support\WhatsAppNavigationState::get($key);
        $productId = is_array($session) ? ($session['product_ids'][$selection - 1] ?? null) : null;
        $destination = is_array($session) ? ($session['destination'] ?? null) : null;
        if (! is_numeric($productId) || ! is_string($destination)) {
            $this->gateway->sendText($phone, 'Pilihan sudah kedaluwarsa. Ketik *TOPUP produk tujuan* untuk memulai kembali.');

            return;
        }

        try {
            $order = $this->createOrder->handle((int) $productId, $destination, $phone, $messageId);
            \App\Support\WhatsAppNavigationState::forget($key);
            $this->gateway->sendText($phone, implode("\n", [
                'Transaksi top up berhasil dibuat.',
                "Nomor: *{$order->order_number}*",
                'Produk: '.$order->product_name,
                'Tujuan: '.$order->maskedDestination(),
                'Total: *Rp'.number_format($order->total_amount, 0, ',', '.').'*', '',
                'Bayar melalui Midtrans:',
                (string) $order->midtrans_redirect_url, '',
                'Pembayaran akan terdeteksi otomatis. Tautan berlaku sampai '.$order->expires_at?->format('d/m/Y H:i').' WIB.',
            ]));
        } catch (RuntimeException $error) {
            $this->gateway->sendText($phone, $error->getMessage());
        } catch (Throwable $error) {
            report($error);
            $this->gateway->sendText($phone, 'Transaksi belum dapat dibuat. Silakan coba kembali beberapa saat lagi.');
        }
    }

    private function sessionKey(string $phone): string
    {
        return 'whatsapp:topup:'.hash('sha256', $phone);
    }

    private function mask(string $destination): string
    {
        $length = mb_strlen($destination);
        if ($length <= 5) {
            return str_repeat('*', max(0, $length - 2)).mb_substr($destination, -2);
        }

        return mb_substr($destination, 0, 3).str_repeat('*', max(3, $length - 6)).mb_substr($destination, -3);
    }
}
