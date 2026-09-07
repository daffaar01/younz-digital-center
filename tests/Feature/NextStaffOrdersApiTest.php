<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\DigitalProduct;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NextStaffOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_view_and_update_orders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->order(['order_number' => 'ORD-NEXT-OWNER', 'status' => ServiceOrderStatus::AwaitingReview]);
        Sanctum::actingAs($owner, ['orders:read', 'orders:write']);

        $this->getJson('/api/v1/staff/orders?q=NEXT-OWNER')
            ->assertOk()
            ->assertJsonPath('data.orders.0.order_number', 'ORD-NEXT-OWNER')
            ->assertJsonMissingPath('data.orders.0.customer_phone');
        $this->getJson('/api/v1/staff/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.customer_phone', '081234567890')
            ->assertJsonPath('data.permissions.manage_financials', true);
        $this->postJson('/api/v1/staff/orders/'.$order->id.'/status', [
            'status' => ServiceOrderStatus::AwaitingOperator->value,
            'notes' => 'Diteruskan melalui Next.',
        ])->assertOk();
        $this->assertSame(ServiceOrderStatus::AwaitingOperator, $order->fresh()->status);
    }

    public function test_staff_order_scope_and_private_detail_follow_policy(): void
    {
        $designer = User::factory()->create(['role' => UserRole::Designer]);
        $other = User::factory()->create(['role' => UserRole::Designer]);
        $assigned = $this->order(['order_number' => 'ORD-ASSIGNED', 'type' => 'desain', 'assigned_to' => $designer->id]);
        $hidden = $this->order(['order_number' => 'ORD-HIDDEN', 'type' => 'desain', 'assigned_to' => $other->id]);
        Sanctum::actingAs($designer, ['orders:read', 'orders:write']);

        $this->getJson('/api/v1/staff/orders')->assertOk()
            ->assertJsonPath('data.orders.0.order_number', 'ORD-ASSIGNED')
            ->assertJsonMissing(['order_number' => 'ORD-HIDDEN']);
        $this->getJson('/api/v1/staff/orders/'.$assigned->id)
            ->assertOk()->assertJsonPath('data.permissions.view_files', true);
        $this->getJson('/api/v1/staff/orders/'.$hidden->id)->assertForbidden();
    }

    public function test_authenticated_generic_order_endpoint_rejects_digital_checkout_bypass(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $product = DigitalProduct::query()->create([
            'name' => 'Produk Bypass', 'category' => 'Uji', 'description' => 'Fixture lokal.',
            'image_path' => 'digital-products/test.webp', 'image_disk' => 'local', 'image_is_upload' => false,
            'price' => 10000, 'stock' => 2, 'is_active' => true, 'sort_order' => 1,
        ]);
        Sanctum::actingAs($owner, ['orders:write']);

        $this->postJson('/api/v1/orders', [
            'customer_name' => 'Pelanggan Digital',
            'customer_phone' => '081234567890',
            'type' => 'digital',
            'source' => 'digital-product-detail',
            'product_id' => $product->id,
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertDatabaseCount('service_orders', 0);
    }

    public function test_customer_and_read_only_token_cannot_modify_staff_orders(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->order();
        Sanctum::actingAs($customer, ['orders:read']);
        $this->getJson('/api/v1/staff/orders')->assertForbidden();
        Sanctum::actingAs($owner, ['orders:read']);
        $this->postJson('/api/v1/staff/orders/'.$order->id.'/status', [
            'status' => ServiceOrderStatus::AwaitingOperator->value,
        ])->assertForbidden();
    }

    public function test_generic_order_reads_cannot_bypass_staff_scope_but_customer_reads_remain_owned(): void
    {
        $designer = User::factory()->create(['role' => UserRole::Designer]);
        $hidden = $this->order(['order_number' => 'ORD-GENERIC-HIDDEN', 'type' => 'desain']);
        Sanctum::actingAs($designer, ['orders:read']);

        $this->getJson('/api/v1/orders')->assertForbidden();
        $this->getJson('/api/v1/orders/'.$hidden->id)->assertForbidden();

        $customerUser = User::factory()->create(['role' => UserRole::Customer]);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'name' => $customerUser->name,
            'email' => $customerUser->email,
            'phone' => '081234567891',
            'type' => 'umum',
        ]);
        $owned = $this->order([
            'order_number' => 'ORD-GENERIC-OWNED',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        Sanctum::actingAs($customerUser, ['orders:read']);
        $this->getJson('/api/v1/orders')->assertOk()
            ->assertJsonPath('data.0.order_number', 'ORD-GENERIC-OWNED');
        $this->getJson('/api/v1/orders/'.$owned->id)->assertOk()
            ->assertJsonPath('data.id', $owned->id);
        $this->getJson('/api/v1/orders/'.$hidden->id)->assertForbidden();
    }

    private function order(array $attributes = []): ServiceOrder
    {
        return ServiceOrder::create($attributes + [
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'public_token' => fake()->uuid(),
            'customer_name' => 'Pelanggan Uji',
            'customer_phone' => '081234567890',
            'type' => 'print',
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);
    }
}
