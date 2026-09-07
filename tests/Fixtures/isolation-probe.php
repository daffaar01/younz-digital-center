<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\InMemoryConnectionFactory;
use Tests\Support\TestEnvironment;

// Read-only bootstrap probe: never runs migrations or uses the main database.
require dirname(__DIR__).'/bootstrap.php';

if (($argv[1] ?? '') === '--poison-cache') {
    $path = __DIR__.'/unsafe-cached-config.php';
    putenv('APP_CONFIG_CACHE='.$path);
    $_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $path;
}

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    TestEnvironment::configureApplication($app);
    $app->make(Kernel::class)->bootstrap();
    TestEnvironment::assertSafeApplication($app);
    if (! $app->make('db.factory') instanceof InMemoryConnectionFactory) {
        throw new RuntimeException('TEST DATABASE SAFETY: connection factory guard is missing.');
    }
    $database = $app->make('db')->connection();
    echo json_encode([
        'environment' => $app->environment(),
        'driver' => $database->getDriverName(),
        'database' => $database->getDatabaseName(),
        'sqlite_file' => $database->selectOne('PRAGMA database_list')->file,
        'environment_file' => $app->environmentFilePath(),
        'config_cache' => $app->getCachedConfigPath(),
        'storage' => $app->storagePath(),
        'guarded_factory' => true,
    ], JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    fwrite(STDERR, str_starts_with($message, 'TEST DATABASE SAFETY:') ? $message.PHP_EOL
        : 'Isolation probe failed: '.get_class($exception).' at '.basename($exception->getFile()).':'.$exception->getLine().PHP_EOL);
    exit(1);
}
