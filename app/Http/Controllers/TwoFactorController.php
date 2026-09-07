<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly Totp $totp,
        private readonly AuditLogger $audit,
    ) {}

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor.login_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = User::query()->find($request->session()->get('two_factor.login_id'));

        if (! $user || ! $user->is_active || ! $user->hasConfirmedTwoFactor() || ! $this->totp->verify($user->two_factor_secret, $data['code'])) {
            $this->audit->log('auth.two_factor_failed', $user, metadata: ['user_id' => $user?->id]);
            throw ValidationException::withMessages(['code' => 'Kode autentikasi tidak valid atau telah kedaluwarsa.']);
        }

        $request->session()->forget('two_factor.login_id');
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('two_factor.confirmed_user_id', $user->id);
        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.login', $user, metadata: ['two_factor' => true]);

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        if ($request->user()->hasConfirmedTwoFactor() && ! (bool) $request->session()->get('two_factor_pending_setup', false)) {
            return redirect()->route('dashboard')->with('status', 'Autentikasi dua faktor pegawai sudah aktif.');
        }

        $secret = $request->session()->get('two_factor.setup_secret');
        if (! is_string($secret)) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('two_factor.setup_secret', $secret);
        }

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => $this->totp->uri($secret, $request->user()->email),
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('two_factor.setup_secret');

        if (! is_string($secret) || ! $this->totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode belum cocok. Pastikan waktu perangkat benar lalu coba kembali.']);
        }

        $request->user()->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $request->session()->forget(['two_factor.setup_secret', 'two_factor_pending_setup']);
        $request->session()->put('two_factor.confirmed_user_id', $request->user()->id);
        $this->audit->log('auth.two_factor_enabled', $request->user());

        return redirect()->route('dashboard')->with('status', 'Autentikasi dua faktor pegawai berhasil diaktifkan.');
    }
}
