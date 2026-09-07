<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_accept_only_their_own_estimate(): void
    {
        [$user, $customer] = $this->customer('owner@example.test', '081100000001');
        [$otherUser, $otherCustomer] = $this->customer('other@example.test', '081100000002');
        $order = $this->order($customer, ServiceOrderStatus::AwaitingCustomer, ['estimated_price' => 50_000]);

        $this->actingAs($otherUser)->post(route('customer.orders.estimate.accept', $order))->assertNotFound();
        $this->actingAs($user)->post(route('customer.orders.estimate.accept', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame(ServiceOrderStatus::AwaitingPayment, $order->status);
        $this->assertNotNull($order->estimate_approved_at);
        $this->assertSame($otherCustomer->id, $otherUser->customer->id);
    }

    public function test_payment_confirmation_requires_operator_verification_before_queue(): void
    {
        [$user, $customer] = $this->customer('pay@example.test', '081100000003');
        $order = $this->order($customer, ServiceOrderStatus::AwaitingPayment, [
            'estimated_price' => 75_000,
            'estimate_approved_at' => now(),
        ]);

        $this->actingAs($user)->post(route('customer.orders.payment.confirm', $order), [
            'method' => 'transfer',
            'reference' => 'TRX-12345',
        ])->assertRedirect();
        $this->assertSame(ServiceOrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->payment_confirmation_at);

        $operator = User::factory()->create(['role' => UserRole::Designer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order->update(['assigned_to' => $operator->id]);
        $this->actingAs($operator)->post(route('orders.status', $order), ['status' => ServiceOrderStatus::Queued->value])
            ->assertSessionHasErrors('status');
        $this->actingAs($operator)->patch(route('orders.details.update', $order), ['paid_amount' => 75_000])->assertForbidden();
        $this->actingAs($admin)->patch(route('orders.details.update', $order), ['paid_amount' => 75_000])->assertRedirect();
        $this->actingAs($operator)->post(route('orders.status', $order), ['status' => ServiceOrderStatus::Queued->value])->assertRedirect();

        $this->assertSame(ServiceOrderStatus::Queued, $order->fresh()->status);
    }

    public function test_customer_can_download_result_request_revision_and_view_invoice(): void
    {
        Storage::fake('local');
        [$user, $customer] = $this->customer('result@example.test', '081100000004');
        $order = $this->order($customer, ServiceOrderStatus::Ready, [
            'estimated_price' => 100_000,
            'final_price' => 90_000,
            'paid_amount' => 0,
        ]);
        $operator = User::factory()->create(['role' => UserRole::Designer]);
        $order->update(['assigned_to' => $operator->id]);

        $this->actingAs($operator)->post(route('orders.results.store', $order), [
            'file' => UploadedFile::fake()->create('hasil-desain.pdf', 50, 'application/pdf'),
        ])->assertRedirect();
        $file = $order->files()->where('kind', 'result')->firstOrFail();
        Storage::disk('local')->assertExists($file->path);

        $downloadUrl = URL::temporarySignedRoute('customer.orders.results.download', now()->addMinutes(5), [$order, $file]);
        $this->actingAs($user)->get($downloadUrl)->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->patch(route('orders.details.update', $order), ['paid_amount' => 90_000])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->actingAs($user)->get($downloadUrl)->assertOk();
        $this->actingAs($user)->get(route('customer.orders.invoice', $order))->assertOk()->assertSee('Rp 90.000');

        $this->actingAs($user)->post(route('customer.orders.revision.request', $order), [
            'notes' => 'Mohon warna judul dibuat lebih gelap.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(ServiceOrderStatus::AwaitingRevision, $order->status);
        $this->assertSame(1, $order->revision_requests);
    }

    public function test_private_order_files_require_assignment_and_customer_payment_release(): void
    {
        Storage::fake('local');
        [$customerUser, $customer] = $this->customer('private@example.test', '081100000005');
        $assignedDesigner = User::factory()->create(['role' => UserRole::Designer]);
        $otherDesigner = User::factory()->create(['role' => UserRole::Designer]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $order = $this->order($customer, ServiceOrderStatus::Ready, [
            'assigned_to' => $assignedDesigner->id,
            'final_price' => 90_000,
            'paid_amount' => 0,
        ]);

        $this->actingAs($otherDesigner)->post(route('orders.results.store', $order), [
            'file' => UploadedFile::fake()->create('hasil-rahasia.pdf', 50, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($cashier)->post(route('orders.results.store', $order), [
            'file' => UploadedFile::fake()->create('hasil-rahasia.pdf', 50, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($assignedDesigner)->post(route('orders.results.store', $order), [
            'file' => UploadedFile::fake()->create('hasil-rahasia.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $file = $order->files()->where('kind', 'result')->firstOrFail();
        $staffDownloadUrl = URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), [$order, $file]);
        $customerDownloadUrl = URL::temporarySignedRoute('customer.orders.results.download', now()->addMinutes(5), [$order, $file]);

        $this->actingAs($otherDesigner)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($otherDesigner)->get(route('orders.index'))->assertOk()->assertDontSee($order->order_number);
        $this->actingAs($cashier)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($assignedDesigner)->get(route('orders.show', $order))->assertOk()->assertSee($order->order_number);
        $this->actingAs($otherDesigner)->get($staffDownloadUrl)->assertForbidden();
        $this->actingAs($cashier)->get($staffDownloadUrl)->assertForbidden();
        $this->actingAs($assignedDesigner)->get($staffDownloadUrl)->assertOk();
        $this->actingAs($customerUser)->get($customerDownloadUrl)->assertForbidden();

        $order->update(['paid_amount' => 90_000]);
        $this->actingAs($customerUser)->get($customerDownloadUrl)->assertForbidden();

        $order->update(['payment_status' => 'paid', 'paid_amount' => 45_000]);
        $this->actingAs($customerUser)->get($customerDownloadUrl)->assertForbidden();

        $order->update(['paid_amount' => 90_000]);
        $this->actingAs($customerUser)->get($customerDownloadUrl)->assertOk();
        $this->actingAs($customerUser)->get(route('customer.orders.results.download', [$order, $file]))->assertForbidden();
    }

    public function test_manual_payment_status_tracks_partial_and_corrected_amounts(): void
    {
        [, $customer] = $this->customer('manual-payment@example.test', '081100000006');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order($customer, ServiceOrderStatus::Ready, ['final_price' => 90_000]);

        foreach ([[45_000, 'unpaid'], [90_000, 'paid'], [0, 'unpaid']] as [$amount, $status]) {
            $this->actingAs($admin)->patch(route('orders.details.update', $order), ['paid_amount' => $amount])->assertRedirect();
            $order->refresh();
            $this->assertSame($status, $order->payment_status);
            $this->assertSame($status === 'paid', $order->canReleaseResults());
        }
    }

    public function test_manual_financial_updates_do_not_override_provider_or_refund_status(): void
    {
        [, $customer] = $this->customer('protected-payment@example.test', '081100000007');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([
            ['type' => 'digital', 'payment_status' => 'unpaid'],
            ['midtrans_order_id' => 'MT-PROTECTED-PAYMENT', 'payment_status' => 'pending'],
            ['payment_status' => 'refunded'],
            ['payment_status' => 'partial_refunded'],
        ] as $attributes) {
            $order = $this->order($customer, ServiceOrderStatus::Ready, $attributes + ['final_price' => 90_000]);
            $this->actingAs($admin)->patch(route('orders.details.update', $order), ['paid_amount' => 90_000])->assertRedirect();
            $order->refresh();
            $this->assertSame($attributes['payment_status'], $order->payment_status);
            $this->assertFalse($order->canReleaseResults());
        }
    }

    /** @return array{User, Customer} */
    private function customer(string $email, string $phone): array
    {
        $user = User::factory()->create(['email' => $email, 'phone' => $phone, 'role' => UserRole::Customer]);
        $customer = Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $email, 'phone' => $phone]);

        return [$user, $customer];
    }

    /** @param array<string, mixed> $attributes */
    private function order(Customer $customer, ServiceOrderStatus $status, array $attributes = []): ServiceOrder
    {
        return ServiceOrder::create($attributes + [
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'public_token' => fake()->uuid(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'type' => 'desain',
            'status' => $status,
        ]);
    }
}
