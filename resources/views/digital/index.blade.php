@extends('layouts.app')
@section('content')
<div>
    <p class="text-sm font-bold text-brand-700">PPOB MANUAL</p>
    <h1 class="mt-1 text-3xl font-black text-ink-900">Transaksi digital</h1>
    <p class="mt-1 text-sm text-slate-500">Nomor tujuan wajib diketik dua kali. Setiap transaksi diproteksi idempotency dan harus disetujui owner/admin.</p>
</div>
<details class="card mt-6">
    <summary class="cursor-pointer font-black">+ Ajukan transaksi digital</summary>
    <form method="post" action="{{ route('digital.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
        <div class="grid gap-1"><label>Jenis</label><select name="type">@foreach(['pulsa','paket_data','ewallet','voucher_game','token_pln','pln_pascabayar','pdam','internet'] as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></div>
        <div class="grid gap-1"><label>Provider</label><input name="provider"></div>
        <div class="grid gap-1"><label>Nomor tujuan</label><input name="destination" required></div>
        <div class="grid gap-1"><label>Konfirmasi nomor</label><input name="destination_confirmation" required></div>
        <div class="grid gap-1"><label>Nominal</label><input name="nominal" type="number" min="0" value="0" required></div>
        <div class="grid gap-1"><label>Harga modal</label><input name="cost_price" type="number" min="0" required></div>
        <div class="grid gap-1"><label>Harga jual</label><input name="selling_price" type="number" min="0" required></div>
        <div class="grid gap-1"><label>Biaya admin</label><input name="admin_fee" type="number" min="0" value="0"></div>
        <div class="grid gap-1"><label>Status setelah disetujui</label><select name="status"><option value="diproses">Diproses</option><option value="berhasil">Berhasil</option><option value="gagal">Gagal</option></select></div>
        <div class="grid gap-1"><label>Referensi provider</label><input name="provider_reference"></div>
        <div class="flex items-end"><button class="btn-primary">Ajukan transaksi</button></div>
    </form>
</details>
<section class="mt-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-brand-700">DIGIFLAZZ + MIDTRANS</p>
            <h2 class="mt-1 text-xl font-black text-ink-900">Top up otomatis</h2>
            <p class="mt-1 text-sm text-slate-500">Transaksi dari web dan WhatsApp. Nomor tujuan ditampilkan dalam bentuk tersamarkan.</p>
        </div>
        <span class="badge">{{ $topupOrders->total() }} transaksi</span>
    </div>
    <div class="table-wrap mt-4">
        <table class="data-table">
            <thead><tr><th>Transaksi</th><th>Produk</th><th>Tujuan</th><th>Nilai</th><th>Laba</th><th>Pembayaran</th><th>Provider</th></tr></thead>
            <tbody>
            @forelse($topupOrders as $order)
                <tr>
                    <td><strong>{{ $order->order_number }}</strong><p class="text-xs text-slate-500">{{ $order->created_at->format('d/m/Y H:i') }} · {{ str_starts_with((string) $order->source_reference, 'whatsapp:') ? 'WhatsApp' : 'Web' }}</p></td>
                    <td><strong>{{ $order->product_name }}</strong><p class="text-xs text-slate-500">{{ $order->category }} · {{ $order->brand }}</p></td>
                    <td class="font-mono">{{ $order->maskedDestination() }}</td>
                    <td>Rp {{ number_format($order->total_amount,0,',','.') }}<p class="text-xs text-slate-500">Modal Rp {{ number_format($order->cost_price,0,',','.') }}</p></td>
                    <td class="font-bold text-emerald-700">Rp {{ number_format($order->total_amount-$order->cost_price,0,',','.') }}</td>
                    <td><span class="badge">{{ $order->payment_status->label() }}</span></td>
                    <td><span class="badge">{{ $order->fulfillment_status->label() }}</span><p class="mt-1 text-xs text-slate-500">{{ $order->provider_rc ? 'RC '.$order->provider_rc : ($order->midtrans_status ?? '-') }}</p></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-500">Belum ada transaksi top up otomatis.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $topupOrders->appends(request()->except('topup_page'))->links() }}</div>
</section>

<section class="mt-8">
    <div>
        <p class="text-sm font-bold text-brand-700">PPOB MANUAL</p>
        <h2 class="mt-1 text-xl font-black text-ink-900">Transaksi yang dicatat pegawai</h2>
    </div>
</section>
<div class="table-wrap mt-4">
    <table class="data-table">
        <thead><tr><th>Transaksi</th><th>Tujuan</th><th>Harga</th><th>Laba</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            <tr><td><strong>{{ $transaction->transaction_number }}</strong><p class="text-xs text-slate-500">{{ str($transaction->type)->replace('_',' ')->title() }} · {{ $transaction->provider }}</p></td><td class="font-mono">{{ $transaction->destination }}</td><td>Rp {{ number_format($transaction->selling_price+$transaction->admin_fee,0,',','.') }}</td><td class="font-bold text-emerald-700">Rp {{ number_format($transaction->profit,0,',','.') }}</td><td><span class="badge">{{ str($transaction->status->value)->replace('_',' ')->title() }}</span></td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-slate-500">Belum ada transaksi digital.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $transactions->appends(request()->except('page'))->links() }}</div>
@endsection
