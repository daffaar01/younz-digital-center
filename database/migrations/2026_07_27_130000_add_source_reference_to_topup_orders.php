<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->string('source_reference')->nullable()->unique()->after('request_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->dropUnique(['source_reference']);
            $table->dropColumn('source_reference');
        });
    }
};