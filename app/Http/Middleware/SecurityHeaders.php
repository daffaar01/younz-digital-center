<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isFirebaseAuthHelper = $request->is('__/auth/*');

        // These files are reverse-proxied Firebase assets. Adding the application's
        // CSP, frame or cross-origin policies changes their execution environment
        // and can make the Google popup report auth/popup-closed-by-user.
        if ($isFirebaseAuthHelper) {
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }

            return $response;
        }

        $firebaseEnabled = (bool) config('services.firebase.enabled');
        $imageSources = ["'self'", 'data:', 'blob:'];
        $scriptSources = ["'self'", "'wasm-unsafe-eval'", 'https://static.cloudflareinsights.com'];
        $connectSources = ["'self'", 'https://cloudflareinsights.com'];
        $frameSources = ["'self'", 'https://www.google.com'];
        $formActionSources = [
            "'self'",
            (bool) config('services.midtrans.production')
                ? 'https://app.midtrans.com'
                : 'https://app.sandbox.midtrans.com',
        ];

        if ($firebaseEnabled) {
            $imageSources[] = 'https://lh3.googleusercontent.com';
            $connectSources[] = 'https://identitytoolkit.googleapis.com';
            $connectSources[] = 'https://securetoken.googleapis.com';
            $connectSources[] = 'https://www.googleapis.com';

            foreach (['https://apis.google.com', 'https://www.google.com', 'https://www.gstatic.com'] as $source) {
                $imageSources[] = $source;
                $scriptSources[] = $source;
                $connectSources[] = $source;
            }

            $authDomain = parse_url('https://'.config('services.firebase.web.authDomain'), PHP_URL_HOST);
            if (is_string($authDomain) && preg_match('/^[a-z0-9.-]+$/i', $authDomain)) {
                $frameSources[] = 'https://'.$authDomain;
            }
        }

        $contentSecurityPolicy = [
            "default-src 'self'",
            "base-uri 'self'",
            'form-action '.implode(' ', $formActionSources),
            "frame-ancestors 'none'",
            "object-src 'none'",
            'img-src '.implode(' ', $imageSources),
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            'script-src '.implode(' ', $scriptSources),
            'connect-src '.implode(' ', $connectSources),
            'frame-src '.implode(' ', $frameSources),
        ];
        if ($request->isSecure()) {
            $contentSecurityPolicy[] = 'upgrade-insecure-requests';
        }
        $contentSecurityPolicy = implode('; ', $contentSecurityPolicy);
        $headers = [
            'Content-Security-Policy' => $contentSecurityPolicy,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => $firebaseEnabled ? 'same-origin-allow-popups' : 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        if ($request->isMethod('post')
            || $request->user()
            || $request->is('login', 'akses-pegawai', 'akun', 'akun/*', 'two-factor-challenge', 'cek-pesanan/*', 'pesanan-saya', 'topup/akses', 'topup/status/*')
        ) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
