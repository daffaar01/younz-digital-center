<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Struk {{ $order->order_number }} · Younz Digital Center</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef2f0;color:#111827;font:11px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace}.paper{width:80mm;min-height:120mm;margin:20px auto;background:#fff;padding:7mm 6mm;box-shadow:0 18px 55px -38px #071a16}.center{display:grid;justify-items:center;gap:2px;text-align:center}.store strong{font-size:15px;letter-spacing:.06em}.store span,.footer span{color:#4b5563;font-size:9px}.divider{margin:9px 0;border-top:1px dashed #111827}.heading strong{font-size:12px;letter-spacing:.08em}.status{margin-top:3px;border:1px solid #111827;padding:2px 8px;font-size:10px;font-weight:800}.row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:8px;margin:3px 0}.row span:last-child{overflow-wrap:anywhere;text-align:right}.total{margin-top:6px;border-top:1px solid #111827;padding-top:6px;font-size:14px;font-weight:900}.item>strong{display:block;font-size:12px}.item>small{display:block;margin-bottom:6px;color:#4b5563}.token{display:grid;gap:4px;margin:10px 0;border:1px dashed #111827;padding:7px;text-align:center}.token strong{overflow-wrap:anywhere;font-size:13px}.actions{display:flex;justify-content:center;gap:8px;margin:18px}.actions button,.actions a{border:0;border-radius:8px;background:#047857;color:#fff;padding:10px 14px;text-decoration:none;font:600 13px sans-serif;cursor:pointer}.actions a{background:#334155}@media print{body{background:#fff}.paper{min-height:0;margin:0;padding:4mm;box-shadow:none}.actions{display:none}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
@php
    $offline = str_starts_with((string) $order->source_reference, 'offline:');
    $whatsapp = str_starts_with((string) $order->source_reference, 'whatsapp:');
    $payment = $offline ? 'TUNAI' : strtoupper(str_replace('_', ' ', $order->midtrans_payment_type ?: 'MIDTRANS'));
    $source = $offline ? 'Transaksi toko' : ($whatsapp ? 'WhatsApp' : 'Website');
@endphp
<main class="paper">
    <header class="center store">
        <strong>YOUNZ DIGITAL CENTER</strong>
        <span>{{ config('services.store.address') }}</span>
        <span>WhatsApp {{ config('services.whatsapp.display_number') }}</span>
    </header>
    <div class="divider"></div>
    <section class="center heading"><strong>STRUK PEMBAYARAN</strong><span class="status">{{ strtoupper($order->payment_status->label()) }}</span></section>
    <div class="divider"></div>
    <section>
        <div class="row"><span>No. transaksi</span><span>{{ $order->order_number }}</span></div>
        <div class="row"><span>Tanggal bayar</span><span>{{ ($order->paid_at ?? $order->created_at)->format('d/m/Y H:i:s') }} WIB</span></div>
        <div class="row"><span>Kanal</span><span>{{ $source }}</span></div>
        <div class="row"><span>Pembayaran</span><span>{{ $payment }}</span></div>
        @if(!$offline && $order->midtrans_transaction_id)<div class="row"><span>Ref. pembayaran</span><span>{{ $order->midtrans_transaction_id }}</span></div>@endif
    </section>
    <div class="divider"></div>
    <section class="item">
        <strong>{{ $order->product_name }}</strong>
        <small>{{ collect([$order->category, $order->brand, $order->sku])->filter()->join(' · ') }}</small>
        <div class="row"><span>Tujuan</span><span>{{ $order->maskedDestination() }}</span></div>
        <div class="row"><span>Pemesan</span><span>{{ $order->customer_name }}</span></div>
        @if($order->provider_customer_name)<div class="row"><span>Nama pelanggan</span><span>{{ $order->provider_customer_name }}</span></div>@endif
        <div class="row"><span>Status layanan</span><span>{{ $order->fulfillment_status->label() }}</span></div>
    </section>
    <div class="divider"></div>
    <section>
        <div class="row"><span>Subtotal</span><span>Rp {{ number_format($order->selling_price, 0, ',', '.') }}</span></div>
        @if($order->admin_fee > 0)<div class="row"><span>Biaya admin</span><span>Rp {{ number_format($order->admin_fee, 0, ',', '.') }}</span></div>@endif
        <div class="row total"><span>TOTAL</span><span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span></div>
    </section>
    @if($order->serial_number)<section class="token"><span>NOMOR SERI / TOKEN</span><strong>{{ $order->serial_number }}</strong></section>@endif
    @if($order->digiflazz_reference || $order->provider_rc)
        <section>
            @if($order->digiflazz_reference)<div class="row"><span>Ref. provider</span><span>{{ $order->digiflazz_reference }}</span></div>@endif
            @if($order->provider_rc)<div class="row"><span>Kode provider</span><span>{{ $order->provider_rc }}</span></div>@endif
        </section>
    @endif
    <div class="divider"></div>
    <footer class="center footer"><strong>{{ $order->fulfillment_status->label() }}</strong><span>Terima kasih telah bertransaksi.</span><span>Simpan struk ini sebagai bukti pembayaran.</span></footer>
</main>
<div class="actions"><button type="button" onclick="window.print()">Cetak struk</button><a href="{{ $order->temporarySignedUrl('topup.show') }}">Kembali</a></div>
</body>
</html>
