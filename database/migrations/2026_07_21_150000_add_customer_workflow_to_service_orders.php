<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('estimate_approved_at')->nullable()->after('estimated_price');
            $table->timestamp('payment_confirmation_at')->nullable()->after('paid_amount');
            $table->string('payment_confirmation_method')->nullable()->after('payment_confirmation_at');
            $table->string('payment_confirmation_reference')->nullable()->after('payment_confirmation_method');
            $table->unsignedSmallInteger('revision_requests')->default(0)->after('payment_confirmation_reference');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'estimate_approved_at',
                'payment_confirmation_at',
                'payment_confirmation_method',
                'payment_confirmation_reference',
                'revision_requests',
            ]);
        });
    }
};
