<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $encryptedColumns = [
        'destination',
        'customer_name',
        'customer_email',
        'customer_phone',
        'midtrans_snap_token',
        'midtrans_redirect_url',
        'provider_customer_name',
        'bill_details',
        'serial_number',
        'provider_message',
    ];

    public function up(): void
    {
        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->text('destination')->change();
            $table->text('customer_name')->change();
            $table->text('customer_email')->change();
            $table->text('customer_phone')->change();
            $table->text('provider_customer_name')->nullable()->change();
        });

        $this->transform(fn (string $value): string => Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->transform(fn (string $value): string => Crypt::decryptString($value));

        Schema::table('topup_orders', function (Blueprint $table): void {
            $table->string('destination', 255)->change();
            $table->string('customer_name', 255)->change();
            $table->string('customer_email', 255)->change();
            $table->string('customer_phone', 24)->change();
            $table->string('provider_customer_name')->nullable()->change();
        });
    }

    /** @param callable(string): string $transform */
    private function transform(callable $transform): void
    {
        DB::table('topup_orders')->orderBy('id')->chunkById(100, function ($orders) use ($transform): void {
            foreach ($orders as $order) {
                $updates = [];

                foreach ($this->encryptedColumns as $column) {
                    $value = $order->{$column};

                    if (is_string($value) && $value !== '') {
                        $updates[$column] = $transform($value);
                    }
                }

                if ($updates !== []) {
                    DB::table('topup_orders')->where('id', $order->id)->update($updates);
                }
            }
        });
    }
};
