<?php

namespace Tests\Feature;

use App\Enums\ApprovalType;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_stock_change_waits_for_separate_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $product = $this->product();

        $this->actingAs($admin)->post(route('products.stock', $product), [
            'quantity' => 5,
            'type' => 'adjustment',
            'reason' => 'Hasil stock opname.',
        ])->assertRedirect();

        $approval = ApprovalRequest::where('type', ApprovalType::StockAdjustment)->firstOrFail();
        $this->assertSame(10, $product->fresh()->stock);
        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionDoesntHaveErrors();
        $this->assertSame(15, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_price_change_waits_for_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $product = $this->product();
        $payload = [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'cost_price' => 700,
            'selling_price' => 1500,
            'minimum_stock' => 1,
            'is_active' => true,
        ];

        $this->actingAs($admin)->put(route('products.update', $product), $payload)->assertRedirect();
        $approval = ApprovalRequest::where('type', ApprovalType::ProductPriceChange)->firstOrFail();
        $this->assertSame(1000, $product->fresh()->selling_price);
        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionDoesntHaveErrors();
        $this->assertSame(1500, $product->fresh()->selling_price);
    }

    public function test_access_and_expense_changes_are_deferred(): void
    {
        $requestingOwner = User::factory()->create(['role' => UserRole::Owner]);
        $approvingOwner = User::factory()->create(['role' => UserRole::Owner]);
        $employee = User::factory()->create(['role' => UserRole::Cashier]);
        $category = ExpenseCategory::create(['name' => 'Operasional']);

        $this->actingAs($requestingOwner)->put(route('employees.update', $employee), [
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ])->assertRedirect();
        $accessApproval = ApprovalRequest::where('type', ApprovalType::EmployeeAccessChange)->firstOrFail();
        $this->assertSame(UserRole::Cashier, $employee->fresh()->role);
        $this->actingAs($approvingOwner)->post(route('approvals.approve', $accessApproval))->assertSessionDoesntHaveErrors();
        $this->assertSame(UserRole::Admin, $employee->fresh()->role);

        $this->actingAs($employee->fresh())->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'amount' => 50000,
            'description' => 'Biaya internet kantor.',
            'expense_date' => today()->toDateString(),
            'payment_method' => 'transfer',
        ])->assertRedirect();
        $expenseApproval = ApprovalRequest::where('type', ApprovalType::FinancialExpense)->firstOrFail();
        $this->assertDatabaseCount('expenses', 0);
        $this->actingAs($approvingOwner)->post(route('approvals.approve', $expenseApproval))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('expenses', ['amount' => 50000, 'description' => 'Biaya internet kantor.']);
    }

    public function test_sole_owner_can_bootstrap_a_second_owner_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->post(route('employees.store'), [
            'name' => 'Owner Kedua',
            'email' => 'owner.kedua@example.test',
            'phone' => '082100000002',
            'role' => UserRole::Owner->value,
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertRedirect();

        $secondOwner = User::query()->where('email', 'owner.kedua@example.test')->firstOrFail();
        $approval = ApprovalRequest::query()->where('type', ApprovalType::EmployeeAccessChange)->firstOrFail();

        $this->assertFalse($secondOwner->is_active);
        $this->actingAs($owner)->post(route('approvals.approve', $approval))->assertSessionDoesntHaveErrors();
        $this->assertTrue($secondOwner->fresh()->is_active);
        $this->assertSame(UserRole::Owner, $secondOwner->fresh()->role);

        $employee = User::factory()->create(['role' => UserRole::Cashier]);
        $this->actingAs($owner)->put(route('employees.update', $employee), [
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ])->assertRedirect();
        $secondApproval = ApprovalRequest::query()->where('subject_id', $employee->id)->firstOrFail();

        $this->actingAs($owner)->post(route('approvals.approve', $secondApproval))->assertSessionHasErrors('approval');
        $this->assertSame(UserRole::Cashier, $employee->fresh()->role);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'ATK', 'slug' => fake()->unique()->slug()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Approval',
            'slug' => fake()->unique()->slug(),
            'sku' => fake()->unique()->bothify('APP-###'),
            'unit' => 'pcs',
            'cost_price' => 500,
            'selling_price' => 1000,
            'stock' => 10,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
    }
}
