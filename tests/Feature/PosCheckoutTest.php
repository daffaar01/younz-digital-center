<?php

namespace Tests\Feature;

use App\Actions\Sales\CheckoutSale;
use App\Enums\UserRole;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_sale_payment_snapshot_and_stock_movement_atomically(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = $this->product(stock: 10, price: 5000, cost: 3000);
        $session = CashSession::create(['user_id' => $cashier->id, 'opening_balance' => 100000, 'status' => 'open', 'opened_at' => now()]);
        $this->actingAs($cashier);

        $sale = app(CheckoutSale::class)->handle(
            $cashier,
            [['product_id' => $product->id, 'quantity' => 2]],
            [['method' => 'cash', 'amount' => 10000]],
            cashSession: $session,
        );

        $this->assertSame(10000, $sale->total);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'unit_price' => 5000, 'cost_price' => 3000, 'quantity' => 2]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => -2, 'stock_before' => 10, 'stock_after' => 8]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale.completed', 'subject_id' => $sale->id]);
        $this->get(route('sales.receipt', $sale))->assertOk()->assertSee($sale->invoice_number);
    }

    public function test_failed_checkout_does_not_reduce_stock_or_create_sale(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = $this->product(stock: 1, price: 5000, cost: 3000);

        try {
            app(CheckoutSale::class)->handle(
                $cashier,
                [['product_id' => $product->id, 'quantity' => 2]],
                [['method' => 'cash', 'amount' => 10000]],
            );
            $this->fail('Checkout seharusnya gagal.');
        } catch (ValidationException) {
            $this->assertSame(1, $product->fresh()->stock);
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    private function product(int $stock, int $price, int $cost): Product
    {
        $category = Category::create(['name' => 'ATK', 'slug' => 'atk']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Pulpen Test',
            'slug' => 'pulpen-test',
            'sku' => 'TEST-001',
            'unit' => 'pcs',
            'cost_price' => $cost,
            'selling_price' => $price,
            'stock' => $stock,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
    }
}
