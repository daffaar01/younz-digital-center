<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KnowledgeDocument;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_knowledge_base_with_an_audit_trail(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->post(route('knowledge.store'), [
            'title' => 'Prosedur revisi desain',
            'type' => 'guide',
            'content' => 'Pelanggan dapat mengajukan revisi sesuai batas yang disepakati operator.',
            'status' => 'active',
        ])->assertRedirect();

        $document = KnowledgeDocument::query()->where('title', 'Prosedur revisi desain')->firstOrFail();
        $this->assertSame($owner->id, $document->created_by);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'knowledge_document.created',
            'subject_id' => $document->id,
        ]);

        $this->actingAs($owner)->put(route('knowledge.update', $document), [
            'title' => $document->title,
            'type' => 'guide',
            'content' => 'Revisi harus dicatat dan dikonfirmasi operator.',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('knowledge_documents', [
            'id' => $document->id,
            'status' => 'draft',
        ]);
    }

    public function test_cashier_cannot_manage_knowledge_or_testimonials(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);

        $this->actingAs($cashier)->get(route('knowledge.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('testimonials.index'))->assertForbidden();
    }

    public function test_published_testimonial_requires_recorded_customer_consent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $payload = [
            'customer_name' => 'Pelanggan Terverifikasi',
            'customer_role' => 'Pemilik UMKM',
            'quote' => 'Proses pemesanan jelas dan hasilnya sesuai kebutuhan.',
            'rating' => 5,
            'source_label' => 'WhatsApp',
            'display_order' => 1,
            'is_published' => '1',
        ];

        $this->actingAs($admin)->post(route('testimonials.store'), $payload)
            ->assertSessionHasErrors('consent_at');

        $this->actingAs($admin)->post(route('testimonials.store'), $payload + [
            'consent_at' => now()->toDateString(),
        ])->assertRedirect();

        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertTrue($testimonial->is_published);
        $this->assertNotNull($testimonial->consent_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'testimonial.created',
            'subject_id' => $testimonial->id,
        ]);
    }
}
