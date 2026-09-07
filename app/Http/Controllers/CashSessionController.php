<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Refund;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['opening_balance' => ['required', 'integer', 'min:0']]);

        try {
            $session = DB::transaction(fn () => CashSession::create([
                'user_id' => $request->user()->id,
                'opening_balance' => $data['opening_balance'],
                'status' => 'open',
                'open_guard' => 'user:'.$request->user()->id,
                'opened_at' => now(),
            ]), 3);
        } catch (QueryException) {
            throw ValidationException::withMessages(['opening_balance' => 'Masih ada sesi kasir yang terbuka.']);
        }
        $this->audit->log('cash_session.opened', $session, after: $session->toArray());

        return back()->with('status', 'Sesi kasir berhasil dibuka.');
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        $data = $request->validate([
            'closing_balance' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $cashSession, $data): void {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);
            abort_unless($locked->user_id === $request->user()->id || $request->user()->hasRole('owner', 'admin'), 403);
            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['closing_balance' => 'Sesi kasir ini sudah ditutup.']);
            }

            $cashReceived = $locked->sales()->whereIn('sales.status', ['completed', 'partially_refunded', 'refunded'])->withSum([
                'payments as cash_paid' => fn ($query) => $query->where('method', 'cash')->where('status', 'paid'),
            ], 'amount')->get()->sum('cash_paid');
            $cashRefunded = Refund::query()
                ->where('status', 'completed')
                ->where('method', 'cash')
                ->whereBetween('processed_at', [$locked->opened_at, now()])
                ->whereHas('sale', fn ($query) => $query->where('cash_session_id', $locked->id))
                ->sum('amount');
            $expected = $locked->opening_balance + $cashReceived - $cashRefunded;
            $before = $locked->toArray();
            $locked->update([
                'expected_balance' => $expected,
                'closing_balance' => $data['closing_balance'],
                'difference' => $data['closing_balance'] - $expected,
                'status' => 'closed',
                'open_guard' => null,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->log('cash_session.closed', $locked, $before, $locked->fresh()->toArray());
        }, 3);

        return back()->with('status', 'Sesi kasir berhasil ditutup.');
    }
}
