<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connectors\ConnectionFactory;
use RuntimeException;

/** Refuse unsafe named, dynamic, and read/write connections before PDO is created. */
final class InMemoryConnectionFactory extends ConnectionFactory
{
    public function make(array $config, $name = null)
    {
        self::assertSafeConfiguration($config);

        return parent::make($config, $name);
    }

    /** @param array<string, mixed> $config */
    public static function assertSafeConfiguration(array $config): void
    {
        if (($config['driver'] ?? null) !== 'sqlite'
            || ($config['database'] ?? null) !== ':memory:'
            || ! in_array($config['url'] ?? null, [null, ''], true)
            || isset($config['read']) || isset($config['write'])
            || ! empty($config['options'])
        ) {
            // Do not include the rejected configuration: it can contain credentials.
            throw new RuntimeException('TEST DATABASE SAFETY: only SQLite :memory: connections are allowed.');
        }
    }
}
