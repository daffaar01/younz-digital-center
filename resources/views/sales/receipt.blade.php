<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Struk {{ $sale->invoice_number }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#171717;font:12px ui-monospace,SFMono-Regular,Consolas,monospace}.receipt{width:80mm;min-height:100mm;margin:20px auto;background:#fff;padding:7mm}.center{text-align:center}.bold{font-weight:700}.muted{color:#525252}.line{border-top:1px dashed #404040;margin:10px 0}.row{display:flex;justify-content:space-between;gap:12px;margin:4px 0}.row span:last-child{text-align:right}.item{margin:9px 0}.item-name{font-weight:700;overflow-wrap:anywhere}.item-detail{display:flex;justify-content:space-between;gap:12px;margin-top:3px}.total{font-size:14px;font-weight:700;margin-top:7px}.payment{margin:5px 0}.footer{line-height:1.6}.actions{display:flex;justify-content:center;gap:8px;margin:18px}.actions button,.actions a{border:0;border-radius:8px;background:#047857;color:#fff;padding:10px 14px;text-decoration:none;font:600 13px sans-serif;cursor:pointer}.actions a{background:#334155}@media print{body{background:#fff}.receipt{margin:0;padding:4mm}.actions{display:none}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
<main class="receipt">
    <header class="center">
        <div class="bold" style="font-size:16px;letter-spacing:.03em">YOUNZ DIGITAL CENTER</div>
        <div>Print · ATK · Pulsa · Digital</div>
        <div class="muted">{{ config('services.store.address') }}</div>
        <div class="muted">WA {{ config('services.whatsapp.display_number') }}</div>
    </header>
    <div class="line"></div>
    <div class="center bold">STRUK PENJUALAN</div>
    <div class="line"></div>
    <div class="row"><span>No. transaksi</span><span class="bold">{{ $sale->invoice_number }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ $sale->completed_at?->format('d/m/Y H:i:s') }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $sale->user->name }}</span></div>
    @if($sale->customer)<div class="row"><span>Pelanggan</span><span>{{ $sale->customer->name }}</span></div>@endif
    <div class="line"></div>
    @foreach($sale->items as $item)
        <div class="item">
            <div class="item-name">{{ $item->name }}</div>
            <div class="item-detail"><span>{{ $item->quantity }} × Rp {{ number_format($item->unit_price, 0, ',', '.') }}</span><span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span></div>
            @if($item->discount)<div class="muted">Diskon item: -Rp {{ number_format($item->discount, 0, ',', '.') }}</div>@endif
        </div>
    @endforeach
    <div class="line"></div>
    <div class="row"><span>Subtotal</span><span>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</span></div>
    @if($sale->discount)<div class="row"><span>Diskon</span><span>-Rp {{ number_format($sale->discount, 0, ',', '.') }}</span></div>@endif
    <div class="row total"><span>TOTAL</span><span>Rp {{ number_format($sale->total, 0, ',', '.') }}</span></div>
    <div class="line"></div>
    <div class="bold">PEMBAYARAN</div>
    @foreach($sale->payments as $payment)<div class="row payment"><span>{{ strtoupper($payment->method) }}</span><span>Rp {{ number_format($payment->amount, 0, ',', '.') }}</span></div>@endforeach
    @if($sale->refunds->isNotEmpty())
        <div class="line"></div>
        <div class="bold">PENGEMBALIAN DANA</div>
        @foreach($sale->refunds as $refund)<div class="row"><span>{{ $refund->refund_number }}</span><span>-Rp {{ number_format($refund->amount, 0, ',', '.') }}</span></div>@endforeach
        <div class="row bold"><span>TOTAL BERSIH</span><span>Rp {{ number_format($sale->total - $sale->refunds->sum('amount'), 0, ',', '.') }}</span></div>
    @endif
    <div class="line"></div>
    <div class="center footer">Terima kasih telah berbelanja.<br>Simpan struk ini sebagai bukti transaksi.</div>
    <div class="actions"><button type="button" onclick="window.print()">Cetak struk</button><a href="{{ route('pos.index') }}">Kembali</a></div>
</main>
</body>
</html>
