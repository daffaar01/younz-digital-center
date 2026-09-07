<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStaff()) {
            if (! $user->hasConfirmedTwoFactor() && (bool) $request->session()->get('two_factor_pending_setup', false)) {
                if (! $request->routeIs('two-factor.setup.*', 'logout')) {
                    return redirect()->route('two-factor.setup.show');
                }
            }

            if ($user->hasConfirmedTwoFactor()
                && (Auth::viaRemember() || (int) $request->session()->get('two_factor.confirmed_user_id') !== $user->id)
            ) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if (! $request->routeIs('logout')) {
                    return redirect()->route('login')
                        ->withErrors(['email' => 'Sesi pegawai berakhir. Masuk kembali dan verifikasi kode dua faktor.']);
                }
            }

            if ((bool) $request->session()->get('two_factor_pending_setup', false) && ! $request->routeIs('two-factor.setup.*', 'logout')) {
                return redirect()->route('two-factor.setup.show');
            }
        }

        return $next($request);
    }
}
