<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_fulfillment_outbox', fn (Blueprint $table) => $table->uuid('generation')->nullable());
    }

    public function down(): void
    {
        Schema::table('topup_fulfillment_outbox', fn (Blueprint $table) => $table->dropColumn('generation'));
    }
};
