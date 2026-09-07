<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyMariaDbBackup;
use App\Support\MariaDbBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MariaDbBackupTest extends TestCase
{
    private function sql(): string
    {
        return "CREATE TABLE `users` (`id` int);\nINSERT INTO `users` (`id`) VALUES (1);\nCREATE TABLE `topup_orders` (`id` int);\n";
    }

    public function test_compressed_manifest_round_trips_and_counts_empty_tables(): void
    {
        $payload = MariaDbBackup::pack($this->sql(), 'test_source');
        $backup = MariaDbBackup::unpack($payload);
        $this->assertSame($this->sql(), $backup['sql']);
        $this->assertSame(['topup_orders' => 0, 'users' => 1], $backup['tables']);
        $this->assertSame('test_source', $backup['database']);
    }

    public function test_corrupted_snapshot_is_rejected(): void
    {
        $payload = json_decode(MariaDbBackup::pack($this->sql(), 'test_source'), true);
        $payload['sha256'] = str_repeat('0', 64);
        $this->expectExceptionMessage('Invalid MariaDB backup payload');
        MariaDbBackup::unpack(json_encode($payload));
    }

    public function test_empty_or_unknown_table_dump_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        MariaDbBackup::pack("CREATE TABLE `users` (`id` int);\nINSERT INTO `other` (`id`) VALUES (1);\n", 'test_source');
    }

    public function test_restore_target_never_accepts_source_or_existing_database_names(): void
    {
        foreach (['younz_digital_center', 'mysql', 'younzrestorecheck', 'test;DROP DATABASE mysql', 'younzrestorecheck20260904120000aabbccddee'] as $target) {
            try {
                VerifyMariaDbBackup::assertTarget($target, $target === 'younzrestorecheck20260904120000aabbccddee' ? $target : 'younz_digital_center');
                $this->fail('Unsafe target accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Unsafe restore database target.', $exception->getMessage());
            }
        }
        VerifyMariaDbBackup::assertTarget('younzrestorecheck20260904120000aabbccddee', 'younz_digital_center');
    }

    public function test_options_quote_credentials_without_adding_option_lines(): void
    {
        $options = MariaDbBackup::optionFile(['username' => 'fixture', 'password' => "quote\"\\\n[client]\nhost=bad"]);
        $this->assertStringContainsString('password="quote\\"\\\\\\n[client]\\nhost=bad"', $options);
        $this->assertSame(1, preg_match_all('/^host=/m', $options));
        $this->assertSame(1, preg_match_all('/^\[client\]$/m', $options));
    }

    public function test_native_dump_cannot_bypass_the_test_database_guard(): void
    {
        $this->expectExceptionMessage('disabled in PHPUnit');
        app(MariaDbBackup::class)->snapshot(DB::connection());
    }

    public function test_native_restore_cannot_bypass_the_test_database_guard(): void
    {
        $this->expectExceptionMessage('disabled in PHPUnit');
        app(MariaDbBackup::class)->restore([], 'fixture', 'SELECT 1;');
    }

    public function test_native_restore_stays_blocked_if_a_test_changes_its_environment_label(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->expectExceptionMessage('disabled in PHPUnit');
        app(MariaDbBackup::class)->restore([], 'fixture', 'SELECT 1;');
    }

    public function test_backup_schedule_is_daily_and_protected_from_overlap(): void
    {
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => str_contains((string) $event->command, 'younz:backup')));
        $this->assertCount(1, $events);
        $this->assertSame('0 2 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertTrue($events[0]->onOneServer);
    }

    public function test_restore_command_is_disabled_in_tests_before_any_database_access(): void
    {
        DB::shouldReceive('connection')->never();
        $this->artisan('younz:backup-verify', ['backup' => 'backups/mariadb/younz-20260904-120000-aaaaaaaa.mariadb.enc'])->assertFailed();
    }

    public function test_command_encrypts_verifies_and_rotates_only_managed_backups(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $legacy = 'backups/mariadb/before-recovery.sql';
        $expired = 'backups/mariadb/younz-20200101-020000-aaaaaaaa.mariadb.enc';
        foreach ([$legacy, $expired] as $path) {
            $disk->put($path, 'fixture');
            touch($disk->path($path), now()->subDays(45)->getTimestamp());
        }
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mariadb');
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $tools = Mockery::mock(MariaDbBackup::class);
        $tools->shouldReceive('snapshot')->once()->with($connection)->andReturn(MariaDbBackup::pack($this->sql(), 'test_source'));
        $this->app->instance(MariaDbBackup::class, $tools);

        $this->artisan('younz:backup')->assertSuccessful();
        $disk->assertExists($legacy);
        $disk->assertMissing($expired);
        $files = array_values(array_filter($disk->files('backups/mariadb'), fn ($path) => str_ends_with($path, '.mariadb.enc')));
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('CREATE TABLE', $disk->get($files[0]));
        $restored = MariaDbBackup::unpack(Crypt::decryptString($disk->get($files[0])));
        $this->assertSame($this->sql(), $restored['sql']);
    }
}
