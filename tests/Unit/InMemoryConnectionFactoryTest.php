<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\InMemoryConnectionFactory;

class InMemoryConnectionFactoryTest extends TestCase
{
    public function test_it_rejects_unsafe_configurations_before_creating_any_connection(): void
    {
        $factory = new InMemoryConnectionFactory(new Container);
        $safe = ['driver' => 'sqlite', 'database' => ':memory:'];
        $unsafe = [
            [],
            ['driver' => 'mysql', 'database' => 'production'],
            ['driver' => 'mariadb', 'database' => 'production'],
            ['driver' => 'pgsql', 'database' => 'production'],
            ['driver' => 'sqlsrv', 'database' => 'production'],
            ['driver' => 'sqlite', 'database' => 'database/database.sqlite'],
            ['driver' => 'sqlite', 'database' => 'file:production.sqlite?mode=rw'],
            $safe + ['url' => 'mysql://user:private-value@localhost/production'],
            $safe + ['read' => ['database' => 'production.sqlite']],
            $safe + ['write' => ['driver' => 'mysql']],
            $safe + ['options' => [1 => 'unexpected']],
        ];
        foreach ($unsafe as $config) {
            try {
                $factory->make($config);
                $this->fail('Unsafe connection was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('TEST DATABASE SAFETY', $exception->getMessage());
                $this->assertStringNotContainsString('private-value', $exception->getMessage());
            }
        }
    }

    public function test_it_allows_only_an_ephemeral_sqlite_database(): void
    {
        $factory = new InMemoryConnectionFactory(new Container);
        $connection = $factory->make(['driver' => 'sqlite', 'database' => ':memory:']);

        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $this->assertSame('', $connection->selectOne('PRAGMA database_list')->file);
    }
}
