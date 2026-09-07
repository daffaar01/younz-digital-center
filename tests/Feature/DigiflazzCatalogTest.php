<?php

namespace Tests\Feature;

use App\Enums\DigiflazzTransactionType;
use App\Models\DigiflazzProduct;
use App\Models\TopupOrder;
use App\Support\DigiflazzCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigiflazzCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_availability_means_catalog_without_creating_transactions(): void
    {
        $this->product();
        $answer = app(DigiflazzCatalog::class)->answer('pembayaran apa yang tersedia?');
        $this->assertStringContainsString('TELKOMSEL', $answer);
        $this->assertSame(0, TopupOrder::count());
        Http::assertNothingSent();
    }

    public function test_payment_methods_and_explicit_purchase_are_not_catalog_questions(): void
    {
        foreach (['bayarnya pakai apa?', 'metode pembayaran apa?', 'BELI pulsa Telkomsel 10000 ke nomor 081234567890'] as $question) {
            $this->assertNull(app(DigiflazzCatalog::class)->answer($question));
        }
    }

    public function test_unavailable_products_are_not_offered(): void
    {
        $product = $this->product();
        $product->update(['seller_product_status' => false]);
        $answer = app(DigiflazzCatalog::class)->answer('pulsa Telkomsel tersedia?');
        $this->assertStringContainsString('belum tersedia', $answer);
        $this->assertStringNotContainsString('Telkomsel 10.000', $answer);
    }

    public function test_postpaid_zero_price_is_not_presented_as_free(): void
    {
        $product = $this->product();
        $product->update(['transaction_type' => DigiflazzTransactionType::Postpaid, 'brand' => 'PLN', 'product_name' => 'PLN Pascabayar', 'selling_price' => 0]);
        $answer = app(DigiflazzCatalog::class)->answer('tagihan PLN apa yang tersedia?');
        $this->assertStringContainsString('nominal setelah cek tagihan', $answer);
        $this->assertStringNotContainsString('Rp0', $answer);
    }

    public function test_follow_up_pages_through_groups_and_stops_at_the_end(): void
    {
        $template = $this->product();
        for ($i = 0; $i < 22; $i++) {
            $product = $template->replicate();
            $product->buyer_sku_code = 'group-'.$i;
            $product->brand = sprintf('BRAND%02d', $i);
            $product->save();
        }
        $catalog = app(DigiflazzCatalog::class);
        $context = null;
        $first = $catalog->answer('pembayaran apa yang tersedia?', $context);
        $this->assertStringContainsString('BRAND00', $first);
        $this->assertStringNotContainsString('BRAND20', $first);
        $second = $catalog->answer('Masih ada layanan lainnya?', $context);
        $this->assertStringContainsString("*Daftar Layanan Digiflazz*\nHalaman 1", $first);
        $this->assertStringContainsString('1. Pulsa — BRAND00', $first);
        $this->assertStringContainsString('21. Pulsa — BRAND20', $second);
        $this->assertStringContainsString('Halaman 2', $second);
        $this->assertStringNotContainsString('•', $first.$second);
        $this->assertStringNotContainsString('BRAND00', $second);
        $this->assertStringContainsString('Semua hasil', $catalog->answer('LANJUT KATALOG', $context));
        $this->assertSame(0, TopupOrder::count());
        Http::assertNothingSent();
    }

    public function test_product_follow_up_preserves_filter_and_new_query_resets_page(): void
    {
        $template = $this->product();
        for ($i = 0; $i < 12; $i++) {
            $product = $template->replicate();
            $product->buyer_sku_code = 'product-'.$i;
            $product->product_name = sprintf('Telkomsel Paket %02d', $i);
            $product->save();
        }
        $catalog = app(DigiflazzCatalog::class);
        $context = null;
        $first = $catalog->answer('pulsa Telkomsel tersedia?', $context);
        $this->assertStringNotContainsString('Paket 11', $first);
        $next = $catalog->answer('produk lainnya?', $context);
        $this->assertStringContainsString("1. *Telkomsel 10.000*\n   Harga: Rp12.000", $first);
        $this->assertStringContainsString('11. *Telkomsel Paket 09*', $next);
        $this->assertStringContainsString('13. *Telkomsel Paket 11*', $next);
        $this->assertStringNotContainsString('•', $first.$next);
        $this->assertSame($first, $catalog->answer('pulsa Telkomsel tersedia?', $context));
        $this->assertNull($catalog->answer('BELI pulsa Telkomsel 10000', $context));
        Http::assertNothingSent();
    }

    public function test_follow_up_without_context_requests_a_catalog_question(): void
    {
        $this->assertStringContainsString('Sebutkan katalog', app(DigiflazzCatalog::class)->answer('Masih ada layanan lainnya?'));
        Http::assertNothingSent();
    }

    public function test_number_opens_exact_category_with_real_package_details(): void
    {
        $axis = $this->product();
        $axis->update(['category' => 'Data', 'brand' => 'AXIS', 'product_name' => 'AXIS 10GB', 'description' => '<b>10GB</b> 7 hari']);
        $other = $axis->replicate();
        $other->buyer_sku_code = 'axis-pulsa';
        $other->category = 'Pulsa';
        $other->product_name = 'Pulsa AXIS';
        $other->save();
        $catalog = app(DigiflazzCatalog::class);
        $context = null;
        $this->assertStringContainsString('1. Data — AXIS', $catalog->answer('pembayaran apa yang tersedia?', $context));
        $answer = $catalog->answer('1', $context);
        $this->assertStringContainsString('AXIS 10GB', $answer);
        $this->assertStringContainsString('10GB 7 hari', $answer);
        $this->assertStringContainsString('Rp12.000', $answer);
        $this->assertStringNotContainsString('<b>', $answer);
        $this->assertStringNotContainsString('Pulsa AXIS', $answer);
        $this->assertStringContainsString('Untuk membeli, gunakan BELI', $catalog->answer('1', $context));
        $this->assertNull($catalog->answer('123456', $context));
        $this->assertSame(0, TopupOrder::count());
        Http::assertNothingSent();
    }

    public function test_number_selection_rechecks_availability_and_rejects_unknown_numbers(): void
    {
        $product = $this->product();
        $catalog = app(DigiflazzCatalog::class);
        $context = null;
        $catalog->answer('pembayaran apa yang tersedia?', $context);
        $this->assertStringContainsString('Nomor layanan tidak tersedia', $catalog->answer('99', $context));
        $product->update(['seller_product_status' => false]);
        $this->assertStringContainsString('belum tersedia', $catalog->answer('1', $context));
        $this->assertStringContainsString('sesi katalog sudah berakhir', $catalog->answer('1'));
        Http::assertNothingSent();
    }

    private function product(): DigiflazzProduct
    {
        return DigiflazzProduct::create([
            'transaction_type' => DigiflazzTransactionType::Prepaid,
            'buyer_sku_code' => 'catalog-test', 'product_name' => 'Telkomsel 10.000',
            'category' => 'Pulsa', 'brand' => 'TELKOMSEL', 'cost_price' => 10000,
            'selling_price' => 12000, 'buyer_product_status' => true,
            'seller_product_status' => true, 'unlimited_stock' => true, 'stock' => 0, 'multi' => false,
        ]);
    }
}
