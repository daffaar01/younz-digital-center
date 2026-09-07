@extends('layouts.app')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-sm font-bold text-brand-700">KONTROL INTERNAL</p><h1 class="mt-1 text-3xl font-black text-ink-900">Persetujuan</h1><p class="mt-1 text-sm text-slate-500">Semua keputusan tercatat dalam jejak audit yang tidak dapat diubah.</p></div>
    <form><select name="status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach(\App\Enums\ApprovalStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach</select></form>
</div>
<section class="table-wrap mt-6"><table class="data-table"><thead><tr><th>Permintaan</th><th>Jenis / Referensi</th><th>Pemohon</th><th>Waktu</th><th>Status</th><th class="text-right">Nilai</th><th></th></tr></thead><tbody>
@forelse($approvals as $approval)
<tr class="{{ $approval->status->value === 'pending' ? 'bg-amber-50/40' : '' }}"><td class="font-bold">{{ $approval->request_number }}</td><td>{{ $approval->type->label() }}<div class="text-xs text-slate-500">{{ $approval->refund?->sale?->invoice_number }}</div></td><td>{{ $approval->requester->name }}</td><td>{{ $approval->requested_at->format('d/m/Y H:i') }}@if($approval->expires_at)<div class="text-xs text-slate-500">Batas {{ $approval->expires_at->format('d/m H:i') }}</div>@endif</td><td><span class="badge {{ $approval->status->value === 'pending' ? 'bg-amber-100 text-amber-700' : ($approval->status->value === 'approved' ? 'bg-emerald-100 text-emerald-700' : '') }}">{{ $approval->status->label() }}</span></td><td class="text-right">Rp {{ number_format($approval->refund?->amount ?? 0,0,',','.') }}</td><td class="text-right"><a class="font-bold text-brand-700" href="{{ route('approvals.show', $approval) }}">Tinjau</a></td></tr>
@empty<tr><td colspan="7" class="text-center text-slate-500">Tidak ada permintaan.</td></tr>@endforelse
</tbody></table></section><div class="mt-5">{{ $approvals->links() }}</div>
@endsection
