<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Jobs\DeleteDigitalProductImage;
use App\Models\DigitalProduct;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
    }

    public function test_owner_can_create_publish_update_and_delete_a_digital_product_with_private_image(): void
    {
        Storage::fake('local');
        config(['filesystems.digital_products_disk' => 'local']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:products']);

        $created = $this->post('/api/v1/staff/digital-products', [
            'name' => 'Canva Pro',
            'category' => 'AI Kreatif',
            'stock' => 8,
            'price' => 125000,
            'description' => 'Akses Canva Pro untuk kebutuhan desain.',
            'is_active' => '1',
            'sort_order' => 9,
            'image' => $this->fakePng('canva.png'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.name', 'Canva Pro')
            ->assertJsonPath('data.stock', 8)
            ->assertJsonPath('data.stock_label', '8 tersedia')
            ->assertJsonPath('data.price', 125000)
            ->assertJsonPath('data.price_label', 'Rp125.000');

        $id = $created->json('data.id');
        $created->assertJsonMissingPath('data.image_path');
        $imagePath = DigitalProduct::findOrFail($id)->image_path;
        $this->assertStringStartsWith('digital-products/', $imagePath);
        $this->assertStringNotContainsString('canva.png', $imagePath);
        Storage::disk('local')->assertExists($imagePath);

        $public = $this->getJson('/api/v1/digital-products')->assertOk()
            ->assertJsonMissingPath('data.0.image_path');
        $this->assertContains('Canva Pro', collect($public->json('data'))->pluck('name')->all());
        $this->get('/api/v1/digital-products/'.$id.'/image')->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $updated = $this->post('/api/v1/staff/digital-products/'.$id, [
            '_method' => 'PATCH',
            'name' => 'Canva Pro Teams',
            'category' => 'AI Kreatif',
            'stock' => '',
            'price' => '',
            'description' => 'Akses Canva Pro untuk tim kreatif.',
            'is_active' => '0',
            'sort_order' => 10,
            'image' => $this->fakePng('replacement.png'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.name', 'Canva Pro Teams')
            ->assertJsonPath('data.stock', null)
            ->assertJsonPath('data.stock_label', 'Konfirmasi stok')
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.price_label', 'Konfirmasi harga');

        $updated->assertJsonMissingPath('data.image_path');
        $newImagePath = DigitalProduct::findOrFail($id)->image_path;
        Storage::disk('local')->assertMissing($imagePath);
        Storage::disk('local')->assertExists($newImagePath);
        $publicAfterUpdate = $this->getJson('/api/v1/digital-products')->assertOk()->assertJsonCount(7, 'data');
        $this->assertNotContains('Canva Pro Teams', collect($publicAfterUpdate->json('data'))->pluck('name')->all());
        $this->get('/api/v1/digital-products/'.$id.'/image')->assertNotFound();
        $staffImage = $this->get('/api/v1/staff/digital-products/'.$id.'/image')->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('Authorization', (string) $staffImage->headers->get('Vary'));

        $this->deleteJson('/api/v1/staff/digital-products/'.$id)->assertOk();
        $this->assertDatabaseMissing('digital_products', ['id' => $id]);
        Storage::disk('local')->assertMissing($newImagePath);
    }

    public function test_owner_can_manage_duration_variants_with_authoritative_prices_and_stock(): void
    {
        Storage::fake('local');
        config(['filesystems.digital_products_disk' => 'local']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:products']);

        $created = $this->post('/api/v1/staff/digital-products', [
            'name' => 'Streaming Premium',
            'category' => 'Streaming',
            'description' => 'Paket streaming dengan pilihan durasi.',
            'is_active' => '1',
            'sort_order' => 50,
            'image' => $this->fakePng('streaming.png'),
            'variants' => [
                ['label' => '1 Hari', 'price' => 10000, 'stock' => 8, 'is_active' => '1', 'sort_order' => 1],
                ['label' => '1 Bulan', 'price' => 75000, 'stock' => 3, 'is_active' => '1', 'sort_order' => 2],
            ],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.0.label', '1 Hari')
            ->assertJsonPath('data.variants.0.price', 10000)
            ->assertJsonPath('data.variants.0.stock', 8)
            ->assertJsonPath('data.variants.1.label', '1 Bulan');

        $id = $created->json('data.id');
        $firstVariantId = $created->json('data.variants.0.id');
        $secondVariantId = $created->json('data.variants.1.id');
        $this->assertDatabaseHas('digital_product_variants', ['digital_product_id' => $id, 'label' => '1 Hari', 'price' => 10000, 'stock' => 8]);
        $this->getJson('/api/v1/digital-products/'.$id)
            ->assertOk()
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonMissingPath('data.variants.0.digital_product_id')
            ->assertJsonMissingPath('data.variants.0.is_active');

        $this->post('/api/v1/staff/digital-products/'.$id, [
            '_method' => 'PATCH', 'name' => 'Streaming Premium', 'category' => 'Streaming',
            'description' => 'Paket streaming dengan pilihan durasi.', 'sort_order' => 50,
            'variants' => [['id' => $firstVariantId, 'label' => '1 Hari', 'price' => 12000, 'stock' => 7, 'is_active' => '1', 'sort_order' => 1]],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.variants.0.id', $firstVariantId)
            ->assertJsonPath('data.variants.0.price', 12000);
        $this->assertDatabaseHas('digital_product_variants', ['id' => $secondVariantId, 'is_active' => false]);
        $this->getJson('/api/v1/digital-products/'.$id)->assertOk()->assertJsonCount(1, 'data.variants');

        $this->post('/api/v1/staff/digital-products/'.$id, [
            '_method' => 'PATCH', 'name' => 'Streaming Premium', 'category' => 'Streaming',
            'description' => 'Paket streaming dengan pilihan durasi.', 'sort_order' => 50,
            'variants_present' => '1',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'data.variants');
        $this->assertDatabaseMissing('digital_product_variants', ['digital_product_id' => $id, 'is_active' => true]);
        $this->getJson('/api/v1/digital-products/'.$id)->assertOk()->assertJsonCount(0, 'data.variants');
    }

    public function test_product_with_active_stock_reservation_cannot_be_deleted(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:products']);
        $product = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $order = ServiceOrder::query()->create([
            'order_number' => 'ORD-RESERVED-DELETE',
            'public_token' => fake()->uuid(),
            'customer_name' => 'Pelanggan Reservation',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'status' => ServiceOrderStatus::AwaitingReview,
            'specifications' => [
                'digital_product_id' => $product->id,
                'quantity' => 1,
                'stock_reserved' => true,
                'stock_released' => false,
            ],
        ]);

        $this->deleteJson('/api/v1/staff/digital-products/'.$product->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);
        $this->assertDatabaseHas('digital_products', ['id' => $product->id]);

        $order->update(['specifications' => [...$order->specifications, 'stock_released' => true]]);
        $this->deleteJson('/api/v1/staff/digital-products/'.$product->id)->assertOk();
        $this->assertDatabaseMissing('digital_products', ['id' => $product->id]);
    }

    public function test_validation_and_authorization_protect_digital_product_crud(): void
    {
        Storage::fake('local');
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        Sanctum::actingAs($cashier, ['staff:products']);
        $this->getJson('/api/v1/staff/digital-products')->assertForbidden();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:dashboard']);
        $this->postJson('/api/v1/staff/digital-products', [])->assertForbidden();

        Sanctum::actingAs($owner, ['staff:products']);
        $this->post('/api/v1/staff/digital-products', [
            'name' => 'Produk Uji',
            'category' => 'Tidak Valid',
            'description' => 'Deskripsi yang valid.',
            'image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'image']);
    }

    public function test_seeded_catalog_has_the_seven_current_products_in_display_order(): void
    {
        $response = $this->getJson('/api/v1/digital-products')->assertOk()->assertJsonCount(7, 'data');
        $this->assertSame('Discord Nitro', $response->json('data.0.name'));
        $this->assertSame('Leonardo AI', $response->json('data.6.name'));
        $this->assertSame('Konfirmasi stok', $response->json('data.0.stock_label'));
        $this->assertSame('Konfirmasi harga', $response->json('data.0.price_label'));
    }

    public function test_public_can_open_active_product_detail_but_not_inactive_product(): void
    {
        $active = DigitalProduct::query()->where('is_active', true)->firstOrFail();
        $inactive = DigitalProduct::query()->create([
            'name' => 'Produk Nonaktif',
            'category' => 'Komunitas',
            'description' => 'Produk ini tidak boleh terlihat publik.',
            'image_path' => 'digital-products/inactive.webp',
            'image_disk' => 'local',
            'image_is_upload' => true,
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $this->getJson('/api/v1/digital-products/'.$active->id)
            ->assertOk()
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.name', $active->name)
            ->assertJsonMissingPath('data.image_path')
            ->assertJsonMissingPath('data.image_disk')
            ->assertJsonMissingPath('data.is_active');

        $this->getJson('/api/v1/digital-products/'.$inactive->id)->assertNotFound();
    }

    public function test_upload_lifecycle_uses_configured_r2_disk(): void
    {
        Storage::fake('r2');
        config(['filesystems.digital_products_disk' => 'r2']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:products']);

        $created = $this->post('/api/v1/staff/digital-products', [
            'name' => 'Produk R2', 'category' => 'AI Kreatif', 'price' => 99000,
            'description' => 'Uji penyimpanan foto pada disk R2.', 'is_active' => '1',
            'sort_order' => 99, 'image' => $this->fakePng('r2.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $product = DigitalProduct::findOrFail($created->json('data.id'));
        $this->assertSame('r2', $product->image_disk);
        Storage::disk('r2')->assertExists($product->image_path);
        $this->get('/api/v1/digital-products/'.$product->id.'/image')->assertOk()->assertHeader('Content-Type', 'image/png');

        $path = $product->image_path;
        $this->deleteJson('/api/v1/staff/digital-products/'.$product->id)->assertOk();
        Storage::disk('r2')->assertMissing($path);
    }

    public function test_create_defaults_active_and_partial_update_preserves_status(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner, ['staff:products']);

        $created = $this->post('/api/v1/staff/digital-products', [
            'name' => 'Status Default', 'category' => 'Komunitas',
            'description' => 'Menguji default status aktif.', 'sort_order' => 90,
            'image' => $this->fakePng('status.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');
        $this->post('/api/v1/staff/digital-products/'.$id, [
            '_method' => 'PATCH', 'name' => 'Status Tetap', 'category' => 'Komunitas',
            'description' => 'Status tidak berubah saat field dihilangkan.', 'sort_order' => 90,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_image_cleanup_job_is_idempotent_and_disk_aware(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('digital-products/cleanup.webp', 'image-bytes');
        (new DeleteDigitalProductImage('r2', 'digital-products/cleanup.webp'))->handle();
        Storage::disk('r2')->assertMissing('digital-products/cleanup.webp');

        (new DeleteDigitalProductImage('r2', 'digital-products/cleanup.webp'))->handle();
        Storage::disk('r2')->assertMissing('digital-products/cleanup.webp');
    }
}
