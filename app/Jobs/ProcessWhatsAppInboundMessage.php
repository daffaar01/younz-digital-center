<?php

namespace App\Jobs;

use App\Actions\Topup\WhatsAppTopupConversation;
use App\Ai\KnowledgeBaseResponder;
use App\Ai\OrderIntakeInterpreter;
use App\Enums\ServiceOrderStatus;
use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Models\AiUsageLog;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use App\Support\AiBudgetGuard;
use App\Support\DocumentNumberGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessWhatsAppInboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public array $backoff = [30, 120];

    public function __construct(
        public readonly string $messageId,
        public readonly string $chatJid,
        public readonly string $senderJid,
        public readonly string $text,
    ) {
        $this->afterCommit();
    }

    public function handle(
        WhatsAppGatewayClient $gateway,
        KnowledgeBaseResponder $knowledge,
        OrderIntakeInterpreter $interpreter,
        AiBudgetGuard $budget,
        DocumentNumberGenerator $numbers,
        WhatsAppTopupConversation $topup,
    ): void {
        $phone = Str::before($this->senderJid, '@');
        $topup->serialize($phone, fn () => $this->processMessage($gateway, $knowledge, $interpreter, $budget, $numbers, $topup));
    }

    private function processMessage(
        WhatsAppGatewayClient $gateway,
        KnowledgeBaseResponder $knowledge,
        OrderIntakeInterpreter $interpreter,
        AiBudgetGuard $budget,
        DocumentNumberGenerator $numbers,
        WhatsAppTopupConversation $topup,
    ): void {
        $phone = Str::before($this->senderJid, '@');
        $command = mb_strtolower(trim($this->text));

        $status = app(\App\Support\WhatsAppTopupStatus::class)->answer($this->text, $this->senderJid, $this->chatJid);
        if ($status !== null) {
            $gateway->sendText($phone, $status);
            return;
        }

        if ($topup->guidedInput($this->text, $phone, $this->messageId)) {
            \App\Support\WhatsAppNavigationState::forget('whatsapp:catalog:v2:'.hash('sha256', $this->chatJid));
            \App\Support\WhatsAppNavigationState::forget('whatsapp:menu:'.hash('sha256', $phone));
            return;
        }

        if (in_array($command, ['kembali', 'ganti nomor', 'ganti paket'], true)) {
            $catalogKey = 'whatsapp:catalog:v2:'.hash('sha256', $this->chatJid);
            $bookmark = \App\Support\WhatsAppNavigationState::get($catalogKey.':bookmark');
            $context = $bookmark ?? \App\Support\WhatsAppNavigationState::get($catalogKey);
            if (! is_array($context) || $command === 'ganti nomor') {
                $gateway->sendText($phone, 'Sesi tidak tersedia. Tulis pembayaran apa yang tersedia? untuk membuka katalog.');
                return;
            }
            $size = ($context['kind'] ?? '') === 'products' ? 10 : 20;
            if ($bookmark === null && $command === 'kembali' && ($context['kind'] ?? '') === 'products') {
                $context = $context['parent'] ?? ['question' => 'pembayaran apa yang tersedia?', 'next_offset' => 20, 'kind' => 'groups'];
                $size = 20;
            }
            $context['next_offset'] = max(0, ($context['next_offset'] ?? $size) - $size);
            $context['has_more'] = true;
            $answer = app(\App\Support\DigiflazzCatalog::class)->answer('LANJUT KATALOG', $context);
            $gateway->sendText($phone, $answer);
            \App\Support\WhatsAppNavigationState::put($catalogKey, $context, now()->addMinutes(15));
            \App\Support\WhatsAppNavigationState::forget($catalogKey.':bookmark');
            return;
        }

        if (in_array($command, ['setuju foto', 'batal foto'], true)) {
            $key = ProcessWhatsAppImage::consentKey($this->senderJid);
            if ($command === 'setuju foto') {
                Cache::put($key, ['version' => 1, 'at' => now()->toIso8601String()], now()->addDays(30));
            } else {
                Cache::forget($key);
            }
            $gateway->sendText($phone, $command === 'setuju foto'
                ? 'Izin pemrosesan foto oleh AI eksternal tersimpan selama 30 hari. Kirim ulang gambar tanpa data sensitif. Balas BATAL FOTO untuk mencabut izin.'
                : 'Izin pemrosesan foto dicabut. Chat teks tetap tersedia.');

            return;
        }

        if (in_array($command, ['/menu', 'menu'], true)) {
            \App\Support\WhatsAppNavigationState::forget('whatsapp:catalog:v2:'.hash('sha256', $this->chatJid));
            \App\Support\WhatsAppNavigationState::put('whatsapp:menu:'.hash('sha256', $phone), true, now()->addMinutes(10));
            $gateway->sendMenu(
                $phone,
                'Menu Younz Digital Center',
                'Pilih layanan yang Anda butuhkan.',
                [
                    ['id' => '/tanya', 'label' => 'Tanya Younz AI', 'description' => 'Konsultasi layanan dan informasi Younz'],
                    ['id' => '/cek', 'label' => 'Cek Pesanan', 'description' => 'Buka petunjuk pelacakan pesanan'],
                ],
            );

            return;
        }

        $menuKey = 'whatsapp:menu:'.hash('sha256', $phone);
        if (\App\Support\WhatsAppNavigationState::has($menuKey) && preg_match('/^[1-2]$/', $command)) {
            \App\Support\WhatsAppNavigationState::forget($menuKey);
            $command = match ($command) {
                '1' => '/tanya',
                '2' => '/cek',
            };
        }

        if (preg_match('/^(?:\/?TOPUP|PILIH|\/?PESAN)\b/iu', trim($this->text)) === 1) {
            $gateway->sendText($phone, 'Fitur PESAN dan TOPUP sudah dinonaktifkan di WhatsApp. Untuk transaksi operator gunakan format *BELI ...* atau *CEK TAGIHAN ...*.');

            return;
        }

        if ($command === '/tanya') {
            $gateway->sendText($phone, 'Silakan tulis pertanyaan Anda. Younz AI siap membantu informasi layanan, harga, jam buka, dan kebutuhan umum lainnya.');

            return;
        }

        if ($command === '/cek') {
            $gateway->sendText($phone, "Untuk melacak pesanan, buka:\n".route('public.track')."\n\nSiapkan nomor pesanan dan nomor WhatsApp yang digunakan saat memesan.");

            return;
        }

        $catalogKey = 'whatsapp:catalog:v2:'.hash('sha256', $this->chatJid);
        $catalogContext = \App\Support\WhatsAppNavigationState::get($catalogKey);
        if (preg_match('/^\d{1,5}$/', $command) && isset($catalogContext['product_choices'][(int) $command])) {
            \App\Support\WhatsAppNavigationState::put($catalogKey.':bookmark', $catalogContext, now()->addMinutes(15));
            $topup->guidedInput($this->text, $phone, $this->messageId, (int) $catalogContext['product_choices'][(int) $command]);
            \App\Support\WhatsAppNavigationState::forget($catalogKey);
            \App\Support\WhatsAppNavigationState::forget($menuKey);
            return;
        }
        $catalogAnswer = app(\App\Support\DigiflazzCatalog::class)->answer($this->text, $catalogContext);
        if ($catalogAnswer !== null) {
            \App\Support\WhatsAppNavigationState::forget($menuKey);
            $gateway->sendText($phone, $catalogAnswer);
            if ($catalogContext !== null) {
                \App\Support\WhatsAppNavigationState::put($catalogKey, $catalogContext, now()->addMinutes(15));
            }

            return;
        }
        \App\Support\WhatsAppNavigationState::forget($catalogKey);

        if ($topup->confirmIfPending(trim($this->text), $phone)) {
            return;
        }

        if (preg_match('/^\+?\d+$|^(?:KONFIRMASI|CONFIRM|SANDI|KODE)\b/iu', trim($this->text))) {
            $gateway->sendText($phone, 'Tidak ada sesi yang sesuai. Buka katalog baru dengan pembayaran apa yang tersedia?');
            return;
        }

        if (preg_match('/^CEK\s+TAGIHAN\b/iu', trim($this->text)) === 1) {
            $topup->directPurchase(trim($this->text), $phone, $this->messageId, postpaidOnly: true);

            return;
        }

        if (preg_match('/^(?:BELI|PROSES|BAYAR)\b/iu', trim($this->text)) === 1) {
            $topup->directPurchase(trim($this->text), $phone, $this->messageId);

            return;
        }

        $historyKey = 'whatsapp:history:'.hash('sha256', $this->chatJid);
        $history = array_slice((array) Cache::get($historyKey, []), -8);
        try {
            $budget->assertAvailable('whatsapp_chat', $this->text, $history);
        } catch (\Illuminate\Validation\ValidationException) {
            $gateway->sendText($phone, 'Batas penggunaan AI sedang tercapai. Coba lagi nanti; pertanyaan katalog Digiflazz tetap tersedia.');

            return;
        }
        $answer = $knowledge->answer($this->text, $history);
        $this->recordUsage('whatsapp_chat', $answer, ['conversation_turns' => count($history)]);
        $gateway->sendText($phone, $answer['answer']."\n\nKetik */menu* untuk melihat pilihan yang tersedia.");

        Cache::put($historyKey, array_slice([
            ...$history,
            ['role' => 'user', 'content' => $this->text],
            ['role' => 'assistant', 'content' => $answer['answer']],
        ], -8), now()->addHours(24));
    }

    private function handleOrder(
        string $message,
        string $phone,
        WhatsAppGatewayClient $gateway,
        OrderIntakeInterpreter $interpreter,
        AiBudgetGuard $budget,
        DocumentNumberGenerator $numbers,
    ): void {
        if ($message === '') {
            $gateway->sendText($phone, 'Tulis kebutuhan setelah kata *PESAN*. Contoh: PESAN print PDF A4 warna 2 rangkap, ambil sore.');

            return;
        }

        $budget->assertAvailable('order_intake', $message, []);
        $interpretation = $interpreter->interpret($message);
        $data = $interpretation['data'];
        $this->recordUsage('whatsapp_order_intake', [
            'provider' => $interpretation['provider'],
            'model' => $interpretation['model'],
            'input_tokens' => 0,
            'output_tokens' => 0,
            'status' => $interpretation['fallback'] ? 'fallback' : 'success',
        ], ['requires_follow_up' => $data['requires_follow_up']]);

        if ($data['requires_follow_up']) {
            $fields = collect($data['uncertain_fields'])->map(fn (string $field) => str_replace('_', ' ', $field))->implode(', ');
            $gateway->sendText($phone, "Detail pesanan belum lengkap. Mohon kirim ulang dengan awalan *PESAN* dan lengkapi: {$fields}.");

            return;
        }

        $sourceReference = 'whatsapp:'.hash('sha256', $this->messageId);
        $order = ServiceOrder::query()->where('source_reference', $sourceReference)->first();
        $order ??= DB::transaction(function () use ($data, $message, $phone, $numbers, $sourceReference) {
            $order = ServiceOrder::create([
                'order_number' => $numbers->next('ORD'),
                'public_token' => (string) Str::uuid(),
                'source_reference' => $sourceReference,
                'customer_name' => 'Pelanggan WhatsApp',
                'customer_phone' => preg_replace('/\D+/', '', $phone),
                'type' => $data['service'],
                'status' => ServiceOrderStatus::AwaitingReview,
                'specifications' => array_filter([
                    'paper_size' => $data['paper_size'],
                    'color_mode' => $data['color_mode'],
                    'sides' => $data['sides'],
                    'copies' => $data['copies'],
                    'finishing' => $data['finishing'],
                    'pickup_time' => $data['pickup_time'],
                    'acquisition_source' => 'whatsapp',
                ], fn ($value) => $value !== null && $value !== ''),
                'notes' => $message,
            ]);
            ServiceOrderStatusHistory::create([
                'service_order_id' => $order->id,
                'to_status' => ServiceOrderStatus::AwaitingReview->value,
                'notes' => 'Draft dibuat dari WhatsApp dan menunggu pemeriksaan operator.',
            ]);

            return $order;
        });

        $gateway->sendText($phone, implode("\n", [
            'Draft pesanan berhasil dibuat.',
            "Nomor: *{$order->order_number}*",
            'Status: *Menunggu pemeriksaan*',
            '',
            'Tim kami akan memeriksa detail dan menghubungi Anda jika ada informasi tambahan yang diperlukan.',
            route('public.track.show', $order->public_token),
        ]));
    }

    /** @param array<string, mixed> $result @param array<string, mixed> $metadata */
    private function recordUsage(string $feature, array $result, array $metadata): void
    {
        AiUsageLog::create([
            'feature' => $feature,
            'interaction_token' => (string) Str::uuid(),
            'provider' => $result['provider'],
            'model' => $result['model'],
            'input_tokens' => $result['input_tokens'] ?? 0,
            'output_tokens' => $result['output_tokens'] ?? 0,
            'status' => $result['status'] ?? 'success',
            'metadata' => $metadata + ['channel' => 'whatsapp'],
        ]);
    }
}
