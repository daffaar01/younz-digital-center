<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 40);
            $table->string('mark', 4)->nullable();
            $table->string('image_path', 500);
            $table->boolean('image_is_upload')->default(false);
            $table->unsignedInteger('stock')->nullable();
            $table->text('description');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('digital_products')->insert([
            ['name' => 'Discord Nitro', 'category' => 'Komunitas', 'mark' => 'DN', 'image_path' => '/products/discord-nitro.svg', 'description' => 'Akses benefit Nitro untuk pengalaman Discord yang lebih lengkap.', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Video Premium', 'category' => 'Streaming', 'mark' => 'VP', 'image_path' => '/products/video-premium.svg', 'description' => 'Pilihan akses hiburan video premium sesuai kebutuhan dan ketersediaan.', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'YouTube Premium', 'category' => 'Streaming', 'mark' => 'YT', 'image_path' => '/products/youtube-premium.svg', 'description' => 'Nikmati YouTube tanpa iklan dengan benefit premium yang tersedia.', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Claude AI', 'category' => 'AI Assistant', 'mark' => 'CL', 'image_path' => '/products/claude-ai.svg', 'description' => 'Akses asisten AI Claude untuk menulis, merangkum, dan membantu pekerjaan.', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ChatGPT', 'category' => 'AI Assistant', 'mark' => 'CG', 'image_path' => '/products/chatgpt.svg', 'description' => 'Pilihan akses ChatGPT untuk produktivitas, ide, dan kebutuhan kreatif.', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Grok', 'category' => 'AI Assistant', 'mark' => 'GR', 'image_path' => '/products/grok.svg', 'description' => 'Akses Grok untuk eksplorasi informasi dan percakapan berbasis AI.', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Leonardo AI', 'category' => 'AI Kreatif', 'mark' => 'LA', 'image_path' => '/products/leonardo-ai.svg', 'description' => 'Akses platform generatif untuk membuat visual dan aset kreatif.', 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_products');
    }
};
