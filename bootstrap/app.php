<?php

use App\Http\Middleware\EnforceBackendOnly;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureStaffTwoFactor;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireStaffAccessGate;
use App\Http\Middleware\RequireStaffHost;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: fn (): array => array_map(
                fn (string $host): string => '^'.preg_quote($host, '/').'$',
                (array) config('app.trusted_hosts', []),
            ),
            subdomains: false,
        );
        // cloudflared connects to the local origin directly; trust only that immediate proxy.
        $middleware->trustProxies(at: 'REMOTE_ADDR');
        // This preference is written by browser JavaScript and must remain readable
        // before Blade renders the next response.
        $middleware->encryptCookies(except: ['ydc_cookie_consent']);
        $middleware->validateCsrfTokens(except: [
            '__/auth/*',
            'webhooks/midtrans',
            'webhooks/digiflazz',
            'webhooks/whatsapp/messages',
        ]);
        $middleware->web(append: [RequireStaffAccessGate::class]);
        $middleware->append(EnforceBackendOnly::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('akun', 'akun/*')
            ? route('customer.login')
            : rtrim((string) config('app.staff_url'), '/').'/login');
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isStaff()
            ? route('dashboard')
            : route('customer.dashboard'));
        $middleware->alias([
            'role' => RequireRole::class,
            'active' => EnsureActiveUser::class,
            'staff.2fa' => EnsureStaffTwoFactor::class,
            'staff.host' => RequireStaffHost::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['access_code']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
