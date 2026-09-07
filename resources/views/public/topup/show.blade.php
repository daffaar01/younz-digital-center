@extends('layouts.public')

@php
    $title = 'Status '.$order->order_number.' · Younz Digital Center';
    $description = 'Pantau status pembayaran dan pemrosesan top up Anda.';
    $isSuccess = $order->fulfillment_status === \App\Enums\TopupFulfillmentStatus::Success;
    $isFailed = $order->fulfillment_status === \App\Enums\TopupFulfillmentStatus::Failed;
    $isPaid = $order->payment_status === \App\Enums\TopupPaymentStatus::Paid;
    $isPendingPayment = $order->payment_status === \App\Enums\TopupPaymentStatus::Pending;
@endphp

@push('head')<meta name="robots" content="noindex,nofollow,noarchive">@endpush

@section('content')
<main class="min-h-[70vh] bg-[#eef8f3] px-4 py-12 sm:px-6 lg:py-16">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div><p class="public-label">Status Top Up</p><h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-[#132016] sm:text-4xl">{{ $order->order_number }}</h1></div>
            <a href="{{ $order->temporarySignedUrl('topup.show') }}" class="btn-secondary">Perbarui status</a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_310px]">
            <section class="overflow-hidden rounded-[1.75rem] border border-emerald-900/10 bg-white shadow-sm">
                <div class="p-6 sm:p-8 {{ $isSuccess ? 'bg-emerald-600 text-white' : ($isFailed ? 'bg-red-50 text-red-950' : 'bg-[#132016] text-white') }}">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/15 text-2xl">{{ $isSuccess ? '✓' : ($isFailed ? '!' : '↻') }}</span>
                        <div><p class="text-xs font-bold opacity-70">{{ $order->payment_status->label() }}</p><h2 class="mt-1 font-display text-2xl font-extrabold">{{ $order->fulfillment_status->label() }}</h2><p class="mt-2 text-sm leading-6 opacity-75">@if($isSuccess)Produk digital telah dikirim ke tujuan Anda.@elseif($isFailed)Transaksi membutuhkan pemeriksaan operator. Simpan nomor pesanan ini.@elseif($isPaid)Pembayaran diterima dan transaksi sedang diteruskan ke provider.@else Selesaikan pembayaran sebelum batas waktu berakhir.@endif</p></div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold text-slate-400">Produk</dt><dd class="mt-1 font-bold text-[#132016]">{{ $order->product_name }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Jenis transaksi</dt><dd class="mt-1 font-bold text-[#132016]">{{ $order->transaction_type->label() }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Tujuan</dt><dd class="mt-1 font-mono font-bold text-[#132016]">{{ $order->maskedDestination() }}</dd></div>
                        @if($order->provider_customer_name)
                            <div><dt class="text-xs font-semibold text-slate-400">Nama pelanggan tagihan</dt><dd class="mt-1 font-bold text-[#132016]">{{ $order->provider_customer_name }}</dd></div>
                        @endif
                        @if($order->transaction_type === \App\Enums\DigiflazzTransactionType::Postpaid && $order->selling_price > 0)
                            <div><dt class="text-xs font-semibold text-slate-400">Nominal tagihan provider</dt><dd class="mt-1 font-bold text-[#132016]">Rp {{ number_format($order->selling_price, 0, ',', '.') }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-400">Biaya admin</dt><dd class="mt-1 font-bold text-[#132016]">Rp {{ number_format($order->admin_fee, 0, ',', '.') }}</dd></div>
                        @endif
                        <div><dt class="text-xs font-semibold text-slate-400">Total pembayaran</dt><dd class="mt-1 text-lg font-extrabold text-[#006c49]">{{ $order->total_amount > 0 ? 'Rp '.number_format($order->total_amount, 0, ',', '.') : 'Menunggu cek tagihan' }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Dibuat</dt><dd class="mt-1 font-semibold text-[#132016]">{{ $order->created_at->format('d M Y, H:i') }} WIB</dd></div>
                    </dl>

                    @if($order->serial_number && $isSuccess)
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-semibold text-emerald-700">Nomor seri / token</p><p class="mt-2 break-all font-mono text-sm font-bold text-emerald-950">{{ $order->serial_number }}</p></div>
                    @endif

                    @if($order->paid_at)
                        <div class="mt-6 grid gap-3 border-t border-slate-200 pt-6 sm:grid-cols-2">
                            <a href="{{ $order->temporarySignedUrl('topup.receipt') }}" target="_blank" rel="noopener noreferrer" class="btn-secondary min-h-12">Cetak struk</a>
                            <a href="{{ $order->temporarySignedUrl('topup.invoice') }}" target="_blank" rel="noopener noreferrer" class="public-btn-dark min-h-12">Cetak invoice</a>
                        </div>
                    @endif

                    @if($isPendingPayment)
                        @if(filled($order->midtrans_redirect_url))
                            <a href="{{ $order->midtrans_redirect_url }}" class="public-btn-primary mt-7 w-full">Buka pembayaran Midtrans <span>→</span></a>
                            <p class="mt-2 text-center text-xs leading-5 text-slate-500">Anda akan diarahkan ke halaman pembayaran resmi Midtrans.</p>
                        @else
                            <form method="post" action="{{ $order->temporarySignedUrl('topup.pay') }}" class="mt-7">@csrf<button type="submit" class="public-btn-primary w-full">Buat pembayaran Midtrans <span>→</span></button></form>
                        @endif
                    @elseif($isFailed)
                        <a href="https://wa.me/{{ config('services.whatsapp.number') }}?text={{ urlencode('Mohon periksa transaksi top up '.$order->order_number) }}" target="_blank" rel="noopener noreferrer" class="public-btn-dark mt-7 w-full">Hubungi operator</a>
                    @endif
                </div>
            </section>

            <aside class="rounded-[1.75rem] border border-emerald-900/10 bg-white p-6 shadow-sm">
                <p class="public-label">Alur transaksi</p>
                <ol class="mt-6 space-y-5">
                    @foreach([
                        ['Pembayaran', $isPaid || $isSuccess, $order->payment_status->label()],
                        ['Pemrosesan provider', in_array($order->fulfillment_status, [\App\Enums\TopupFulfillmentStatus::Processing, \App\Enums\TopupFulfillmentStatus::ProviderPending, \App\Enums\TopupFulfillmentStatus::Success], true), $order->fulfillment_status->label()],
                        ['Selesai', $isSuccess, $isSuccess ? 'Berhasil dikirim' : 'Menunggu'],
                    ] as [$label, $done, $detail])
                        <li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold {{ $done ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400' }}">{{ $done ? '✓' : '·' }}</span><div><p class="text-sm font-bold text-[#132016]">{{ $label }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $detail }}</p></div></li>
                    @endforeach
                </ol>
                <div class="mt-7 rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-500">Simpan tautan halaman ini. Demi privasi, nomor tujuan ditampilkan dalam bentuk tersamarkan.</div>
                <a href="{{ route('topup.index') }}" class="mt-5 inline-flex min-h-11 items-center text-sm font-bold text-[#006c49]">← Kembali ke katalog</a>
            </aside>
        </div>
    </div>
</main>
@endsection
