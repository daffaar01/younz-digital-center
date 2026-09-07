<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $staffHost = (string) config('app.staff_host');

        if ($staffHost === '' || hash_equals($staffHost, $request->getHost())) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            abort(404);
        }

        $staffUrl = rtrim((string) config('app.staff_url'), '/');

        return redirect()->away($staffUrl.$request->getRequestUri());
    }
}
