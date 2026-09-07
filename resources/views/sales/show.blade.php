@extends('layouts.app')
@section('content')
@php
    $completedRefunds = $sale->refunds->filter(fn ($refund) => $refund->status->value === 'completed');
    $refundedAmount = $completedRefunds->sum('amount');
    $canRefund = in_array($sale->status, ['completed', 'partially_refunded'], true);
@endphp
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><a href="{{ route('sales.index') }}" class="text-sm font-bold text-brand-700">← Riwayat penjualan</a><h1 class="mt-1 text-3xl font-black text-ink-900">{{ $sale->invoice_number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $sale->completed_at?->format('d F Y, H:i') }} · {{ $sale->user->name }}</p></div>
    <a class="btn-secondary" href="{{ route('sales.receipt', $sale) }}" target="_blank">Cetak struk</a>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
    <div class="space-y-6">
        <section class="table-wrap">
            <div class="flex items-center justify-between p-5"><h2 class="text-lg font-black">Item transaksi</h2><span class="badge">{{ str($sale->status)->replace('_', ' ')->title() }}</span></div>
            <table class="data-table"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th class="text-right">Subtotal</th></tr></thead><tbody>
            @foreach($sale->items as $item)<tr><td class="font-bold">{{ $item->name }}<div class="text-xs font-normal text-slate-500">{{ $item->sku }}</div></td><td>{{ $item->quantity }}</td><td>Rp {{ number_format($item->unit_price,0,',','.') }}</td><td class="text-right">Rp {{ number_format($item->subtotal,0,',','.') }}</td></tr>@endforeach
            </tbody></table>
            <div class="border-t border-slate-200 p-5 text-sm">
                <div class="flex justify-between"><span>Subtotal</span><strong>Rp {{ number_format($sale->subtotal,0,',','.') }}</strong></div>
                <div class="mt-2 flex justify-between"><span>Diskon transaksi</span><strong>- Rp {{ number_format($sale->discount,0,',','.') }}</strong></div>
                <div class="mt-3 flex justify-between text-lg"><span class="font-black">Total</span><strong>Rp {{ number_format($sale->total,0,',','.') }}</strong></div>
                @if($refundedAmount)<div class="mt-2 flex justify-between font-bold text-red-600"><span>Sudah direfund</span><span>- Rp {{ number_format($refundedAmount,0,',','.') }}</span></div>@endif
            </div>
        </section>

        <section class="table-wrap">
            <div class="p-5"><h2 class="text-lg font-black">Riwayat refund</h2></div>
            <table class="data-table"><thead><tr><th>Nomor</th><th>Pemohon</th><th>Status</th><th>Nilai</th><th></th></tr></thead><tbody>
            @forelse($sale->refunds as $refund)<tr><td class="font-bold">{{ $refund->refund_number }}</td><td>{{ $refund->requester->name }}</td><td><span class="badge">{{ $refund->status->label() }}</span></td><td>Rp {{ number_format($refund->amount,0,',','.') }}</td><td>@if(auth()->user()->hasRole('owner','admin'))<a class="font-bold text-brand-700" href="{{ route('approvals.show', $refund->approvalRequest) }}">Approval</a>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-500">Belum ada refund.</td></tr>@endforelse
            </tbody></table>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="card"><h2 class="text-lg font-black">Pembayaran</h2><div class="mt-4 space-y-2">@foreach($sale->payments as $payment)<div class="flex justify-between"><span>{{ strtoupper($payment->method) }}</span><strong>Rp {{ number_format($payment->amount,0,',','.') }}</strong></div>@endforeach</div>@if($sale->customer)<div class="mt-5 border-t border-slate-200 pt-4 text-sm"><span class="text-slate-500">Pelanggan</span><p class="font-bold">{{ $sale->customer->name }}</p></div>@endif</section>

        @if($canRefund)
        <section class="card">
            <h2 class="text-lg font-black">Ajukan refund</h2><p class="mt-1 text-sm text-slate-500">Nilai dihitung otomatis setelah diskon. Stok baru dikembalikan setelah disetujui.</p>
            <form method="post" action="{{ route('sales.refunds.store', $sale) }}" class="mt-5 space-y-4">@csrf
                <div class="space-y-3">
                    @foreach($sale->items as $item)
                        @php
                            $reservedQuantity = $item->refundItems->filter(fn ($refundItem) => in_array($refundItem->refund->status->value, ['pending','completed'], true))->sum('quantity');
                            $remainingQuantity = $item->quantity - $reservedQuantity;
                        @endphp
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="flex justify-between gap-3"><strong>{{ $item->name }}</strong><span class="text-xs text-slate-500">Sisa {{ $remainingQuantity }}</span></div>
                            <div class="mt-2 grid grid-cols-[1fr_auto] items-end gap-3"><div><label>Jumlah</label><input type="number" min="0" max="{{ $remainingQuantity }}" name="items[{{ $item->id }}][quantity]" value="{{ old("items.{$item->id}.quantity", 0) }}" {{ $remainingQuantity < 1 ? 'disabled' : '' }}></div><label class="mb-2 flex items-center gap-2 text-sm"><input type="hidden" name="items[{{ $item->id }}][restore_stock]" value="0"><input type="checkbox" name="items[{{ $item->id }}][restore_stock]" value="1" checked {{ $remainingQuantity < 1 ? 'disabled' : '' }}> Kembali ke stok</label></div>
                        </div>
                    @endforeach
                </div>
                <div><label>Metode pengembalian</label><select name="method" required><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="other">Lainnya</option></select></div>
                <div><label>Alasan</label><textarea name="reason" rows="3" required minlength="5" placeholder="Jelaskan alasan refund">{{ old('reason') }}</textarea></div>
                <button class="btn-primary w-full">Kirim untuk persetujuan</button>
            </form>
        </section>
        @else
        <section class="card bg-slate-50"><h2 class="font-black">Refund selesai</h2><p class="mt-1 text-sm text-slate-500">Seluruh nilai transaksi sudah direfund.</p></section>
        @endif
    </aside>
</div>
@endsection
