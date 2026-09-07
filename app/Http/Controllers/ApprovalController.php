<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\ApprovalEngine;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        if ($status !== null) {
            validator(['status' => $status], ['status' => [Rule::enum(ApprovalStatus::class)]])->validate();
        }

        $approvals = ApprovalRequest::query()
            ->with(['requester', 'decider', 'refund.sale', 'subject'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('requested_at')
            ->paginate(20)
            ->withQueryString();

        return view('approvals.index', compact('approvals', 'status'));
    }

    public function show(ApprovalRequest $approval): View
    {
        $approval->load([
            'requester',
            'decider',
            'events.actor',
            'subject',
            'refund.sale.user',
            'refund.items.saleItem',
        ]);

        return view('approvals.show', compact('approval'));
    }

    public function approve(Request $request, ApprovalRequest $approval, ApprovalEngine $engine): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $engine->approve($approval, $request->user(), $data['notes'] ?? null);

        return redirect()->route('approvals.show', $approval)->with('status', 'Permintaan telah disetujui dan tindakan diterapkan.');
    }

    public function reject(Request $request, ApprovalRequest $approval, ApprovalEngine $engine): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $engine->reject($approval, $request->user(), $data['notes']);

        return redirect()->route('approvals.show', $approval)->with('status', 'Permintaan telah ditolak.');
    }
}
