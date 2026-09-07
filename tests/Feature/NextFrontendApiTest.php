<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextFrontendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_endpoint_exposes_only_public_frontend_data(): void
    {
        $service = Service::create(['name' => 'Print Next', 'slug' => 'print-next', 'type' => 'print', 'unit' => 'lembar', 'base_price' => 2500, 'is_active' => true]);
        $category = Category::create(['name' => 'ATK', 'slug' => 'atk-next']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Kertas Next', 'slug' => 'kertas-next', 'sku' => 'NEXT-001', 'unit' => 'rim', 'cost_price' => 40000, 'selling_price' => 50000, 'stock' => 5, 'minimum_stock' => 1, 'is_active' => true]);
        Testimonial::create(['customer_name' => 'Pelanggan Next', 'quote' => 'Frontend cepat dan mudah.', 'rating' => 5, 'source_label' => 'Form', 'is_published' => true, 'consent_at' => now()]);
        Testimonial::create(['customer_name' => 'Tanpa Izin', 'quote' => 'Tidak boleh tampil.', 'rating' => 5, 'source_label' => 'Form', 'is_published' => true]);

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonPath('data.featured_products.0.id', $product->id)
            ->assertJsonPath('data.testimonials.0.customer_name', 'Pelanggan Next')
            ->assertJsonMissing(['customer_name' => 'Tanpa Izin'])
            ->assertJsonMissingPath('data.featured_products.0.cost_price')
            ->assertJsonStructure(['data' => ['services', 'featured_products', 'testimonials', 'store', 'meta']]);
    }
}
