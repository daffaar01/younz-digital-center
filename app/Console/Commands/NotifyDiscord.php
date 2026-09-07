<?php

namespace App\Console\Commands;

use App\Integrations\Discord\DiscordNotifier;
use Illuminate\Console\Command;

class NotifyDiscord extends Command
{
    protected $signature = 'discord:notify
        {--event=system.alert : Jenis event notifikasi}
        {--title= : Judul notifikasi}
        {--message= : Isi pesan}
        {--mention : Sebut role staff}
        {--test : Kirim notifikasi contoh untuk memastikan integrasi berjalan}';

    protected $description = 'Mengirim notifikasi ke bot Discord Younz Digital Center.';

    public function handle(DiscordNotifier $notifier): int
    {
        if (! $notifier->isEnabled()) {
            $this->components->error('Integrasi Discord belum aktif.');
            $this->line('Isi DISCORD_NOTIFY_ENABLED, DISCORD_BOT_WEBHOOK_URL, dan DISCORD_BOT_WEBHOOK_TOKEN pada .env.');

            return self::FAILURE;
        }

        if (! $notifier->isHealthy()) {
            $this->components->warn('Bot Discord tidak merespons health check. Notifikasi tetap dicoba.');
        }

        if ($this->option('test')) {
            $sent = $notifier->notify(
                event: 'system.alert',
                title: 'Uji Integrasi Discord',
                message: 'Notifikasi contoh dari Laravel. Bila pesan ini tampil, integrasi berjalan normal.',
                fields: [
                    ['name' => 'Sumber', 'value' => 'Artisan discord:notify --test', 'inline' => true],
                    ['name' => 'Lingkungan', 'value' => app()->environment(), 'inline' => true],
                ],
                mentionStaff: (bool) $this->option('mention'),
            );

            return $this->report($sent);
        }

        $title = trim((string) $this->option('title'));

        if ($title === '') {
            $this->components->error('Opsi --title wajib diisi, atau gunakan --test.');

            return self::FAILURE;
        }

        $sent = $notifier->notify(
            event: (string) $this->option('event'),
            title: $title,
            message: (string) $this->option('message'),
            mentionStaff: (bool) $this->option('mention'),
        );

        return $this->report($sent);
    }

    private function report(bool $sent): int
    {
        if ($sent) {
            $this->components->info('Notifikasi terkirim ke Discord.');

            return self::SUCCESS;
        }

        $this->components->error('Notifikasi gagal dikirim. Periksa storage/logs/laravel.log.');

        return self::FAILURE;
    }
}
