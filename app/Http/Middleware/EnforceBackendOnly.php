<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceBackendOnly
{
    /**
     * Prevent the Laravel runtime from becoming a browser frontend when the
     * production tunnel or reverse proxy is accidentally pointed at port 8080.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.backend_only') || $this->isBackendEndpoint($request)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'Frontend dilayani oleh Next.js.',
            'service' => 'younz-laravel-backend',
        ], Response::HTTP_NOT_FOUND, [
            'Cache-Control' => 'no-store',
            'X-Younz-Frontend' => 'nextjs',
        ]);
    }

    private function isBackendEndpoint(Request $request): bool
    {
        return $request->is(
            'api',
            'api/*',
            'up',
            'webhooks/*',
            '__/auth/*',
            'service-worker.js',
            'sitemap.xml',
            'topup/status/*',
            'email/verify/*',
        );
    }
}
