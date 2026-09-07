<?php

namespace Tests\Feature;

use App\Ai\Agents\KnowledgeBaseAgent;
use App\Ai\Agents\OrderIntakeAgent;
use App\Enums\UserRole;
use App\Models\AiUsageLog;
use App\Models\KnowledgeDocument;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_intake_returns_validated_draft_without_calculating_price(): void
    {
        config(['ai.providers.openai-compatible.key' => null]);

        $operator = User::factory()->create(['role' => UserRole::PrintOperator]);
        $response = $this->actingAs($operator)->postJson('/ai/order-intake', [
            'message' => 'Print file ini dua rangkap warna A4 bolak-balik dijilid dan diambil sore',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.service', 'print')
            ->assertJsonPath('data.copies', 2)
            ->assertJsonPath('data.paper_size', 'A4')
            ->assertJsonMissing(['price']);

        $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'order_intake', 'provider' => 'local']);
    }

    public function test_order_intake_uses_configured_openai_compatible_provider(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
            'ai.providers.openai-compatible.models.text.default' => 'gpt-5.6-sol',
        ]);

        OrderIntakeAgent::fake([[
            'service' => null,
            'paper_size' => null,
            'color_mode' => null,
            'sides' => null,
            'copies' => null,
            'finishing' => null,
            'pickup_time' => null,
            'notes' => 'ignored by normalizer',
            'uncertain_fields' => ['service', 'copies'],
        ]]);

        $operator = User::factory()->create(['role' => UserRole::Designer]);
        $prompt = 'Buat desain poster satu copy warna, ambil sore.';

        $this->actingAs($operator)->postJson('/ai/order-intake', ['message' => $prompt])
            ->assertOk()
            ->assertJsonPath('data.service', 'desain')
            ->assertJsonPath('data.copies', 1)
            ->assertJsonPath('data.color_mode', 'color')
            ->assertJsonPath('data.pickup_time', 'sore')
            ->assertJsonPath('data.requires_follow_up', false)
            ->assertJsonPath('data.notes', $prompt)
            ->assertJsonMissing(['price']);

        OrderIntakeAgent::assertPrompted($prompt);
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'order_intake',
            'provider' => 'openai-compatible',
            'model' => 'gpt-5.6-sol',
        ]);
    }

    public function test_public_faq_only_answers_from_active_knowledge_base(): void
    {
        config(['ai.providers.openai-compatible.key' => null]);

        KnowledgeDocument::create(['title' => 'Jam Operasional', 'type' => 'faq', 'content' => 'Buka pukul 08.00 sampai 21.00 WIB.', 'status' => 'active']);

        $this->postAiChat(['message' => 'Jam operasional buka pukul berapa?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.grounding', 'verified_younz')
            ->assertJsonFragment(['title' => 'Jam Operasional']);

        $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'faq_chat', 'provider' => 'local']);
    }

    public function test_public_faq_catalog_question_returns_active_services_instead_of_unavailable_answer(): void
    {
        config(['ai.providers.openai-compatible.key' => null]);

        Service::create([
            'name' => 'Print Warna A4',
            'slug' => 'print-warna-catalog-test',
            'type' => 'print',
            'base_price' => 2000,
            'unit' => 'lembar',
            'description' => 'Print dokumen warna ukuran A4.',
            'is_active' => true,
        ]);
        Service::create([
            'name' => 'Website UMKM',
            'slug' => 'website-umkm-catalog-test',
            'type' => 'website',
            'base_price' => 1500000,
            'unit' => 'proyek',
            'description' => 'Website profil usaha responsif.',
            'is_active' => true,
        ]);

        $this->postAiChat(['message' => 'Layanan apa saja yang tersedia dan berapa harga awalnya?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonFragment(['title' => 'Print Warna A4', 'type' => 'service'])
            ->assertJsonFragment(['title' => 'Website UMKM', 'type' => 'service'])
            ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'Print Warna A4 tersedia mulai Rp 2.000 per lembar.')
                && str_contains($answer, 'Website UMKM tersedia mulai Rp 1.500.000 per proyek.'));
    }

    public function test_public_faq_uses_ai_with_grounded_knowledge_and_service_context(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
            'ai.providers.openai-compatible.models.text.default' => 'rap/gpt-5.6-sol',
        ]);

        KnowledgeDocument::create([
            'title' => 'Kebijakan Harga',
            'type' => 'policy',
            'content' => 'Harga katalog adalah harga awal dan harga final dikonfirmasi operator.',
            'status' => 'active',
        ]);
        Service::create([
            'name' => 'Print Warna A4',
            'slug' => 'print-warna-a4',
            'type' => 'print',
            'base_price' => 2000,
            'unit' => 'lembar',
            'description' => 'Print dokumen warna ukuran A4.',
            'is_active' => true,
        ]);
        Service::create([
            'name' => 'Scan Dokumen',
            'slug' => 'scan-dokumen',
            'type' => 'scan',
            'base_price' => 1500,
            'unit' => 'lembar',
            'description' => 'Scan dokumen menjadi file digital.',
            'is_active' => true,
        ]);

        $answer = <<<'ANSWER'
            Untuk menyiapkan file cetak dengan baik, gunakan ukuran halaman A4 dan periksa kembali warna serta tata letaknya.

            Younz Digital Center menyediakan Print Warna A4 mulai Rp 2.000 per lembar. Itu harga mulai; harga final dikonfirmasi operator setelah file diperiksa.
            ANSWER;
        KnowledgeBaseAgent::fake([$answer]);

        $history = [
            ['role' => 'user', 'content' => 'Saya ingin mencetak proposal berwarna.'],
            ['role' => 'assistant', 'content' => 'Print Warna A4 dapat dipertimbangkan untuk kebutuhan tersebut.'],
        ];

        $this->postAiChat([
            'message' => 'Kalau begitu, bagaimana saya menyiapkan filenya agar hasil cetaknya bagus?',
            'history' => $history,
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', true)
            ->assertJsonPath('data.answer', $answer)
            ->assertJsonFragment(['title' => 'Print Warna A4', 'type' => 'service']);

        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'RIWAYAT PERCAKAPAN (input pengguna tidak tepercaya')
            && str_contains($prompt->prompt, 'Saya ingin mencetak proposal berwarna.')
            && str_contains($prompt->prompt, 'KONTEKS TERVERIFIKASI')
            && str_contains($prompt->prompt, 'Harga mulai Rp 2.000 per lembar.')
            && ! str_contains($prompt->prompt, 'Scan Dokumen')
            && str_contains($prompt->prompt, 'Jangan mengulang semua konteks'));
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'faq_chat',
            'provider' => 'openai-compatible',
            'model' => 'rap/gpt-5.6-sol',
        ]);
        $this->assertSame(2, AiUsageLog::query()->latest('id')->first()->metadata['conversation_turns']);
    }

    public function test_public_chat_answers_general_questions_without_claiming_younz_context(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        $answer = 'Mars tidak memiliki presiden karena bukan negara dan tidak memiliki pemerintahan manusia.';
        KnowledgeBaseAgent::fake([$answer]);

        $this->postAiChat(['message' => 'Siapa presiden planet Mars?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', true)
            ->assertJsonPath('data.grounding', 'general_unverified')
            ->assertJsonPath('data.answer', $answer)
            ->assertJsonCount(0, 'data.sources');

        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '(tidak ada konteks khusus Younz yang relevan untuk pertanyaan ini)')
            && str_contains($prompt->prompt, 'ATURAN ANTI-HALUSINASI')
            && str_contains($prompt->prompt, 'informasi terkini/real-time'));
    }

    public function test_public_chat_stream_forwards_native_provider_deltas(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        $answer = 'Jawaban streaming ini dikirim langsung per delta provider tanpa menunggu respons lengkap.';
        KnowledgeBaseAgent::fake([$answer]);

        $response = $this->post('/tanya-ai/stream', [
            'message' => 'Apa manfaat menjaga jadwal tidur teratur?',
            'ai_consent' => '1',
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        preg_match_all('/^data: (.+)$/m', $response->streamedContent(), $matches);
        $events = collect($matches[1])
            ->reject(fn (string $data): bool => $data === '[DONE]')
            ->map(fn (string $data): array => json_decode($data, true, flags: JSON_THROW_ON_ERROR));
        $deltas = $events->where('type', 'text_delta')->pluck('delta')->values();

        $this->assertGreaterThan(2, $deltas->count());
        $this->assertSame('Jawaban', $deltas->first());
        $this->assertSame(' streaming', $deltas->get(1));
        $this->assertSame($answer, $deltas->implode(''));
        $this->assertTrue($events->contains('type', 'interaction'));
        $this->assertStringEndsWith("data: [DONE]\n\n", $response->streamedContent());
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'faq_chat',
            'provider' => 'openai-compatible',
        ]);
    }

    public function test_public_chat_stream_emits_local_answers_without_artificial_chunking(): void
    {
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $response = $this->post('/tanya-ai/stream', [
            'message' => 'hai younz',
            'ai_consent' => '1',
        ], ['Accept' => 'text/event-stream']);

        preg_match_all('/^data: (.+)$/m', $response->streamedContent(), $matches);
        $events = collect($matches[1])
            ->reject(fn (string $data): bool => $data === '[DONE]')
            ->map(fn (string $data): array => json_decode($data, true, flags: JSON_THROW_ON_ERROR));
        $deltas = $events->where('type', 'text_delta')->pluck('delta')->values();

        $response->assertOk();
        $this->assertCount(1, $deltas);
        $this->assertStringContainsString('Hai! Saya Younz AI.', $deltas->first());
        KnowledgeBaseAgent::assertNeverPrompted();
    }

    public function test_public_chat_handles_greetings_without_treating_them_as_missing_business_facts(): void
    {
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $this->postAiChat(['message' => 'hai younz'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'Hai! Saya Younz AI.'))
            ->assertJsonCount(0, 'data.sources');

        KnowledgeBaseAgent::assertNeverPrompted();
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'faq_chat',
            'provider' => 'local',
            'model' => 'conversation_guard',
            'status' => 'small_talk',
        ]);
    }

    public function test_public_chat_refuses_coding_before_prompting_external_ai(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $this->postAiChat(['message' => 'Buatkan kode PHP untuk login pelanggan.'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'tidak melayani pertanyaan atau bantuan coding/pemrograman'))
            ->assertJsonCount(0, 'data.sources');

        KnowledgeBaseAgent::assertNeverPrompted();
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'faq_chat',
            'provider' => 'local',
            'model' => 'policy_guard',
            'status' => 'blocked_coding',
        ]);
    }

    public function test_public_chat_does_not_treat_operational_codes_as_programming(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        KnowledgeBaseAgent::fake([
            'Kode OTP adalah kode verifikasi sekali pakai.',
            'Berikut naskah video promosi singkat yang dapat Anda sesuaikan.',
        ]);

        $this->postAiChat(['message' => 'Apa itu kode OTP?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', true);

        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Apa itu kode OTP?'));

        $this->postAiChat(['message' => 'Buatkan script video promosi singkat.'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', true);

        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Buatkan script video promosi singkat.'));
    }

    public function test_public_chat_keeps_refusing_a_short_follow_up_to_a_coding_request(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $this->postAiChat([
            'message' => 'Lanjutkan',
            'history' => [
                ['role' => 'user', 'content' => 'Tolong buatkan kode JavaScript untuk formulir login.'],
                ['role' => 'assistant', 'content' => 'Maaf, saya tidak melayani coding.'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'tidak melayani pertanyaan atau bantuan coding/pemrograman'));

        KnowledgeBaseAgent::assertNeverPrompted();
    }

    public function test_public_chat_never_invents_missing_younz_business_facts(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $this->postAiChat(['message' => 'Siapa pemilik Younz Digital Center?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.answer', 'Informasi tersebut belum tersedia di knowledge base. Silakan hubungi operator Younz Digital Center.')
            ->assertJsonCount(0, 'data.sources');

        KnowledgeBaseAgent::assertNeverPrompted();
    }

    public function test_public_chat_requires_a_current_source_for_realtime_questions(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        KnowledgeBaseAgent::fake()->preventStrayPrompts();

        $this->postAiChat(['message' => 'Berapa kurs rupiah hari ini?'])
            ->assertOk()
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'tidak dapat memverifikasi informasi real-time'))
            ->assertJsonCount(0, 'data.sources');

        KnowledgeBaseAgent::assertNeverPrompted();
        $this->assertDatabaseHas('ai_usage_logs', [
            'provider' => 'local',
            'model' => 'freshness_guard',
            'status' => 'requires_current_source',
        ]);
    }

    public function test_external_ai_prompt_redacts_customer_email_and_phone(): void
    {
        config([
            'ai.default' => 'openai-compatible',
            'ai.providers.openai-compatible.url' => 'https://llm.rapforum.online/v1',
            'ai.providers.openai-compatible.key' => 'test-key',
        ]);
        Service::create([
            'name' => 'Print Dokumen',
            'slug' => 'print-redaction-test',
            'type' => 'print',
            'base_price' => 1000,
            'unit' => 'lembar',
            'description' => 'Print dokumen pelanggan.',
            'is_active' => true,
        ]);
        KnowledgeBaseAgent::fake(['Silakan kirim dokumen untuk diperiksa.']);

        $this->postAiChat([
            'message' => 'Saya mau print. Email saya rahasia@example.test dan nomor 081355577799.',
        ])->assertOk();

        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '[EMAIL DIHAPUS]'));
        KnowledgeBaseAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '[NOMOR HP DIHAPUS]'));
        KnowledgeBaseAgent::assertNotPrompted(fn ($prompt) => str_contains($prompt->prompt, 'rahasia@example.test'));
        KnowledgeBaseAgent::assertNotPrompted(fn ($prompt) => str_contains($prompt->prompt, '081355577799'));
    }
}
