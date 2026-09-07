<?php

namespace Tests\Feature;

use App\Actions\Sales\CheckoutSale;
use App\Enums\ApprovalType;
use App\Enums\UserRole;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\DigitalTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_product_lines_are_rejected_without_stock_mutation(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = $this->product();

        try {
            app(CheckoutSale::class)->handle(
                $cashier,
                [
                    ['product_id' => $product->id, 'quantity' => 4],
                    ['product_id' => $product->id, 'quantity' => 4],
                ],
                [['method' => 'cash', 'amount' => 8000]],
            );
            $this->fail('Produk duplikat seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(5, $product->fresh()->stock);
            $this->assertDatabaseCount('sales', 0);
        }
    }

    public function test_api_checkout_requires_a_cash_session(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = $this->product();
        $token = $cashier->createToken('test', ['pos:checkout'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_session_id');
    }

    public function test_only_one_open_cash_session_is_allowed_and_close_is_single_use(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $this->actingAs($cashier)->post('/cash-sessions', ['opening_balance' => 10000])->assertRedirect();
        $this->actingAs($cashier)->post('/cash-sessions', ['opening_balance' => 20000])->assertSessionHasErrors('opening_balance');
        $session = CashSession::firstOrFail();

        $this->actingAs($cashier)->post(route('cash-sessions.close', $session), ['closing_balance' => 10000])->assertSessionDoesntHaveErrors();
        $this->actingAs($cashier)->post(route('cash-sessions.close', $session), ['closing_balance' => 10000])->assertSessionHasErrors('closing_balance');
    }

    public function test_digital_transaction_idempotency_prevents_duplicate_approval(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $payload = [
            'type' => 'pulsa',
            'destination' => '08123456789',
            'destination_confirmation' => '08123456789',
            'nominal' => 10000,
            'cost_price' => 10500,
            'selling_price' => 11500,
            'admin_fee' => 0,
            'status' => 'diproses',
            'idempotency_key' => fake()->uuid(),
        ];

        $this->actingAs($cashier)->post(route('digital.store'), $payload)->assertRedirect();
        $this->actingAs($cashier)->post(route('digital.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('digital_transactions', 1);
        $this->assertDatabaseCount('approval_requests', 1);
        $this->assertSame(ApprovalType::DigitalTransaction, DigitalTransaction::firstOrFail()->load('approvalRequest')->approvalRequest?->type);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'ATK', 'slug' => fake()->unique()->slug()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Test',
            'slug' => fake()->unique()->slug(),
            'sku' => fake()->unique()->bothify('SKU-###'),
            'unit' => 'pcs',
            'cost_price' => 500,
            'selling_price' => 1000,
            'stock' => 5,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
    }
}
