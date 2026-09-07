<?php

namespace App\Http\Controllers;

use App\Contracts\FirebaseTokenVerifier;
use App\Data\FirebaseUserIdentity;
use App\Enums\UserRole;
use App\Exceptions\InvalidFirebaseToken;
use App\Models\Customer;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('customer.auth.login');
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->audit->log('customer.login_failed', metadata: [
                'email_hash' => $this->audit->identifierFingerprint($credentials['email']),
            ]);
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }

        $user = $request->user();

        if ($user->role !== UserRole::Customer) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun ini adalah akun pegawai. Silakan gunakan Login Pegawai.',
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun sedang dinonaktifkan.']);
        }

        if (! $user->customer()->exists()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Profil pelanggan belum tersedia. Silakan hubungi operator Younz Digital Center.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $request->session()->regenerate();

            return redirect()->route('verification.notice')
                ->with('status', 'Verifikasi email Anda sebelum membuka portal pelanggan.');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $this->audit->log('customer.login', $user);

        return redirect()->route('customer.dashboard');
    }

    public function apiLogin(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        if (! Auth::once(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }
        $user = Auth::user();
        if ($user->role !== UserRole::Customer || ! $user->is_active || ! $user->customer()->exists()) {
            throw ValidationException::withMessages(['email' => 'Akun pelanggan tidak dapat digunakan.']);
        }
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Verifikasi email Anda sebelum masuk.']);
        }
        $expiresAt = now()->addDays((int) config('sanctum.expiration_days', 30));
        $token = $user->createToken($data['device_name'], ['profile:read', 'orders:read', 'orders:write'], $expiresAt)->plainTextToken;
        $user->update(['last_login_at' => now()]);
        $this->audit->log('customer.api_login', $user, metadata: ['device_name' => $data['device_name'], 'expires_at' => $expiresAt->toIso8601String()]);

        return response()->json(['data' => ['user' => $user->load('customer'), 'token' => $token]]);
    }

    public function apiRegister(Request $request): JsonResponse
    {
        $request->merge([
            'name' => trim($request->string('name')->toString()),
            'email' => Str::lower(trim($request->string('email')->toString())),
            'phone' => preg_replace('/\D+/', '', $request->string('phone')->toString()),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'digits_between:9,15', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'terms' => ['accepted'],
            'device_name' => ['required', 'string', 'max:100'],
        ], [
            'email.unique' => 'Email sudah terdaftar. Jika akun belum aktif, gunakan kirim ulang email verifikasi.',
            'phone.digits_between' => 'Nomor WhatsApp harus terdiri dari 9 sampai 15 digit.',
            'phone.unique' => 'Nomor WhatsApp sudah digunakan oleh akun lain.',
            'password.min' => 'Password minimal 12 karakter.',
            'password.letters' => 'Password harus memiliki huruf.',
            'password.mixed' => 'Password harus memiliki huruf besar dan kecil.',
            'password.numbers' => 'Password harus memiliki minimal satu angka.',
            'terms.accepted' => 'Anda perlu menyetujui penggunaan data untuk pengelolaan pesanan.',
        ]);
        $user = DB::transaction(function () use ($data): User {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password'], 'role' => UserRole::Customer, 'is_active' => true]);
            Customer::create(['user_id' => $user->id, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'type' => 'umum']);

            return $user;
        }, 3);
        $emailSent = true;
        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
            $emailSent = false;
        }
        $this->audit->log('customer.api_registered', $user, metadata: [
            'device_name' => $data['device_name'],
            'verification_email_sent' => $emailSent,
        ]);

        return response()->json([
            'message' => $emailSent
                ? 'Akun berhasil dibuat. Buka tautan verifikasi yang dikirim ke email Anda.'
                : 'Akun berhasil dibuat, tetapi email verifikasi belum dapat dikirim. Tekan kirim ulang email.',
            'data' => [
                'user' => $user->load('customer'),
                'verification_required' => true,
                'verification_email_sent' => $emailSent,
            ],
        ], 201);
    }

    public function apiResendVerification(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user
            || $user->role !== UserRole::Customer
            || ! $user->is_active
            || ! Hash::check($data['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi. Silakan masuk ke aplikasi.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Email verifikasi belum dapat dikirim. Silakan coba kembali beberapa saat lagi.',
            ], 503);
        }

        $this->audit->log('customer.api_email_verification_resent', $user);

        return response()->json(['message' => 'Tautan verifikasi baru telah dikirim ke email Anda.']);
    }

    public function showRegistration(): View
    {
        return view('customer.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim($request->string('name')->toString()),
            'email' => Str::lower(trim($request->string('email')->toString())),
            'phone' => preg_replace('/\D+/', '', $request->string('phone')->toString()),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'digits_between:9,15', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'terms' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ], [
            'phone.digits_between' => 'Nomor WhatsApp harus terdiri dari 9 sampai 15 digit.',
            'terms.accepted' => 'Anda perlu menyetujui penggunaan data untuk pengelolaan pesanan.',
        ]);

        $user = DB::transaction(function () use ($data, $request): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::Customer,
                'is_active' => true,
            ]);

            Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'type' => 'umum',
                'marketing_consent' => $request->boolean('marketing_consent'),
            ]);

            return $user;
        }, 3);

        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));
        $this->audit->log('customer.registered', $user);

        return redirect()->route('verification.notice')
            ->with('status', 'Akun berhasil dibuat. Tautan verifikasi telah dikirim ke email Anda.');
    }

    public function firebase(Request $request, FirebaseTokenVerifier $verifier): JsonResponse
    {
        abort_unless(config('services.firebase.enabled'), 404);

        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $identity = $verifier->verify($data['id_token']);
        } catch (InvalidFirebaseToken $exception) {
            $this->audit->log('customer.firebase_login_failed', metadata: ['reason' => 'invalid_token']);
            throw ValidationException::withMessages([
                'id_token' => 'Login Firebase tidak dapat diverifikasi. Silakan coba kembali.',
            ]);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'id_token' => 'Layanan login Firebase sedang tidak tersedia.',
            ]);
        }

        $isGoogle = $identity->provider === 'google.com'
            && $identity->emailVerified
            && filter_var($identity->email, FILTER_VALIDATE_EMAIL);

        if (! $isGoogle) {
            $this->audit->log('customer.firebase_login_failed', metadata: ['reason' => 'invalid_identity']);
            throw ValidationException::withMessages([
                'id_token' => 'Gunakan akun Google yang terverifikasi.',
            ]);
        }

        [$user, $isNewUser] = DB::transaction(
            fn (): array => $this->resolveGoogleFirebaseUser($identity),
            3,
        );

        Auth::login($user, false);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $this->audit->log('customer.firebase_login', $user, metadata: ['provider' => $identity->provider]);

        $needsProfile = $isNewUser || blank($user->phone) || blank($user->customer?->phone);

        return response()->json([
            'message' => $needsProfile ? 'Login berhasil. Lengkapi profil pelanggan Anda.' : 'Login Firebase berhasil.',
            'redirect' => $needsProfile ? route('customer.profile.edit') : route('customer.dashboard'),
        ]);
    }

    /** @return array{User, bool} */
    private function resolveGoogleFirebaseUser(FirebaseUserIdentity $identity): array
    {
        $uidUser = User::query()->where('firebase_uid', $identity->uid)->lockForUpdate()->first();
        $emailUser = User::query()->where('email', $identity->email)->lockForUpdate()->first();

        if ($uidUser && $emailUser && ! $uidUser->is($emailUser)) {
            throw ValidationException::withMessages([
                'id_token' => 'Identitas Google bertabrakan dengan akun lain. Hubungi operator.',
            ]);
        }

        $user = $uidUser ?? $emailUser;
        $isNewUser = ! $user;

        $this->ensureCustomerFirebaseAccount($user, $identity->uid, 'Email ini terdaftar sebagai akun pegawai. Gunakan Login Pegawai.');

        if ($uidUser && strcasecmp($uidUser->email, $identity->email) !== 0) {
            throw ValidationException::withMessages([
                'id_token' => 'Email Google tidak cocok dengan akun pelanggan. Hubungi operator.',
            ]);
        }

        if (! $user) {
            $user = User::create([
                'name' => $identity->name,
                'email' => $identity->email,
                'email_verified_at' => now(),
                'firebase_uid' => $identity->uid,
                'avatar_url' => $identity->picture,
                'password' => Str::random(64),
                'role' => UserRole::Customer,
                'is_active' => true,
            ]);
        } else {
            $claimedUnverifiedAccount = ! $user->hasVerifiedEmail() && blank($user->firebase_uid);
            $updates = [
                'firebase_uid' => $identity->uid,
                'avatar_url' => $identity->picture ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'name' => $user->name ?: $identity->name,
            ];

            if ($claimedUnverifiedAccount) {
                // A verified Google identity may safely claim an unverified local
                // registration, but the old password must never remain usable.
                $updates['password'] = Str::random(64);
                $updates['remember_token'] = null;
            }

            $user->update($updates);

            if ($claimedUnverifiedAccount) {
                $this->audit->log('customer.unverified_account_claimed', $user, metadata: ['provider' => 'google.com']);
            }
        }

        return [$this->ensureCustomerProfile($user), $isNewUser];
    }

    private function ensureCustomerFirebaseAccount(?User $user, string $firebaseUid, string $staffMessage): void
    {
        if ($user && $user->role !== UserRole::Customer) {
            throw ValidationException::withMessages(['id_token' => $staffMessage]);
        }

        if ($user && filled($user->firebase_uid) && $user->firebase_uid !== $firebaseUid) {
            throw ValidationException::withMessages([
                'id_token' => 'Akun pelanggan sudah tertaut dengan identitas Firebase lain.',
            ]);
        }

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages(['id_token' => 'Akun sedang dinonaktifkan.']);
        }
    }

    private function ensureCustomerProfile(User $user): User
    {
        $customer = $user->customer()->firstOrCreate([], [
            'name' => $user->name,
            'email' => str_ends_with($user->email, '@auth.younz.local') ? null : $user->email,
            'phone' => $user->phone,
            'type' => 'umum',
        ]);

        if (blank($customer->phone) && filled($user->phone)) {
            $customer->update(['phone' => $user->phone]);
        }

        return $user->fresh('customer');
    }
}
