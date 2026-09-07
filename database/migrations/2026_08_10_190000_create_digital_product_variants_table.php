<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('digital_product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['digital_product_id', 'label'], 'dpv_product_label_uq');
            $table->index(['digital_product_id', 'is_active', 'sort_order'], 'dpv_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_product_variants');
    }
};
