<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['project_name', 'customer_name', 'notes'];

    public function up(): void
    {
        Schema::table('client_project_reminders', function (Blueprint $table): void {
            $table->text('project_name')->change();
            $table->text('customer_name')->change();
        });
        $this->transform(true);
    }

    public function down(): void
    {
        $this->transform(false);
        Schema::table('client_project_reminders', function (Blueprint $table): void {
            $table->string('project_name', 180)->change();
            $table->string('customer_name', 180)->change();
        });
    }

    private function transform(bool $encrypt): void
    {
        DB::table('client_project_reminders')->select(['id', ...self::FIELDS])->orderBy('id')
            ->each(function (object $row) use ($encrypt): void {
                $updates = [];
                foreach (self::FIELDS as $field) {
                    if ($row->{$field} !== null) {
                        $updates[$field] = $encrypt
                            ? Crypt::encryptString($row->{$field})
                            : Crypt::decryptString($row->{$field});
                    }
                }
                if ($updates !== []) {
                    DB::table('client_project_reminders')->where('id', $row->id)->update($updates);
                }
            });
    }
};
