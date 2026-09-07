<?php

namespace Tests\Feature;

use App\Actions\Approvals\RequestSaleRefund;
use App\Actions\Sales\CheckoutSale;
use App\Enums\ApprovalStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_request_partial_refund_without_immediate_stock_change(): void
    {
        [$cashier, $product, $sale] = $this->sale(quantity: 2, price: 5000, transactionDiscount: 1000);
        $saleItem = $sale->items->first();

        $this->actingAs($cashier)->post(route('sales.refunds.store', $sale), [
            'method' => 'cash',
            'reason' => 'Barang dikembalikan pelanggan.',
            'items' => [$saleItem->id => ['quantity' => 1, 'restore_stock' => true]],
        ])->assertRedirect(route('sales.show', $sale));

        $refund = Refund::firstOrFail();
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(4500, $refund->amount);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('approval_requests', [
            'subject_id' => $sale->id,
            'status' => ApprovalStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('approval_request_events', ['to_status' => ApprovalStatus::Pending->value]);
        $this->assertStringStartsWith('APR-', $refund->approvalRequest->request_number);
        $this->assertStringStartsWith('RFD-', $refund->refund_number);
    }

    public function test_cashier_cannot_open_or_decide_approval(): void
    {
        [$cashier, , $sale] = $this->sale();
        $approval = $this->requestRefund($cashier, $sale);

        $this->actingAs($cashier)->get(route('approvals.show', $approval))->assertForbidden();
        $this->actingAs($cashier)->post(route('approvals.approve', $approval))->assertForbidden();
        $this->actingAs($cashier)->post(route('approvals.reject', $approval), ['notes' => 'Tidak disetujui.'])->assertForbidden();
    }

    public function test_owner_approval_completes_refund_and_restores_stock_exactly_once(): void
    {
        [$cashier, $product, $sale] = $this->sale(quantity: 2);
        $approval = $this->requestRefund($cashier, $sale, quantity: 1);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->post(route('approvals.approve', $approval), ['notes' => 'Bukti retur sesuai.'])
            ->assertRedirect(route('approvals.show', $approval));

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame('partially_refunded', $sale->fresh()->status);
        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        $this->assertSame(RefundStatus::Completed, $approval->refund->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'return_customer',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'approval.approved', 'subject_id' => $approval->id]);

        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_rejection_does_not_restore_stock_and_releases_reserved_quantity(): void
    {
        [$cashier, $product, $sale] = $this->sale(quantity: 2);
        $approval = $this->requestRefund($cashier, $sale, quantity: 2);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('approvals.reject', $approval), ['notes' => 'Barang tidak diterima kembali.'])
            ->assertRedirect(route('approvals.show', $approval));

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame(ApprovalStatus::Rejected, $approval->fresh()->status);
        $this->assertSame(RefundStatus::Rejected, $approval->refund->fresh()->status);

        $newApproval = $this->requestRefund($cashier, $sale, quantity: 2);
        $this->assertSame(ApprovalStatus::Pending, $newApproval->status);
    }

    public function test_pending_and_completed_refunds_prevent_over_refund(): void
    {
        [$cashier, , $sale] = $this->sale(quantity: 2);
        $this->requestRefund($cashier, $sale, quantity: 1);
        $item = $sale->items->first();

        $this->actingAs($cashier)->post(route('sales.refunds.store', $sale), [
            'method' => 'cash',
            'reason' => 'Mencoba jumlah berlebih.',
            'items' => [$item->id => ['quantity' => 2, 'restore_stock' => true]],
        ])->assertSessionHasErrors("items.{$item->id}.quantity");

        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('approval_requests', 1);
    }

    public function test_requester_cannot_approve_own_request_when_separation_of_duties_is_enabled(): void
    {
        [, $product, $sale] = $this->sale();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $approval = $this->requestRefund($owner, $sale);

        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_admin_limit_requires_owner_for_large_refund(): void
    {
        config(['approvals.refund_owner_threshold' => 4000]);
        [$cashier, $product, $sale] = $this->sale(price: 5000);
        $approval = $this->requestRefund($cashier, $sale);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($admin)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);

        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionDoesntHaveErrors();
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('refunded', $sale->fresh()->status);
    }

    public function test_expired_approval_is_closed_without_refund_or_stock_mutation(): void
    {
        [$cashier, $product, $sale] = $this->sale();
        $approval = $this->requestRefund($cashier, $sale);
        $approval->update(['expires_at' => now()->subMinute()]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(ApprovalStatus::Expired, $approval->fresh()->status);
        $this->assertSame(RefundStatus::Cancelled, $approval->refund->fresh()->status);
        $this->assertDatabaseHas('approval_request_events', [
            'approval_request_id' => $approval->id,
            'to_status' => ApprovalStatus::Expired->value,
        ]);
    }

    public function test_scheduler_command_expires_overdue_approvals(): void
    {
        [$cashier, $product, $sale] = $this->sale();
        $approval = $this->requestRefund($cashier, $sale);
        $approval->update(['expires_at' => now()->subMinute()]);

        $this->artisan('approvals:expire')->expectsOutput('Expired 1 approval request(s).')->assertSuccessful();

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(ApprovalStatus::Expired, $approval->fresh()->status);
        $this->assertSame(RefundStatus::Cancelled, $approval->refund->fresh()->status);
    }

    public function test_cash_refund_is_subtracted_when_cash_session_is_closed(): void
    {
        [$cashier, , $sale] = $this->sale(withCashSession: true);
        $approval = $this->requestRefund($cashier, $sale);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionDoesntHaveErrors();

        $session = CashSession::findOrFail($sale->cash_session_id);
        $this->actingAs($owner)->post(route('cash-sessions.close', $session), [
            'closing_balance' => 10000,
        ])->assertSessionDoesntHaveErrors();

        $session->refresh();
        $this->assertSame(10000, $session->expected_balance);
        $this->assertSame(0, $session->difference);
        $this->actingAs($owner)->get(route('reports.daily'))->assertOk()->assertSee($approval->refund->refund_number);
    }

    public function test_full_refund_allocation_remains_exact_when_discount_rounding_creates_zero_value_lines(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $category = Category::create(['name' => 'Pembulatan', 'slug' => 'pembulatan']);
        $products = collect(range(1, 3))->map(fn (int $number) => Product::create([
            'category_id' => $category->id,
            'name' => "Item {$number}",
            'slug' => "item-{$number}",
            'sku' => "ROUND-{$number}",
            'unit' => 'pcs',
            'cost_price' => 0,
            'selling_price' => 1,
            'stock' => 10,
            'minimum_stock' => 0,
            'is_active' => true,
        ]));
        $this->actingAs($cashier);
        $sale = app(CheckoutSale::class)->handle(
            $cashier,
            $products->map(fn (Product $product) => ['product_id' => $product->id, 'quantity' => 1])->all(),
            [['method' => 'cash', 'amount' => 1]],
            discount: 2,
        );
        $requestedItems = $sale->items->mapWithKeys(fn ($item) => [
            $item->id => ['quantity' => 1, 'restore_stock' => true],
        ])->all();

        $refund = app(RequestSaleRefund::class)->handle(
            $cashier,
            $sale,
            $requestedItems,
            'cash',
            'Refund penuh dengan pembulatan diskon.',
        );

        $this->assertSame(1, $refund->amount);
        $this->assertSame(1, $refund->items->sum('amount'));
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post(route('approvals.approve', $refund->approvalRequest))->assertSessionDoesntHaveErrors();
        $this->assertSame('refunded', $sale->fresh()->status);
        $products->each(fn (Product $product) => $this->assertSame(10, $product->fresh()->stock));
    }

    /** @return array{User, Product, Sale} */
    private function sale(int $quantity = 1, int $price = 5000, int $transactionDiscount = 0, bool $withCashSession = false): array
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $category = Category::create(['name' => 'ATK', 'slug' => 'atk']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Pulpen Refund',
            'slug' => 'pulpen-refund',
            'sku' => 'RFD-001',
            'unit' => 'pcs',
            'cost_price' => 3000,
            'selling_price' => $price,
            'stock' => 10,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        $cashSession = $withCashSession ? CashSession::create([
            'user_id' => $cashier->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]) : null;
        $this->actingAs($cashier);
        $sale = app(CheckoutSale::class)->handle(
            $cashier,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            [['method' => 'cash', 'amount' => ($price * $quantity) - $transactionDiscount]],
            discount: $transactionDiscount,
            cashSession: $cashSession,
        );

        return [$cashier, $product, $sale];
    }

    private function requestRefund(User $requester, Sale $sale, int $quantity = 1): ApprovalRequest
    {
        $this->actingAs($requester);
        $item = $sale->items->first();
        $refund = app(RequestSaleRefund::class)->handle(
            $requester,
            $sale,
            [$item->id => ['quantity' => $quantity, 'restore_stock' => true]],
            'cash',
            'Pengembalian barang pelanggan.',
        );

        return $refund->approvalRequest;
    }
}
