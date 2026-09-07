<?php

namespace Tests\Feature;

use App\Models\TopupOrder;
use App\Services\LegacySampitmartTopupImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySampitmartTopupImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.legacy_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('legacy_test')->create('digital_products', function (Blueprint $table): void {
            $table->id();
            $table->string('brand')->nullable();
            $table->decimal('base_price', 14, 2)->default(0);
            $table->decimal('sell_price', 14, 2)->default(0);
        });
        Schema::connection('legacy_test')->create('digital_orders', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('digital_product_id')->nullable();
            $table->string('buyer_sku_code');
            $table->string('product_name');
            $table->string('product_category');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_no');
            $table->decimal('amount', 14, 2);
            $table->decimal('gross_amount', 14, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status');
            $table->string('topup_status');
            $table->string('digiflazz_ref_id')->nullable();
            $table->string('digiflazz_rc')->nullable();
            $table->text('serial_number')->nullable();
            $table->text('message')->nullable();
            $table->string('postpaid_customer_name')->nullable();
            $table->string('postpaid_period')->nullable();
            $table->decimal('postpaid_admin_fee', 14, 2)->default(0);
            $table->decimal('postpaid_price', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        DB::connection('legacy_test')->table('digital_products')->insert([
            'id' => 1,
            'brand' => 'TELKOMSEL',
            'base_price' => 10335,
            'sell_price' => 12000,
        ]);
        DB::connection('legacy_test')->table('digital_orders')->insert([
            'id' => 'topup-legacy-001',
            'digital_product_id' => 1,
            'buyer_sku_code' => 'Tel10',
            'product_name' => 'Telkomsel 10.000',
            'product_category' => 'Pulsa',
            'customer_name' => 'Pelanggan Lama',
            'customer_phone' => '081234567890',
            'customer_email' => 'lama@example.test',
            'customer_no' => '081234567890',
            'amount' => 12000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'topup_status' => 'success',
            'digiflazz_ref_id' => 'topup-legacy-001',
            'digiflazz_rc' => '00',
            'paid_at' => '2026-06-15 04:59:42',
            'processed_at' => '2026-06-15 05:00:00',
            'created_at' => '2026-06-15 04:59:16',
            'updated_at' => '2026-06-15 05:00:00',
        ]);
    }

    public function test_dry_run_does_not_write_and_commit_is_idempotent_and_encrypted(): void
    {
        $importer = app(LegacySampitmartTopupImporter::class);

        $dryRun = $importer->import('legacy_test');
        $this->assertSame(['source' => 1, 'eligible' => 1, 'created' => 0, 'skipped' => 0, 'conflicts' => 0], $dryRun);
        $this->assertDatabaseCount('topup_orders', 0);

        $committed = $importer->import('legacy_test', false);
        $this->assertSame(1, $committed['created']);
        $this->assertDatabaseCount('topup_orders', 1);

        $order = TopupOrder::query()->sole();
        $this->assertSame('legacy:sampitmart:digital_order:topup-legacy-001', $order->source_reference);
        $this->assertSame('081234567890', $order->destination);
        $this->assertSame(10335, $order->cost_price);
        $this->assertSame(12000, $order->total_amount);
        $this->assertSame('2026-06-15 04:59:16', $order->created_at->format('Y-m-d H:i:s'));
        $this->assertNotSame('081234567890', DB::table('topup_orders')->value('destination'));

        $repeated = $importer->import('legacy_test', false);
        $this->assertSame(0, $repeated['created']);
        $this->assertSame(1, $repeated['skipped']);
        $this->assertDatabaseCount('topup_orders', 1);
    }
}
