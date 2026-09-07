<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->string('open_guard')->nullable()->unique()->after('status');
        });

        DB::table('cash_sessions')
            ->where('status', 'open')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->each(function ($sessions, $userId): void {
                if ($session = $sessions->last()) {
                    DB::table('cash_sessions')
                        ->where('id', $session->id)
                        ->update(['open_guard' => 'user:'.$userId]);
                }
            });

        Schema::table('digital_transactions', function (Blueprint $table) {
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key');
        });

        Schema::table('service_files', function (Blueprint $table) {
            $table->string('scan_status')->default('validated')->after('kind');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
            $table->timestamp('expires_at')->nullable()->index()->after('scanned_at');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('interaction_token')->nullable()->unique()->after('feature');
            $table->string('feedback', 20)->nullable()->after('status');
            $table->timestamp('feedback_at')->nullable()->after('feedback');
        });

        Schema::table('approval_request_events', function (Blueprint $table) {
            $table->dropForeign(['approval_request_id']);
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->restrictOnDelete();
        });

        DB::table('knowledge_documents')->where('title', 'Jam Operasional')->update([
            'content' => 'Younz Digital Center buka '.config('services.store.open_hours').'.',
        ]);
    }

    public function down(): void
    {
        Schema::table('approval_request_events', function (Blueprint $table) {
            $table->dropForeign(['approval_request_id']);
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->cascadeOnDelete();
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropUnique(['interaction_token']);
            $table->dropColumn(['interaction_token', 'feedback', 'feedback_at']);
        });

        Schema::table('service_files', function (Blueprint $table) {
            $table->dropColumn(['scan_status', 'scanned_at', 'expires_at']);
        });

        Schema::table('digital_transactions', function (Blueprint $table) {
            $table->dropColumn('request_fingerprint');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropUnique(['open_guard']);
            $table->dropColumn('open_guard');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
