<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE topup_orders ALTER COLUMN bill_details TYPE TEXT USING bill_details::text');

            return;
        }

        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->text('bill_details')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('topup_orders')->whereNotNull('bill_details')->exists()) {
            throw new LogicException('Encrypted bill details cannot be converted back to JSON safely.');
        }

        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->json('bill_details')->nullable()->change();
        });
    }
};
