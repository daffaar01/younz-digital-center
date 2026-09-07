<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityAudit extends Command
{
    protected $signature = 'younz:security-audit';

    protected $description = 'Validate production security invariants without exposing secrets';

    public function handle(): int
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $trustedHosts = (array) config('app.trusted_hosts', []);
        $antivirusBinary = (string) config('services.file_security.antivirus_binary');

        $checks = [
            ['Production mode', app()->isProduction()],
            ['Debug disabled', ! (bool) config('app.debug')],
            ['Canonical HTTPS URL', parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'],
            ['Canonical host allowlisted', is_string($appHost) && in_array($appHost, $trustedHosts, true)],
            ['Argon2id password hashing', config('hashing.driver') === 'argon2id'],
            ['Staff access gate configured', str_starts_with((string) config('auth.staff_access.code_hash'), '$argon2id$')],
            ['Encrypted sessions', (bool) config('session.encrypt')],
            ['Secure session cookie', (bool) config('session.secure')],
            ['HttpOnly session cookie', (bool) config('session.http_only')],
            ['Safe SameSite cookie', in_array(config('session.same_site'), ['lax', 'strict'], true)],
            ['Persistent queue', config('queue.default') !== 'sync'],
            ['Antivirus fail-closed', (bool) config('services.file_security.antivirus_required')],
            ['Antivirus binary available', $antivirusBinary !== '' && File::isFile($antivirusBinary)],
            ['Digiflazz credentials and webhook secret', ! config('services.digiflazz.enabled') || (
                filled(config('services.digiflazz.username'))
                && filled(config('services.digiflazz.api_key'))
                && filled(config('services.digiflazz.webhook_secret'))
            )],
            ['Midtrans server key', ! config('services.midtrans.enabled') || filled(config('services.midtrans.server_key'))],
            ['Live providers not in testing mode', ! app()->isProduction() || (
                ! config('services.digiflazz.enabled')
                || ! config('services.digiflazz.testing')
            )],
            ['Midtrans production mode', ! app()->isProduction() || ! config('services.midtrans.enabled') || config('services.midtrans.production')],
            ['Google Drive backup credentials', ! config('services.google_drive_backup.enabled') || (
                filled(config('services.google_drive_backup.client_id'))
                && filled(config('services.google_drive_backup.client_secret'))
                && filled(config('services.google_drive_backup.refresh_token'))
            )],
        ];

        $failed = 0;
        foreach ($checks as [$label, $passed]) {
            if ($passed) {
                $this->components->info($label);
            } else {
                $this->components->error($label);
                $failed++;
            }
        }

        if (config('database.default') === 'sqlite') {
            $this->components->warn('SQLite dipakai di produksi; pastikan ACL folder database tetap terbatas dan backup diuji.');
        }
        if (config('filesystems.backup') === 'local'
            && ! config('services.backup_r2.enabled')
            && ! config('services.google_drive_backup.enabled')
        ) {
            $this->components->warn('Backup masih berada di disk lokal; salin terenkripsi ke lokasi off-site.');
        }

        if ($failed > 0) {
            $this->error("Audit keamanan gagal pada {$failed} kontrol.");

            return self::FAILURE;
        }

        $this->info('Semua kontrol keamanan wajib aktif.');

        return self::SUCCESS;
    }
}
