<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Enums\DigitalTransactionStatus;
use App\Models\DigitalTransaction;
use App\Models\TopupOrder;
use App\Support\AuditLogger;
use App\Support\DocumentNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DigitalTransactionController extends Controller
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AuditLogger $audit,
        private readonly RequestActionApproval $requestApproval,
    ) {}

    public function index(): View
    {
        return view('digital.index', [
            'transactions' => DigitalTransaction::query()->with(['user', 'customer'])->latest()->paginate(20),
            'topupOrders' => TopupOrder::query()->latest()->paginate(20, ['*'], 'topup_page'),
            'statuses' => DigitalTransactionStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'type' => ['required', 'in:pulsa,paket_data,ewallet,voucher_game,token_pln,pln_pascabayar,pdam,internet'],
            'provider' => ['nullable', 'string', 'max:100'],
            'destination' => ['required', 'string', 'max:100', 'confirmed'],
            'nominal' => ['required', 'integer', 'min:0'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'admin_fee' => ['nullable', 'integer', 'min:0'],
            'provider_reference' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:diproses,berhasil,gagal'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $idempotencyKey = mb_strtolower($data['idempotency_key']);
        $fingerprintData = $data;
        unset($fingerprintData['idempotency_key']);
        ksort($fingerprintData);
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $existing = DigitalTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['idempotency_key' => 'Kunci idempotency sudah digunakan untuk payload yang berbeda.']);
            }

            return back()->with('status', "Permintaan duplikat tidak diproses ulang ({$existing->transaction_number}).");
        }

        $targetStatus = $data['status'];
        unset($data['status'], $data['idempotency_key']);

        try {
            $transaction = DB::transaction(fn () => DigitalTransaction::create([
                ...$data,
                'transaction_number' => $this->numbers->next('DIG'),
                'user_id' => $request->user()->id,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'status' => DigitalTransactionStatus::Pending,
            ]), 3);
        } catch (QueryException) {
            $existing = DigitalTransaction::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

            return back()->with('status', "Permintaan duplikat tidak diproses ulang ({$existing->transaction_number}).");
        }
        $this->audit->log('digital_transaction.created', $transaction, after: $transaction->toArray());
        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::DigitalTransaction,
            $transaction,
            ['target_status' => $targetStatus],
            "Pemrosesan {$transaction->type} ke {$transaction->destination}.",
        );

        return back()->with('status', "Transaksi dicatat dan menunggu persetujuan ({$approval->request_number}).");
    }
}
