<?php

namespace Tests\Feature;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Jobs\SendServiceOrderWhatsAppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_customer_can_create_order_with_private_file_and_track_it(): void
    {
        Storage::fake('local');

        $response = $this->post('/pesan', [
            'customer_name' => 'Daffa',
            'customer_phone' => '08123456789',
            'type' => 'print',
            'specifications' => ['paper_size' => 'A4'],
            'notes' => 'Dua rangkap warna.',
            'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]);

        $order = ServiceOrder::firstOrFail();
        $response->assertRedirect(route('public.track.show', $order->public_token));
        $this->assertSame(ServiceOrderStatus::AwaitingReview, $order->status);
        Storage::disk('local')->assertExists($order->files()->firstOrFail()->path);
        $this->get(route('public.track.show', $order->public_token))->assertOk()->assertSee($order->order_number);
    }

    public function test_status_transition_is_validated_and_logged(): void
    {
        Queue::fake();
        $operator = User::factory()->create(['role' => UserRole::PrintOperator]);
        $order = ServiceOrder::create([
            'order_number' => 'ORD-20260720-0001',
            'public_token' => fake()->uuid(),
            'customer_name' => 'Pelanggan',
            'customer_phone' => '0812',
            'type' => 'print',
            'assigned_to' => $operator->id,
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);

        $this->actingAs($operator)->post(route('orders.status', $order), ['status' => ServiceOrderStatus::AwaitingOperator->value])
            ->assertRedirect();

        $this->assertSame(ServiceOrderStatus::AwaitingOperator, $order->fresh()->status);
        $this->assertDatabaseHas('service_order_status_histories', ['service_order_id' => $order->id, 'to_status' => ServiceOrderStatus::AwaitingOperator->value]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'service_order.status_changed', 'subject_id' => $order->id]);
        Queue::assertPushed(SendServiceOrderWhatsAppNotification::class, fn ($job) => $job->orderId === $order->id
            && $job->next === ServiceOrderStatus::AwaitingOperator);
    }
}
