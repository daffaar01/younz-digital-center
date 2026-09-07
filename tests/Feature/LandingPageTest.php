<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stitch_landing_page_renders_active_services_and_real_routes(): void
    {
        $service = Service::create([
            'name' => 'Print Warna Premium',
            'slug' => 'print-warna-premium',
            'type' => 'print',
            'base_price' => 2500,
            'description' => 'Print warna tajam untuk kebutuhan profesional.',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Semua beres.')
            ->assertSee('landing-hero-visual', escape: false)
            ->assertSee('aria-label="Satu tempat. Semua beres."', escape: false)
            ->assertSee('data-count-up', escape: false)
            ->assertSee('data-count-to="3"', escape: false)
            ->assertSee('data-count-suffix=" langkah"', escape: false)
            ->assertSee('Print Warna Premium')
            ->assertSee('Rp 2.500')
            ->assertSee('data-ai-messages', escape: false)
            ->assertSee('data-ai-avatar-template', escape: false)
            ->assertDontSee('images/younz-ai-avatar.webp', escape: false)
            ->assertSee('younz-ai-monogram', escape: false)
            ->assertSee('favicon.webp', escape: false)
            ->assertSee('data-cookie-consent', escape: false)
            ->assertSee('data-cookie-settings', escape: false)
            ->assertSee('Peta lokasi Younz Digital Center')
            ->assertSee(config('services.store.maps_embed_url'))
            ->assertSee(route('public.order', ['service_id' => $service->id, 'source' => 'catalog']))
            ->assertSee('data-sticky-order-cta', escape: false)
            ->assertSee('"@context": "https://schema.org"', escape: false)
            ->assertSee(route('public.ai.chat'), escape: false);
    }

    public function test_service_card_link_preselects_service_on_order_form(): void
    {
        $service = Service::create([
            'name' => 'Desain Poster',
            'slug' => 'desain-poster-test',
            'type' => 'desain',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $response = $this->get(route('public.order', ['service_id' => $service->id]))
            ->assertOk()
            ->assertSee('data-order-service', escape: false);

        $this->assertMatchesRegularExpression(
            '/<option(?=[^>]*value="'.$service->id.'")(?=[^>]*\sselected(?:\s|=|>))[^>]*>/',
            $response->getContent(),
        );
    }

    public function test_cookie_consent_is_visible_on_first_visit_and_hidden_after_a_choice(): void
    {
        $firstVisitHtml = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<section\s+(?=[^>]*data-cookie-consent)(?=[^>]*data-cookie-consent-state="unset")(?![^>]*\shidden(?:\s|=|>))[^>]*>/',
            $firstVisitHtml,
        );

        $returnVisitHtml = $this
            ->withUnencryptedCookie('ydc_cookie_consent', 'essential')
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<section\s+(?=[^>]*data-cookie-consent)(?=[^>]*data-cookie-consent-state="saved")(?=[^>]*\shidden(?:\s|=|>))[^>]*>/',
            $returnVisitHtml,
        );
    }

    public function test_featured_products_use_a_uniform_product_grid(): void
    {
        $category = Category::create(['name' => 'ATK', 'slug' => 'atk-grid']);

        foreach (range(1, 4) as $number) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Produk ATK {$number}",
                'slug' => "produk-atk-{$number}",
                'sku' => "ATK-{$number}",
                'unit' => 'pcs',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'stock' => 10,
                'minimum_stock' => 1,
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('home'))->assertOk()->assertSee('data-product-grid', escape: false);

        $this->assertSame(4, substr_count($response->getContent(), 'data-product-card'));
    }

    public function test_only_published_testimonials_with_consent_are_shown(): void
    {
        Testimonial::create([
            'customer_name' => 'Pelanggan Terverifikasi',
            'customer_role' => 'Pemilik UMKM',
            'quote' => 'Pelayanan nyata dan dapat dilacak.',
            'rating' => 5,
            'source_label' => 'WhatsApp',
            'is_published' => true,
            'consent_at' => now(),
        ]);
        Testimonial::create([
            'customer_name' => 'Belum Disetujui',
            'quote' => 'Konten ini tidak boleh tampil.',
            'rating' => 5,
            'source_label' => 'Form',
            'is_published' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Pelanggan Terverifikasi')
            ->assertSee('Pelayanan nyata dan dapat dilacak.')
            ->assertDontSee('Belum Disetujui')
            ->assertDontSee('Ahmad R.');
    }

    public function test_order_page_preserves_the_acquisition_source(): void
    {
        $this->get(route('public.order', ['source' => 'sticky']))
            ->assertOk()
            ->assertSee('name="source" value="sticky"', escape: false)
            ->assertSee('data-order-form', escape: false);
    }
}


