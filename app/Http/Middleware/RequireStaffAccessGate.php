<?php

namespace App\Http\Middleware;

use App\Support\StaffAccessGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffAccessGate
{
    public function __construct(private readonly StaffAccessGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isStaffHost($request) || ! $this->gate->isConfigured()) {
            return $next($request);
        }

        if ($request->routeIs('staff.access.*')) {
            return $next($request);
        }

        if ($request->user()?->isStaff()) {
            return $request->routeIs('home')
                ? redirect()->route('dashboard')
                : $next($request);
        }

        if (! $this->gate->hasValidSession($request)) {
            return redirect()->guest(route('staff.access.show'));
        }

        if ($request->routeIs('home')) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    private function isStaffHost(Request $request): bool
    {
        return strcasecmp($request->getHost(), (string) config('app.staff_host')) === 0;
    }
}
