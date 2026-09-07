<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityAuditCommandTest extends TestCase
{
    public function test_security_audit_passes_when_required_controls_are_enabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://example.test',
            'app.trusted_hosts' => ['example.test'],
            'hashing.driver' => 'argon2id',
            'auth.staff_access.code_hash' => '$argon2id$configured-for-test',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'services.file_security.antivirus_required' => true,
            'services.file_security.antivirus_binary' => __FILE__,
            'services.digiflazz.enabled' => false,
            'services.midtrans.enabled' => false,
            'services.google_drive_backup.enabled' => false,
        ]);

        $this->artisan('younz:security-audit')->assertSuccessful();
    }

    public function test_security_audit_fails_open_debug_mode(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.url' => 'https://example.test',
            'app.trusted_hosts' => ['example.test'],
            'hashing.driver' => 'argon2id',
            'auth.staff_access.code_hash' => '$argon2id$configured-for-test',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'services.file_security.antivirus_required' => true,
            'services.file_security.antivirus_binary' => __FILE__,
            'services.digiflazz.enabled' => false,
            'services.midtrans.enabled' => false,
            'services.google_drive_backup.enabled' => false,
        ]);

        $this->artisan('younz:security-audit')->assertFailed();
    }

    public function test_security_audit_fails_when_google_drive_backup_credentials_are_incomplete(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://example.test',
            'app.trusted_hosts' => ['example.test'],
            'hashing.driver' => 'argon2id',
            'auth.staff_access.code_hash' => '$argon2id$configured-for-test',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'services.file_security.antivirus_required' => true,
            'services.file_security.antivirus_binary' => __FILE__,
            'services.digiflazz.enabled' => false,
            'services.midtrans.enabled' => false,
            'services.google_drive_backup.enabled' => true,
            'services.google_drive_backup.client_id' => 'client-id',
            'services.google_drive_backup.client_secret' => null,
            'services.google_drive_backup.refresh_token' => null,
        ]);

        $this->artisan('younz:security-audit')->assertFailed();
    }
}
