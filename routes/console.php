<?php

use App\Actions\ApplyServiceOrderMidtransStatus;
use App\Jobs\DeleteDigitalProductImage;
use App\Models\DigitalProduct;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;

Schedule::command('topup:recover-fulfillment')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function (): void {
    $disk = Storage::disk('local');
    foreach ($disk->files('whatsapp-vision') as $path) {
        if ($disk->lastModified($path) < now()->subHours(2)->timestamp) {
            $disk->delete($path);
        }
    }
})->hourly()->name('whatsapp-vision-cleanup')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('digital-products:migrate-images-to-r2 {--dry-run}', function (): int {
    foreach (['key', 'secret', 'bucket', 'endpoint'] as $key) {
        if (blank(config("filesystems.disks.r2.$key"))) {
            $this->error("Konfigurasi R2 $key belum tersedia.");

            return Command::FAILURE;
        }
    }

    $products = DigitalProduct::query()->where('image_is_upload', true)->where('image_disk', 'local')->get();
    $this->info("Foto lokal yang akan dimigrasikan: {$products->count()}");
    if ($this->option('dry-run')) {
        return Command::SUCCESS;
    }

    foreach ($products as $product) {
        $contents = Storage::disk('local')->get($product->image_path);
        $written = Storage::disk('r2')->put($product->image_path, $contents, ['visibility' => 'private']);
        if (! $written || ! Storage::disk('r2')->exists($product->image_path)
            || Storage::disk('r2')->size($product->image_path) !== strlen($contents)) {
            Storage::disk('r2')->delete($product->image_path);
            $this->error("Verifikasi upload gagal untuk produk {$product->id}.");

            return Command::FAILURE;
        }

        $product->update(['image_disk' => 'r2']);
        DeleteDigitalProductImage::dispatch('local', $product->image_path);
        $this->line("Produk {$product->id}: berhasil.");
    }

    $this->info('Migrasi foto Produk Digital ke R2 selesai.');

    return Command::SUCCESS;
})->purpose('Migrate verified local Digital Product images to private Cloudflare R2');

Artisan::command('service-orders:expire-payments {--limit=100}', function (ApplyServiceOrderMidtransStatus $applyStatus): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $orders = ServiceOrder::query()
        ->where('type', 'digital')
        ->where('payment_status', 'pending')
        ->whereNotNull('payment_expires_at')
        ->where('payment_expires_at', '<=', now())
        ->orderBy('id')
        ->limit($limit)
        ->get();

    foreach ($orders as $order) {
        $applyStatus->handle($order, [
            'transaction_status' => 'expire',
            'status_code' => '202',
        ]);
    }

    $this->info("Pembayaran Produk Digital kedaluwarsa: {$orders->count()}");

    return Command::SUCCESS;
})->purpose('Expire stale Digital Product payments and release reserved stock');

Schedule::command('approvals:expire')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('service-orders:expire-payments --limit=100')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('services.midtrans.enabled'))
    ->withoutOverlapping()
    ->onOneServer();
$securityAudit = Schedule::command('younz:security-audit')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/security-audit.log'));
if (filled(config('services.security.alert_email'))) {
    $securityAudit->emailOutputOnFailure((string) config('services.security.alert_email'));
}
$backup = Schedule::command('younz:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));
if (filled(config('services.security.alert_email'))) {
    $backup->emailOutputOnFailure((string) config('services.security.alert_email'));
}
Schedule::command('younz:prune-service-files')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
Schedule::command('digiflazz:sync-products --type=prepaid')
    ->cron('0,30 * * * *')
    ->when(fn (): bool => (bool) config('services.digiflazz.enabled'))
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('digiflazz:sync-products --type=postpaid')
    ->cron('10,40 * * * *')
    ->when(fn (): bool => (bool) config('services.digiflazz.enabled'))
    ->withoutOverlapping()
    ->onOneServer();
$midtransReconciliation = Schedule::command('midtrans:reconcile-payments --limit=50')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('services.midtrans.enabled') && (bool) config('services.digiflazz.enabled'))
    ->withoutOverlapping()
    ->onOneServer();
$midtransReconciliation->onFailure(function (): void {
    Log::critical('Rekonsiliasi pembayaran Midtrans terjadwal gagal.');
    $email = trim((string) config('services.security.alert_email'));

    if ($email === '' || ! Cache::add('alerts:midtrans-reconciliation', true, now()->addHour())) {
        return;
    }

    try {
        Mail::raw(
            'Rekonsiliasi pembayaran Midtrans Younz Digital Center gagal. Periksa queue, scheduler, dan storage/logs.',
            fn ($message) => $message->to($email)->subject('Peringatan Midtrans Younz Digital Center'),
        );
    } catch (Throwable $exception) {
        report($exception);
    }
});
