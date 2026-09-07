<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('snap_creation_state')->nullable()->index()->after('midtrans_status');
            $table->timestamp('snap_creation_started_at')->nullable()->after('snap_creation_state');
            $table->unsignedBigInteger('refunded_amount')->default(0)->after('snap_creation_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropIndex(['snap_creation_state']);
            $table->dropColumn(['snap_creation_state', 'snap_creation_started_at', 'refunded_amount']);
        });
    }
};
