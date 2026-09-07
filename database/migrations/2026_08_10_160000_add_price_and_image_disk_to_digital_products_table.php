<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_products', function (Blueprint $table): void {
            $table->unsignedBigInteger('price')->nullable()->after('stock');
            $table->string('image_disk', 32)->default('local')->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table): void {
            $table->dropColumn(['price', 'image_disk']);
        });
    }
};
