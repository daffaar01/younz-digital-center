<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_fulfillment_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topup_order_id')->unique()->constrained('topup_orders')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_fulfillment_outbox');
    }
};
