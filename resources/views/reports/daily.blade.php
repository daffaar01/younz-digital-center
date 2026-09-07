@extends('layouts.app')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-bold text-brand-700">LAPORAN</p><h1 class="mt-1 text-3xl font-black text-ink-900">Ringkasan harian</h1></div><form class="flex items-end gap-2"><div class="grid gap-1"><label>Tanggal</label><input name="date" type="date" value="{{ $date->toDateString() }}"></div><button class="btn-secondary">Tampilkan</button></form></div>
<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">@foreach([['Penjualan kotor',$grossRevenue,'text-emerald-700'],['Refund',$refundsTotal,'text-red-600'],['Pendapatan bersih',$revenue,'text-emerald-700'],['Laba kotor',$grossProfit,'text-sky-700'],['Arus kas bersih',$revenue-$expensesTotal,'text-ink-900']] as [$label,$amount,$color])<div class="card"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black {{ $color }}">Rp {{ number_format($amount,0,',','.') }}</p></div>@endforeach</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2"><section class="table-wrap"><div class="p-5"><h2 class="text-lg font-black">Penjualan ({{ $sales->count() }})</h2></div><table class="data-table"><thead><tr><th>Invoice</th><th>Kasir</th><th>Total</th></tr></thead><tbody>@forelse($sales as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->user->name }}</td><td>Rp {{ number_format($sale->total,0,',','.') }}</td></tr>@empty<tr><td colspan="3">Tidak ada data.</td></tr>@endforelse</tbody></table></section><section class="table-wrap"><div class="p-5"><h2 class="text-lg font-black">Pengeluaran ({{ $expenses->count() }})</h2></div><table class="data-table"><thead><tr><th>Kategori</th><th>Keterangan</th><th>Jumlah</th></tr></thead><tbody>@forelse($expenses as $expense)<tr><td>{{ $expense->category->name }}</td><td>{{ $expense->description }}</td><td>Rp {{ number_format($expense->amount,0,',','.') }}</td></tr>@empty<tr><td colspan="3">Tidak ada data.</td></tr>@endforelse</tbody></table></section></div>
<section class="table-wrap mt-6">
    <div class="p-5"><h2 class="text-lg font-black">Top up otomatis berhasil ({{ $topupOrders->count() }})</h2><p class="mt-1 text-sm text-slate-500">Hanya transaksi lunas dan berhasil di Digiflazz pada tanggal laporan.</p></div>
    <table class="data-table">
        <thead><tr><th>Transaksi</th><th>Produk</th><th>Tujuan</th><th>Sumber</th><th class="text-right">Pendapatan</th><th class="text-right">Laba</th></tr></thead>
        <tbody>
        @forelse($topupOrders as $order)
            <tr>
                <td><strong>{{ $order->order_number }}</strong><p class="text-xs text-slate-500">{{ $order->fulfilled_at?->format('H:i') }}</p></td>
                <td>{{ $order->product_name }}</td>
                <td class="font-mono">{{ $order->maskedDestination() }}</td>
                <td>{{ str_starts_with((string) $order->source_reference, 'whatsapp:') ? 'WhatsApp' : 'Web' }}</td>
                <td class="text-right">Rp {{ number_format($order->total_amount,0,',','.') }}</td>
                <td class="text-right font-bold text-emerald-700">Rp {{ number_format($order->total_amount-$order->cost_price,0,',','.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-slate-500">Tidak ada top up otomatis yang berhasil.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<section class="table-wrap mt-6"><div class="p-5"><h2 class="text-lg font-black">Refund diproses ({{ $refunds->count() }})</h2></div><table class="data-table"><thead><tr><th>Refund</th><th>Invoice</th><th>Penyetuju</th><th>Metode</th><th class="text-right">Nilai</th></tr></thead><tbody>@forelse($refunds as $refund)<tr><td>{{ $refund->refund_number }}</td><td><a class="font-bold text-brand-700" href="{{ route('sales.show',$refund->sale) }}">{{ $refund->sale->invoice_number }}</a></td><td>{{ $refund->approver?->name }}</td><td>{{ strtoupper($refund->method) }}</td><td class="text-right text-red-600">- Rp {{ number_format($refund->amount,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-500">Tidak ada refund.</td></tr>@endforelse</tbody></table></section>
@endsection
