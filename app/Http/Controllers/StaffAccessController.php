<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\StaffAccessGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAccessController extends Controller
{
    public function __construct(
        private readonly StaffAccessGate $gate,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless($this->gate->isConfigured(), 503, 'Gerbang akses pegawai belum dikonfigurasi.');

        if ($this->gate->hasValidSession($request)) {
            return redirect()->route('login');
        }

        return view('auth.staff-access');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->gate->isConfigured(), 503, 'Gerbang akses pegawai belum dikonfigurasi.');

        $data = $request->validate([
            'access_code' => ['required', 'string', 'max:128'],
        ]);

        if (! $this->gate->verify($data['access_code'])) {
            $this->audit->log('auth.staff_access_failed');

            throw ValidationException::withMessages([
                'access_code' => 'Kode akses tidak sesuai.',
            ]);
        }

        $this->gate->authorize($request);
        $this->audit->log('auth.staff_access_verified');

        return redirect()->intended(route('login'));
    }
}
