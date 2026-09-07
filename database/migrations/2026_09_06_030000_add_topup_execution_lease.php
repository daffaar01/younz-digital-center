<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->uuid('execution_token')->nullable();
            $table->timestamp('execution_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->dropColumn(['execution_token', 'execution_expires_at']);
        });
    }
};
