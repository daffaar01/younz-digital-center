<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digiflazz_products', function (Blueprint $table): void {
            $table->id();
            $table->string('buyer_sku_code')->unique();
            $table->string('product_name');
            $table->string('category')->index();
            $table->string('brand')->index();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cost_price');
            $table->unsignedBigInteger('selling_price');
            $table->boolean('buyer_product_status')->default(false);
            $table->boolean('seller_product_status')->default(false);
            $table->boolean('unlimited_stock')->default(false);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('multi')->default(false);
            $table->string('start_cut_off', 5)->nullable();
            $table->string('end_cut_off', 5)->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('topup_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->uuid('public_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('digiflazz_product_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('sku');
            $table->string('product_name');
            $table->string('category');
            $table->string('brand');
            $table->string('destination')->index();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 24);
            $table->unsignedBigInteger('cost_price');
            $table->unsignedBigInteger('selling_price');
            $table->unsignedBigInteger('admin_fee')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('payment_status')->index();
            $table->string('fulfillment_status')->index();
            $table->string('midtrans_order_id')->unique();
            $table->string('midtrans_transaction_id')->nullable()->unique();
            $table->string('midtrans_payment_type')->nullable();
            $table->string('midtrans_status')->nullable();
            $table->text('midtrans_snap_token')->nullable();
            $table->text('midtrans_redirect_url')->nullable();
            $table->string('digiflazz_reference')->nullable()->unique();
            $table->text('serial_number')->nullable();
            $table->text('provider_message')->nullable();
            $table->string('provider_rc', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_orders');
        Schema::dropIfExists('digiflazz_products');
    }
};
