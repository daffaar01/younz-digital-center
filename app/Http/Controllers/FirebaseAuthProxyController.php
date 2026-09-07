<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuthProxyController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        abort_unless(config('services.firebase.enabled'), 404);
        abort_unless($this->isSafeHelperPath($path), 404);

        $projectId = (string) config('services.firebase.project_id');
        $authDomain = (string) config('services.firebase.web.authDomain');
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{4,29}$/', $projectId) === 1, 503);
        abort_unless(filter_var($authDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME), 503);

        $firebaseOrigin = "https://{$projectId}.firebaseapp.com";
        $target = $firebaseOrigin.'/__/auth/'.$path;
        if (filled($request->getQueryString())) {
            $target .= '?'.$request->getQueryString();
        }

        $headers = array_filter([
            'Accept' => $request->header('Accept'),
            'Accept-Language' => $request->header('Accept-Language'),
            'Content-Type' => $request->header('Content-Type'),
            'Origin' => $request->header('Origin'),
            'User-Agent' => $request->userAgent(),
        ], fn (?string $value): bool => filled($value));

        $body = $request->getContent();
        if ($request->isMethod('POST') && blank($body) && $request->request->count() > 0) {
            $body = http_build_query($request->request->all());
        }

        try {
            $upstream = Http::withHeaders($headers)
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->send($request->method(), $target, [
                    'body' => $body,
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            abort(502, 'Layanan login Google sementara tidak dapat dijangkau.');
        }

        $response = response($upstream->body(), $upstream->status());
        foreach (['Content-Type', 'Cache-Control', 'ETag', 'Last-Modified', 'Vary'] as $header) {
            if (filled($upstream->header($header))) {
                $response->headers->set($header, $upstream->header($header));
            }
        }

        // Firebase requires this endpoint to behave as a transparent reverse proxy.
        // Prevent Cloudflare Web Analytics and other intermediaries from injecting
        // scripts into the authentication helper documents.
        $cacheControl = trim((string) $response->headers->get('Cache-Control'));
        if (! str_contains(strtolower($cacheControl), 'no-transform')) {
            $response->headers->set(
                'Cache-Control',
                $cacheControl === '' ? 'no-transform' : $cacheControl.', no-transform',
            );
        }

        if (filled($location = $upstream->header('Location'))) {
            $response->headers->set(
                'Location',
                str_replace($firebaseOrigin, 'https://'.$authDomain, $location),
            );
        }

        return $response;
    }

    private function isSafeHelperPath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '..')
            && preg_match('#^[a-zA-Z0-9._/-]+$#', $path) === 1;
    }
}
