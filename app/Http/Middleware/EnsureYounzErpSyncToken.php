<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureYounzErpSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.younz_erp.sync_token'));
        $provided = trim((string) $request->header('X-Younz-ERP-Sync'));

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Akses sinkronisasi tidak valid.'], 401);
        }

        return $next($request);
    }
}
