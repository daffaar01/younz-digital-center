<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\StaffAccessGate;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Totp $totp,
        private readonly StaffAccessGate $staffAccessGate,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim($request->string('email')->toString())),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, false)) {
            $this->audit->log('auth.login_failed', metadata: [
                'email_hash' => $this->audit->identifierFingerprint($credentials['email']),
            ]);
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }

        if (! $request->user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun sedang dinonaktifkan.']);
        }

        $user = $request->user();
        $request->session()->regenerate();

        if ($user->isStaff() && $user->hasConfirmedTwoFactor()) {
            $request->session()->put('two_factor.login_id', $user->id);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.login', $user, metadata: ['two_factor' => false]);

        if ($user->isStaff()) {
            $request->session()->put('two_factor_pending_setup', true);

            return redirect()->route('two-factor.setup.show');
        }

        if ($request->boolean('remember')) {
            Auth::login($user, true);
        }

        if ($user->role === UserRole::Customer) {
            return redirect()->route('customer.dashboard');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function staffAuthConfig(): JsonResponse
    {
        return response()->json(['data' => [
            'access_code_required' => $this->staffAccessGate->isConfigured(),
            'two_factor_required' => true,
        ]]);
    }

    public function apiLogin(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim($request->string('email')->toString())),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'two_factor_code' => ['nullable', 'digits:6'],
        ]);

        if (! Auth::once(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            $this->audit->log('auth.api_login_failed', metadata: [
                'email_hash' => $this->audit->identifierFingerprint($credentials['email']),
            ]);
            throw ValidationException::withMessages(['email' => 'Kredensial tidak valid.']);
        }

        $user = Auth::user();
        if (! $user->is_active) {
            $this->audit->log('auth.api_login_failed', $user, metadata: ['reason' => 'inactive']);
            throw ValidationException::withMessages(['email' => 'Akun sedang dinonaktifkan.']);
        }

        if ($user->isStaff() && (! $user->hasConfirmedTwoFactor() || ! $this->totp->verify($user->two_factor_secret, (string) ($credentials['two_factor_code'] ?? '')))) {
            $this->audit->log('auth.api_login_failed', $user, metadata: ['reason' => 'invalid_two_factor']);
            throw ValidationException::withMessages(['two_factor_code' => 'Pegawai wajib menggunakan kode autentikasi dua faktor yang valid.']);
        }

        $expiresAt = now()->addDays((int) config('sanctum.expiration_days', 30));
        $abilities = $user->isStaff() ? [
            'profile:read',
            'orders:read',
            'orders:write',
            'ai:order-intake',
            'pos:checkout',
        ] : [
            'profile:read',
            'orders:read',
            'orders:write',
        ];
        $token = $user->createToken($credentials['device_name'], $abilities, $expiresAt)->plainTextToken;
        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.api_login', $user, metadata: ['device_name' => $credentials['device_name'], 'expires_at' => $expiresAt->toIso8601String()]);

        return response()->json(['data' => ['user' => $user, 'token' => $token]]);
    }

    public function staffApiLogin(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim($request->string('email')->toString())),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'access_code' => ['nullable', 'string', 'max:128'],
            'two_factor_code' => ['nullable', 'digits:6'],
        ]);

        $validAccessCode = ! $this->staffAccessGate->isConfigured()
            || $this->staffAccessGate->verify((string) ($credentials['access_code'] ?? ''));
        $validCredentials = Auth::once([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);
        $user = $validCredentials ? Auth::user() : null;

        if (! $validAccessCode || ! $user || ! $user->isStaff() || ! $user->is_active) {
            $this->audit->log('auth.api_login_failed', $user, metadata: [
                'email_hash' => $this->audit->identifierFingerprint($credentials['email']),
                'reason' => 'invalid_staff_credentials',
            ]);
            throw ValidationException::withMessages(['email' => 'Kredensial pegawai tidak valid.']);
        }

        if (! $user->hasConfirmedTwoFactor()) {
            $secret = $this->totp->generateSecret();
            $setupToken = Str::random(64);
            Cache::put(
                $this->staffSetupKey($setupToken),
                Crypt::encryptString(json_encode([
                    'user_id' => $user->id,
                    'secret' => $secret,
                    'device_name' => $credentials['device_name'],
                ], JSON_THROW_ON_ERROR)),
                now()->addMinutes(10),
            );
            $this->audit->log('auth.two_factor_setup_requested', $user);

            return response()->json(['data' => [
                'requires_two_factor_setup' => true,
                'setup_token' => $setupToken,
                'secret' => $secret,
                'uri' => $this->totp->uri($secret, $user->email),
            ]]);
        }

        if (! $this->totp->verify($user->two_factor_secret, (string) ($credentials['two_factor_code'] ?? ''))) {
            $this->audit->log('auth.api_login_failed', $user, metadata: ['reason' => 'invalid_two_factor']);
            throw ValidationException::withMessages([
                'two_factor_code' => 'Kode autentikasi tidak valid atau telah kedaluwarsa.',
            ]);
        }

        return $this->staffTokenResponse($user, $credentials['device_name']);
    }

    public function confirmStaffTwoFactorSetup(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'setup_token' => ['required', 'string', 'size:64'],
            'code' => ['required', 'digits:6'],
        ]);
        $key = $this->staffSetupKey($credentials['setup_token']);
        $encrypted = Cache::get($key);

        try {
            $setup = is_string($encrypted)
                ? json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR)
                : null;
        } catch (\Throwable) {
            $setup = null;
        }

        $user = is_array($setup) ? User::query()->find($setup['user_id'] ?? null) : null;
        $secret = is_array($setup) ? (string) ($setup['secret'] ?? '') : '';
        $deviceName = is_array($setup) ? (string) ($setup['device_name'] ?? '') : '';

        if (! $user || ! $user->isStaff() || ! $user->is_active || $secret === '' || $deviceName === '') {
            throw ValidationException::withMessages([
                'setup_token' => 'Sesi aktivasi autentikator telah kedaluwarsa. Silakan masuk kembali.',
            ]);
        }

        if (! $this->totp->verify($secret, $credentials['code'])) {
            $this->audit->log('auth.two_factor_failed', $user, metadata: ['reason' => 'invalid_setup_code']);
            throw ValidationException::withMessages([
                'code' => 'Kode belum cocok. Pastikan waktu perangkat benar lalu coba kembali.',
            ]);
        }

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        Cache::forget($key);
        $this->audit->log('auth.two_factor_enabled', $user);

        return $this->staffTokenResponse($user, $deviceName);
    }

    private function staffTokenResponse(User $user, string $deviceName): JsonResponse
    {
        $expiresAt = now()->addDays((int) config('sanctum.expiration_days', 30));
        $abilities = [
            'profile:read',
            'staff:dashboard',
            'orders:read',
            'orders:write',
            'ai:order-intake',
        ];
        if ($user->hasRole('owner', 'admin')) {
            $abilities[] = 'project-reminders:reveal';
            $abilities[] = 'staff:products';
            $abilities[] = 'staff:approvals';
            $abilities[] = 'staff:suppliers';
            $abilities[] = 'staff:finance';
            $abilities[] = 'staff:content';
        }
        if ($user->hasRole('owner', 'admin', 'cashier')) {
            $abilities[] = 'pos:checkout';
            $abilities[] = 'staff:customers';
            $abilities[] = 'staff:digital';
        }

        $token = $user->createToken($deviceName, $abilities, $expiresAt)->plainTextToken;
        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.api_login', $user, metadata: [
            'device_name' => $deviceName,
            'expires_at' => $expiresAt->toIso8601String(),
            'two_factor' => true,
            'staff_access' => $this->staffAccessGate->isConfigured(),
        ]);

        return response()->json(['data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ['code' => $user->role->value, 'label' => $user->role->label()],
            ],
            'token' => $token,
        ]]);
    }

    private function staffSetupKey(string $token): string
    {
        return 'staff-two-factor-setup:'.hash('sha256', $token);
    }

    public function apiLogout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        if ($token && $token->tokenable_id === $request->user()->getKey()) {
            $token->delete();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
