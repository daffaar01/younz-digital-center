<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('topup_orders')
            ->whereNotNull('midtrans_snap_token')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $token = $order->midtrans_snap_token;

                    if (! is_string($token) || $token === '' || $this->isEncrypted($token)) {
                        continue;
                    }

                    DB::table('topup_orders')->where('id', $order->id)->update([
                        'midtrans_snap_token' => Crypt::encryptString($token),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Existing deployments may contain a mix of legacy and newly encrypted values.
        // Reverting all values to plaintext would expose payment tokens at rest.
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
