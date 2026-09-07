<?php

namespace Tests\Feature;

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\InMemoryConnectionFactory;
use Tests\Support\TestEnvironment;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_default_connection_and_runtime_are_isolated(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('', DB::selectOne('PRAGMA database_list')->file);
        $this->assertInstanceOf(InMemoryConnectionFactory::class, app('db.factory'));
        $this->assertSame(realpath(base_path('tests/.env.testing')), realpath($this->app->environmentFilePath()));
        $this->assertNotSame(base_path('storage'), storage_path());
        $this->assertNotSame(base_path('bootstrap/cache/config.php'), $this->app->getCachedConfigPath());
        $this->assertFalse($this->app->configurationIsCached());
    }

    public function test_refresh_migration_is_only_run_after_verifying_the_actual_memory_database(): void
    {
        $this->assertInstanceOf(InMemoryConnectionFactory::class, app('db.factory'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame('', DB::selectOne('PRAGMA database_list')->file);

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('topup_orders', 0);
    }

    public function test_live_named_connections_are_not_available(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DB::connection('legacy_sampitmart');
    }

    public function test_a_late_added_server_connection_is_blocked(): void
    {
        config()->set('database.connections.unsafe_test', [
            'driver' => 'mariadb', 'database' => 'production', 'host' => 'never-connect.invalid',
        ]);
        $this->expectExceptionMessage('TEST DATABASE SAFETY');
        DB::connection('unsafe_test');
    }

    public function test_a_dynamic_connection_cannot_bypass_the_guard(): void
    {
        $this->expectExceptionMessage('TEST DATABASE SAFETY');
        DB::build(['driver' => 'mysql', 'database' => 'production', 'host' => 'never-connect.invalid']);
    }

    public function test_a_changed_default_connection_is_blocked_on_reconnect(): void
    {
        config()->set('database.connections.sqlite.database', storage_path('must-not-exist.sqlite'));
        DB::purge('sqlite');
        $this->expectExceptionMessage('TEST DATABASE SAFETY');
        DB::connection();
    }

    public function test_an_additional_in_memory_fixture_database_is_allowed(): void
    {
        config()->set('database.connections.legacy_test', ['driver' => 'sqlite', 'database' => ':memory:']);
        $connection = DB::connection('legacy_test');
        $this->assertSame('', $connection->selectOne('PRAGMA database_list')->file);
        $this->assertNotSame(DB::connection()->getPdo(), $connection->getPdo());
    }

    public function test_unsafe_application_configuration_is_rejected(): void
    {
        config()->set('database.default', 'mariadb');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST DATABASE SAFETY');
        TestEnvironment::assertSafeApplication($this->app);
    }

    public function test_unmocked_http_requests_are_blocked(): void
    {
        $this->expectException(StrayRequestException::class);
        Http::get('https://never-request.invalid');
    }

    public function test_inherited_production_settings_and_cache_are_ignored(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/isolation-probe.php')], base_path(), [
            'APP_ENV' => 'production',
            'APP_CONFIG_CACHE' => base_path('tests/Fixtures/unsafe-cached-config.php'),
            'DB_CONNECTION' => 'mariadb',
            'DB_DATABASE' => 'production',
            'DB_URL' => 'mysql://user:private-value@never-connect.invalid/production',
            'SQLITE_DATABASE' => base_path('database/database.sqlite'),
            'CACHE_STORE' => 'database',
        ]);
        $process->setTimeout(30)->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('testing', $data['environment']);
        $this->assertSame(':memory:', $data['database']);
        $this->assertSame('', $data['sqlite_file']);
        $this->assertTrue($data['guarded_factory']);
        $this->assertNotSame($this->app->getCachedConfigPath(), $data['config_cache']);
    }

    public function test_a_cache_reintroduced_after_bootstrap_is_rejected_before_execution(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/isolation-probe.php'), '--poison-cache'], base_path());
        $process->setTimeout(30)->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('TEST DATABASE SAFETY: cached application configuration', $process->getErrorOutput());
        $this->assertStringNotContainsString('UNSAFE CACHE WAS EXECUTED', $process->getErrorOutput());
    }
}
