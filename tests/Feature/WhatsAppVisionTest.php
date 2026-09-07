<?php

namespace Tests\Feature;

use App\Ai\Agents\KnowledgeBaseAgent;
use App\Ai\ProviderClient;
use App\Ai\VisionImage;
use App\Jobs\ProcessWhatsAppImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppVisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_sends_real_multimodal_payload(): void
    {
        config()->set('ai.vision', ['enabled' => true, 'model' => 'vision-test']);
        config()->set('ai.providers.fixture', ['driver' => 'openai-compatible', 'url' => 'https://ai.example.test/v1', 'key' => 'fixture']);
        Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'Gambar uji.']]]])]);
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $response = app(ProviderClient::class)->prompt(new KnowledgeBaseAgent, 'Jelaskan', [VisionImage::fromBytes($bytes)], provider: 'fixture');
        $this->assertSame('Gambar uji.', $response->text);
        Http::assertSent(fn ($request) => $request['model'] === 'vision-test'
            && $request['messages'][1]['content'][0]['type'] === 'text'
            && str_starts_with($request['messages'][1]['content'][1]['image_url']['url'], 'data:image/png;base64,'));
    }

    public function test_text_and_images_use_separate_endpoints_and_credentials(): void
    {
        config()->set('ai.providers.fixture', [
            'driver' => 'openai-compatible', 'url' => 'https://router.example.test/v1',
            'key' => 'router-key', 'models' => ['text' => ['default' => 'text-model']],
        ]);
        config()->set('ai.vision', [
            'enabled' => true, 'model' => 'vision-model',
            'url' => 'https://vision.example.test/v1', 'key' => 'vision-key',
        ]);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $client = app(ProviderClient::class);
        $client->prompt(new KnowledgeBaseAgent, 'Halo', provider: 'fixture');
        $client->prompt(new KnowledgeBaseAgent, 'Jelaskan', [VisionImage::fromBytes($bytes)], provider: 'fixture');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://router.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer router-key')
            && $request['model'] === 'text-model'
            && is_string($request['messages'][1]['content']));
        Http::assertSent(fn ($request) => $request->url() === 'https://vision.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer vision-key')
            && $request['model'] === 'vision-model'
            && $request['messages'][1]['content'][1]['type'] === 'image_url');
    }

    public function test_partial_vision_configuration_never_sends_text_credentials(): void
    {
        config()->set('ai.providers.fixture', ['driver' => 'openai-compatible', 'url' => 'https://router.example.test/v1', 'key' => 'router-key']);
        Http::fake();
        foreach ([['url' => 'https://vision.example.test/v1'], ['key' => 'vision-key']] as $partial) {
            config()->set('ai.vision', $partial + ['enabled' => true, 'model' => 'vision-model']);
            try {
                app(ProviderClient::class)->prompt(new KnowledgeBaseAgent, 'Jelaskan', [null], provider: 'fixture');
                $this->fail('Incomplete vision configuration must fail closed.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('URL dan API key vision khusus harus diisi bersamaan.', $exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_invalid_image_is_rejected_before_provider_call(): void
    {
        $this->expectException(\RuntimeException::class);
        VisionImage::fromBytes('not an image');
    }

    public function test_terminal_failure_cleans_private_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('whatsapp-vision/test.png', 'fixture');
        $job = new ProcessWhatsAppImage('whatsapp-vision/test.png', '628123456789@s.whatsapp.net', '');
        $job->failed(new \RuntimeException('fixture'));
        Storage::disk('local')->assertMissing('whatsapp-vision/test.png');
        $this->assertStringNotContainsString('628123456789', ProcessWhatsAppImage::consentKey('628123456789@s.whatsapp.net'));
    }
}
