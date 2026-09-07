<?php

namespace App\Providers;

use App\Actions\Approvals\ApprovalEngine;
use App\Actions\Approvals\DeferredActionApprovalHandler;
use App\Actions\Approvals\SaleRefundApprovalHandler;
use App\Actions\Inventory\AdjustStock;
use App\Contracts\FirebaseTokenVerifier;
use App\Enums\ApprovalType;
use App\Events\ServiceOrderStatusChanged;
use App\Listeners\QueueServiceOrderDiscordNotification;
use App\Listeners\QueueServiceOrderWhatsAppNotification;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use App\Support\GoogleFirebaseTokenVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseTokenVerifier::class, GoogleFirebaseTokenVerifier::class);

        $this->app->singleton(ApprovalEngine::class, function (Application $app): ApprovalEngine {
            $deferred = fn (ApprovalType $type) => new DeferredActionApprovalHandler(
                $type,
                $app->make(AdjustStock::class),
                $app->make(DocumentNumberGenerator::class),
                $app->make(AuditLogger::class),
            );

            return new ApprovalEngine(
                handlers: [
                    $app->make(SaleRefundApprovalHandler::class),
                    $deferred(ApprovalType::ProductPriceChange),
                    $deferred(ApprovalType::StockAdjustment),
                    $deferred(ApprovalType::EmployeeAccessChange),
                    $deferred(ApprovalType::DigitalTransaction),
                    $deferred(ApprovalType::FinancialExpense),
                ],
                audit: $app->make(AuditLogger::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ServiceOrderStatusChanged::class, QueueServiceOrderWhatsAppNotification::class);
        Event::listen(ServiceOrderStatusChanged::class, QueueServiceOrderDiscordNotification::class);

        RateLimiter::for('staff-login', fn (Request $request): array => $this->loginLimits($request, 'staff'));
        RateLimiter::for('customer-login', fn (Request $request): array => $this->loginLimits($request, 'customer'));
        RateLimiter::for('api-login', fn (Request $request): array => $this->loginLimits($request, 'api'));

        RateLimiter::for('staff-access', fn (Request $request): array => [
            Limit::perMinute(5)->by('staff-access:minute:'.$request->ip()),
            Limit::perHour(20)->by('staff-access:hour:'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(3)->by('registration:minute:'.$request->ip()),
            Limit::perHour(10)->by('registration:hour:'.$request->ip()),
        ]);

        RateLimiter::for('verification-resend', function (Request $request): array {
            $identity = $this->identityHash($request);

            return [
                Limit::perMinutes(10, 3)->by('verification-resend:identity:'.$identity),
                Limit::perHour(20)->by('verification-resend:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('firebase-login', fn (Request $request): array => [
            Limit::perMinute(10)->by('firebase-login:minute:'.$request->ip()),
            Limit::perHour(60)->by('firebase-login:hour:'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', function (Request $request): array {
            $identity = $this->identityHash($request);

            return [
                Limit::perMinutes(10, 3)->by('password-reset:identity:'.$identity),
                Limit::perHour(20)->by('password-reset:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request): array {
            $identity = (string) ($request->session()->get('two_factor.login_id') ?: $request->ip());

            return [
                Limit::perMinutes(5, 3)->by('two-factor:identity:'.$identity),
                Limit::perHour(12)->by('two-factor:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('staff-two-factor-api', fn (Request $request): array => [
            Limit::perMinutes(5, 5)->by('staff-two-factor-api:token:'.hash('sha256', (string) $request->input('setup_token'))),
            Limit::perHour(20)->by('staff-two-factor-api:ip:'.$request->ip()),
        ]);

        RateLimiter::for('topup-checkout', fn (Request $request): array => [
            Limit::perMinute(5)->by('topup:minute:'.($request->user()?->id ?: $request->ip())),
            Limit::perHour(30)->by('topup:hour:'.($request->user()?->id ?: $request->ip())),
        ]);
        RateLimiter::for('topup-access', fn (Request $request): array => [
            Limit::perMinute(5)->by('topup-access:minute:'.$request->ip()),
            Limit::perHour(20)->by('topup-access:hour:'.$request->ip()),
        ]);

        RateLimiter::for('firebase-helper', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('firebase-helper:'.$request->ip()));
        RateLimiter::for('webhook', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('webhook:'.$request->ip()));
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('api:'.($request->user()?->id ?: $request->ip())));
    }

    /** @return list<Limit> */
    private function loginLimits(Request $request, string $scope): array
    {
        $identity = $this->identityHash($request);

        return [
            Limit::perMinute(5)->by("{$scope}-login:identity-ip:{$identity}|{$request->ip()}"),
            Limit::perMinutes(10, 20)->by("{$scope}-login:identity:{$identity}"),
            Limit::perHour(30)->by("{$scope}-login:ip:{$request->ip()}"),
        ];
    }

    private function identityHash(Request $request): string
    {
        $email = Str::lower(trim((string) $request->input('email', '')));

        return hash('sha256', $email !== '' ? $email : 'missing');
    }
}
