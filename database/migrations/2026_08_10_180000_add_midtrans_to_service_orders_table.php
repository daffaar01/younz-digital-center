<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->uuid('checkout_idempotency_key')->nullable()->unique()->after('public_token');
            $table->string('checkout_request_fingerprint', 64)->nullable()->after('checkout_idempotency_key');
            $table->string('payment_status')->default('unpaid')->index()->after('paid_amount');
            $table->string('midtrans_order_id')->nullable()->unique()->after('payment_status');
            $table->string('midtrans_transaction_id')->nullable()->unique()->after('midtrans_order_id');
            $table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_status')->nullable()->after('midtrans_payment_type');
            $table->text('midtrans_snap_token')->nullable()->after('midtrans_status');
            $table->text('midtrans_redirect_url')->nullable()->after('midtrans_snap_token');
            $table->timestamp('payment_expires_at')->nullable()->after('midtrans_redirect_url');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_idempotency_key']);
            $table->dropUnique(['midtrans_order_id']);
            $table->dropUnique(['midtrans_transaction_id']);
            $table->dropColumn([
                'checkout_idempotency_key',
                'checkout_request_fingerprint',
                'payment_status',
                'midtrans_order_id',
                'midtrans_transaction_id',
                'midtrans_payment_type',
                'midtrans_status',
                'midtrans_snap_token',
                'midtrans_redirect_url',
                'payment_expires_at',
            ]);
        });
    }
};
