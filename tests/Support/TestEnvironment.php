<?php

declare(strict_types=1);

namespace Tests\Support;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TestEnvironment
{
    private static ?string $runtime = null;

    /** Run before Laravel can load the main .env or cached configuration. */
    public static function prepare(): void
    {
        $settings = Dotenv::parse((string) file_get_contents(dirname(__DIR__).'/.env.testing'));
        foreach ($settings as $name => $value) {
            self::setEnvironment($name, (string) $value);
        }

        if (self::$runtime === null) {
            $directory = rtrim(sys_get_temp_dir(), '/\\').'/younz-tests-'.getmypid().'-'.bin2hex(random_bytes(8));
            if (! mkdir($directory, 0700)) {
                throw new RuntimeException('TEST DATABASE SAFETY: cannot create isolated runtime.');
            }
            self::$runtime = realpath($directory) ?: $directory;
            foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
                'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
                if (! mkdir(self::$runtime.'/'.$path, 0700, true) && ! is_dir(self::$runtime.'/'.$path)) {
                    throw new RuntimeException('TEST DATABASE SAFETY: cannot create isolated storage.');
                }
            }
        }

        foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php',
            'APP_EVENTS_CACHE' => 'events.php', 'APP_SERVICES_CACHE' => 'services.php',
            'APP_PACKAGES_CACHE' => 'packages.php'] as $name => $file) {
            self::setEnvironment($name, self::$runtime.'/bootstrap/cache/'.$file);
        }
        self::setEnvironment('VIEW_COMPILED_PATH', self::$runtime.'/storage/framework/views');
        self::setEnvironment('LARAVEL_STORAGE_PATH', self::$runtime.'/storage');
    }

    public static function configureApplication(Application $app): void
    {
        if (self::$runtime === null) {
            throw new RuntimeException('TEST DATABASE SAFETY: test bootstrap was not initialized.');
        }
        // Explicit path prevents Laravel from falling back to the live .env file.
        $app->useEnvironmentPath(dirname(__DIR__));
        $app->loadEnvironmentFrom('.env.testing');
        $app->useStoragePath(self::$runtime.'/storage');
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (range('A', 'Z') as $drive) {
                $app->addAbsoluteCachePathPrefix($drive.':\\');
                $app->addAbsoluteCachePathPrefix($drive.':/');
            }
        }

        $app->beforeBootstrapping(LoadConfiguration::class, static function (Application $app): void {
            if ($app->configurationIsCached()) {
                throw new RuntimeException('TEST DATABASE SAFETY: cached application configuration is not allowed.');
            }
        });
        $app->afterBootstrapping(LoadConfiguration::class, static function (Application $app): void {
            self::assertSafeApplication($app);
            $config = $app->make('config');
            // Legacy/provider databases must never be available through a named connection.
            $config->set('database.connections', ['sqlite' => $config->get('database.connections.sqlite')]);
        });
        // Container extenders also apply when the framework registers this binding later.
        $app->extend('db.factory', static fn ($factory, $container) => new InMemoryConnectionFactory($container));
        $app->afterBootstrapping(BootProviders::class, static function (): void {
            Http::preventStrayRequests();
        });
    }

    public static function assertSafeApplication(Application $app): void
    {
        $config = $app->make('config');
        if (! $app->environment('testing') || $config->get('database.default') !== 'sqlite'
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array'
            || $config->get('queue.default') !== 'sync' || $config->get('mail.default') !== 'array'
        ) {
            throw new RuntimeException('TEST DATABASE SAFETY: unsafe test environment; refusing to bootstrap.');
        }
        InMemoryConnectionFactory::assertSafeConfiguration((array) $config->get('database.connections.sqlite'));
    }

    private static function setEnvironment(string $name, string $value): void
    {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
