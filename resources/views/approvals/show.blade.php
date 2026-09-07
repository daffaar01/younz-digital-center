@extends('layouts.app')
@section('content')
@php
    $refund = $approval->refund;
    $subjectLabel = match(true) {
        $approval->subject instanceof \App\Models\Product => $approval->subject->name,
        $approval->subject instanceof \App\Models\User => $approval->subject->name,
        $approval->subject instanceof \App\Models\DigitalTransaction => $approval->subject->transaction_number,
        default => 'Tanpa subjek tersimpan',
    };
@endphp
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><a href="{{ route('approvals.index') }}" class="text-sm font-bold text-brand-700">← Daftar persetujuan</a><h1 class="mt-1 text-3xl font-black text-ink-900">{{ $approval->request_number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $approval->type->label() }} · diminta {{ $approval->requested_at->format('d F Y, H:i') }}</p></div>
    <span class="badge text-sm {{ $approval->status->value === 'pending' ? 'bg-amber-100 text-amber-700' : ($approval->status->value === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700') }}">{{ $approval->status->label() }}</span>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
    <div class="space-y-6">
        <section class="card">
            <div class="grid gap-4 sm:grid-cols-2"><div><p class="text-sm text-slate-500">Jenis tindakan</p><p class="font-black">{{ $approval->type->label() }}</p></div><div><p class="text-sm text-slate-500">Subjek</p><p class="font-black">{{ $refund?->refund_number ?? $subjectLabel }}</p></div><div><p class="text-sm text-slate-500">Pemohon</p><p class="font-bold">{{ $approval->requester->name }} · {{ $approval->requester->role->label() }}</p></div><div><p class="text-sm text-slate-500">Batas keputusan</p><p class="font-bold">{{ $approval->expires_at?->format('d F Y, H:i') ?? '-' }}</p></div></div>
            <div class="mt-5 rounded-2xl bg-slate-50 p-4"><p class="text-sm text-slate-500">Alasan</p><p class="mt-1 whitespace-pre-line">{{ $approval->reason }}</p></div>
        </section>

        @if($refund)
        <section class="table-wrap"><div class="p-5"><h2 class="text-lg font-black">Rincian refund {{ $refund->sale->invoice_number }}</h2></div><table class="data-table"><thead><tr><th>Item</th><th>Jumlah</th><th>Stok</th><th class="text-right">Refund</th></tr></thead><tbody>@foreach($refund->items as $item)<tr><td class="font-bold">{{ $item->saleItem->name }}</td><td>{{ $item->quantity }}</td><td>{{ $item->restore_stock ? 'Dikembalikan' : 'Tidak' }}</td><td class="text-right">Rp {{ number_format($item->amount,0,',','.') }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="3">Total</th><th class="text-right">Rp {{ number_format($refund->amount,0,',','.') }}</th></tr></tfoot></table></section>
        @else
        <section class="card"><h2 class="text-lg font-black">Payload yang akan diterapkan</h2><pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ json_encode($approval->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></section>
        @endif

        <section class="card"><h2 class="text-lg font-black">Jejak keputusan</h2><div class="mt-4 space-y-4">@foreach($approval->events as $event)<div class="border-l-2 border-brand-500 pl-4"><div class="flex flex-wrap justify-between gap-2"><strong>{{ str($event->to_status)->replace('_',' ')->title() }}</strong><span class="text-xs text-slate-500">{{ $event->created_at->format('d/m/Y H:i:s') }}</span></div><p class="text-sm text-slate-600">{{ $event->actor?->name ?? 'Sistem' }}@if($event->notes) · {{ $event->notes }}@endif</p></div>@endforeach</div></section>
    </div>
    <aside>
        @if($approval->status->value === 'pending')
        <section class="card"><h2 class="text-lg font-black">Keputusan</h2><p class="mt-1 text-sm text-slate-500">Persetujuan akan menerapkan tindakan ini secara atomik dan mencatatnya di audit log.</p>@if(auth()->id() === $approval->requested_by && config('approvals.require_separate_approver'))<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-700">Pemisahan tugas aktif: Anda tidak dapat menyetujui permintaan sendiri.</p>@endif @if($refund && auth()->user()->hasRole('admin') && $refund->amount > config('approvals.refund_owner_threshold'))<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-700">Nilai melebihi batas admin. Persetujuan owner diperlukan.</p>@endif
            <form method="post" action="{{ route('approvals.approve', $approval) }}" class="mt-5 space-y-3">@csrf<label>Catatan persetujuan (opsional)</label><textarea name="notes" rows="3"></textarea><button class="btn-primary w-full">Setujui dan terapkan</button></form>
            <form method="post" action="{{ route('approvals.reject', $approval) }}" class="mt-6 space-y-3 border-t border-slate-200 pt-5">@csrf<label>Alasan penolakan</label><textarea name="notes" rows="3" required minlength="5"></textarea><button class="btn-secondary w-full border-red-300 text-red-700">Tolak permintaan</button></form>
        </section>
        @else
        <section class="card"><h2 class="font-black">Keputusan final</h2><p class="mt-2 text-sm text-slate-600">{{ $approval->decider?->name ?? 'Sistem' }} · {{ $approval->decided_at?->format('d F Y, H:i') }}</p>@if($approval->decision_notes)<p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">{{ $approval->decision_notes }}</p>@endif</section>
        @endif
    </aside>
</div>
@endsection
