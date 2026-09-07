<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digiflazz_products', function (Blueprint $table): void {
            $table->string('transaction_type')->default('prepaid')->index()->after('id');
            $table->unsignedBigInteger('provider_admin')->default(0)->after('selling_price');
            $table->unsignedBigInteger('provider_commission')->default(0)->after('provider_admin');
            $table->dropUnique(['buyer_sku_code']);
            $table->unique(['transaction_type', 'buyer_sku_code']);
        });

        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->string('transaction_type')->default('prepaid')->index()->after('id');
            $table->string('provider_customer_name')->nullable()->after('customer_phone');
            $table->json('bill_details')->nullable()->after('provider_customer_name');
            $table->timestamp('inquired_at')->nullable()->after('expires_at');
            $table->timestamp('provider_payment_requested_at')->nullable()->after('inquired_at');
        });
    }

    public function down(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->dropIndex(['transaction_type']);
            $table->dropColumn([
                'transaction_type',
                'provider_customer_name',
                'bill_details',
                'inquired_at',
                'provider_payment_requested_at',
            ]);
        });

        Schema::table('digiflazz_products', function (Blueprint $table): void {
            $table->dropUnique(['transaction_type', 'buyer_sku_code']);
            $table->unique('buyer_sku_code');
            $table->dropIndex(['transaction_type']);
            $table->dropColumn(['transaction_type', 'provider_admin', 'provider_commission']);
        });
    }
};
