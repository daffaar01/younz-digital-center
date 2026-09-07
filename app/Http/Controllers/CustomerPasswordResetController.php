<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerPasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function emailApi(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));
        $status = Password::sendResetLink([
            'email' => $email,
            'role' => UserRole::Customer->value,
        ]);

        $this->audit->log('customer.password_reset_requested', metadata: [
            'email_hash' => $this->audit->identifierFingerprint($email),
            'accepted' => $status === Password::RESET_LINK_SENT,
        ]);

        return response()->json([
            'message' => 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.',
        ]);
    }

    public function updateApi(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $data + ['role' => UserRole::Customer->value],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
                $this->audit->log('customer.password_reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Password berhasil diubah. Silakan masuk.']);
    }
    public function request(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));
        $status = Password::sendResetLink([
            'email' => $email,
            'role' => UserRole::Customer->value,
        ]);

        $this->audit->log('customer.password_reset_requested', metadata: [
            'email_hash' => $this->audit->identifierFingerprint($email),
            'accepted' => $status === Password::RESET_LINK_SENT,
        ]);

        // Always return the same message so the endpoint cannot enumerate accounts.
        return back()->with('status', 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('customer.auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $data + ['role' => UserRole::Customer->value],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
                $this->audit->log('customer.password_reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('customer.login')->with('status', 'Password berhasil diubah. Silakan masuk.');
    }
}
