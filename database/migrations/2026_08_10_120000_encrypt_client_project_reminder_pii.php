<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_reminders', function (Blueprint $table): void {
            $table->text('whatsapp')->nullable()->change();
            $table->text('email')->nullable()->change();
        });

        DB::table('client_project_reminders')
            ->select(['id', 'whatsapp', 'email', 'address'])
            ->orderBy('id')
            ->each(function (object $row): void {
                $updates = [];
                foreach (['whatsapp', 'email', 'address'] as $field) {
                    if ($row->{$field} !== null) {
                        $updates[$field] = Crypt::encryptString($row->{$field});
                    }
                }
                if ($updates !== []) {
                    DB::table('client_project_reminders')->where('id', $row->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        DB::table('client_project_reminders')
            ->select(['id', 'whatsapp', 'email', 'address'])
            ->orderBy('id')
            ->each(function (object $row): void {
                $updates = [];
                foreach (['whatsapp', 'email', 'address'] as $field) {
                    if ($row->{$field} !== null) {
                        $updates[$field] = Crypt::decryptString($row->{$field});
                    }
                }
                if ($updates !== []) {
                    DB::table('client_project_reminders')->where('id', $row->id)->update($updates);
                }
            });

        Schema::table('client_project_reminders', function (Blueprint $table): void {
            $table->string('whatsapp', 30)->nullable()->change();
            $table->string('email', 180)->nullable()->change();
        });
    }
};
