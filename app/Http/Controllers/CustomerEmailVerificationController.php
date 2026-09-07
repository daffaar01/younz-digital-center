<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerEmailVerificationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function notice(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->role === UserRole::Customer, 403);

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless($user->role === UserRole::Customer && $user->is_active, 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        $wasVerified = $user->hasVerifiedEmail();
        if (! $wasVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (! $wasVerified) {
            $this->audit->log('customer.email_verified', $user);
        }

        if ($request->user()?->is($user)) {
            return redirect()->route('customer.dashboard')
                ->with('status', 'Email berhasil diverifikasi. Selamat datang!');
        }

        return redirect()->route('customer.login')
            ->with('status', 'Email berhasil diverifikasi. Kembali ke aplikasi lalu masuk menggunakan akun Anda.');
    }

    public function resend(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === UserRole::Customer, 403);

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('customer.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();
        $this->audit->log('customer.email_verification_resent', $request->user());

        return back()->with('status', 'Tautan verifikasi baru telah dikirim.');
    }
}
