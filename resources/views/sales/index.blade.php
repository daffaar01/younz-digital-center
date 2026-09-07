@extends('layouts.app')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-sm font-bold text-brand-700">PENJUALAN</p><h1 class="mt-1 text-3xl font-black text-ink-900">Riwayat transaksi</h1><p class="mt-1 text-sm text-slate-500">Buka transaksi untuk mencetak ulang struk atau mengajukan refund.</p></div>
    <form class="flex gap-2"><input name="q" value="{{ $search }}" placeholder="Invoice atau pelanggan"><button class="btn-secondary">Cari</button></form>
</div>
<section class="table-wrap mt-6">
    <table class="data-table">
        <thead><tr><th>Invoice</th><th>Waktu</th><th>Kasir / Pelanggan</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Refund</th><th></th></tr></thead>
        <tbody>
        @forelse($sales as $sale)
            @php($refunded = (int) ($sale->refunded_amount ?? 0))
            <tr>
                <td class="font-bold">{{ $sale->invoice_number }}</td>
                <td>{{ $sale->completed_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $sale->user->name }}<div class="text-xs text-slate-500">{{ $sale->customer?->name ?? 'Umum' }}</div></td>
                <td><span class="badge {{ $sale->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ str($sale->status)->replace('_', ' ')->title() }}</span></td>
                <td class="text-right">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                <td class="text-right {{ $refunded ? 'font-bold text-red-600' : 'text-slate-400' }}">Rp {{ number_format($refunded, 0, ',', '.') }}</td>
                <td class="text-right"><a class="font-bold text-brand-700" href="{{ route('sales.show', $sale) }}">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-slate-500">Transaksi tidak ditemukan.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div class="mt-5">{{ $sales->links() }}</div>
@endsection
