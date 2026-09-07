<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class WebAssetPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_raster_web_assets_only_use_webp(): void
    {
        $forbiddenExtensions = ['bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'tif', 'tiff'];
        $forbiddenFiles = [];

        foreach ([public_path(), resource_path()] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (in_array(mb_strtolower($file->getExtension()), $forbiddenExtensions, true)) {
                    $forbiddenFiles[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $forbiddenFiles, 'Aset raster lokal berikut harus dikonversi ke WebP: '.implode(', ', $forbiddenFiles));

        $favicon = file_get_contents(public_path('favicon.webp'));
        $this->assertIsString($favicon);
        $this->assertSame('RIFF', substr($favicon, 0, 4));
        $this->assertSame('WEBP', substr($favicon, 8, 4));
    }

    public function test_service_worker_only_caches_static_assets(): void
    {
        $serviceWorker = file_get_contents(resource_path('js/service-worker.js'));

        $this->assertIsString($serviceWorker);
        $this->assertStringContainsString("new Set(['font', 'image', 'script', 'style'])", $serviceWorker);
        $this->assertStringContainsString("['/build/', '/images/']", $serviceWorker);
        $this->assertStringContainsString("new Set(['/favicon.webp'])", $serviceWorker);
        $this->assertStringNotContainsString("'document'", $serviceWorker);
    }

    public function test_styles_do_not_depend_on_blocked_remote_fonts(): void
    {
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($stylesheet);
        $this->assertStringNotContainsString('fonts.googleapis.com', $stylesheet);
    }

    public function test_service_worker_response_is_never_cached(): void
    {
        $response = $this->get(route('service-worker'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_service_worker_is_enabled_only_on_the_public_host(): void
    {
        config()->set('app.staff_host', 'admin.example.test');
        config()->set('auth.staff_access.code_hash', 'configured-for-test');

        $this->get('https://example.test/')
            ->assertOk()
            ->assertSee('data-service-worker-enabled', false);
        $this->get('https://admin.example.test/akses-pegawai')
            ->assertOk()
            ->assertDontSee('data-service-worker-enabled', false);
    }
}
