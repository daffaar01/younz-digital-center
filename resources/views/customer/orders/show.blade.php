@extends('layouts.public')
@section('content')
<main class="min-h-[70vh] bg-[#f3f7f5] px-4 py-10 sm:px-6 sm:py-14">
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('customer.dashboard') }}" class="text-sm font-bold text-[#006c49] hover:underline">← Kembali ke akun</a>
        <section class="mt-5 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col gap-5 bg-[#102720] p-6 text-white sm:flex-row sm:items-end sm:justify-between sm:p-8">
                <div><p class="font-mono text-[10px] tracking-[.16em] text-emerald-300 uppercase">Detail Pesanan</p><h1 class="mt-2 font-mono text-2xl font-bold sm:text-3xl">{{ $order->order_number }}</h1><p class="mt-2 text-sm text-emerald-50/60">Dibuat {{ $order->created_at->translatedFormat('d F Y, H:i') }}</p></div>
                <span class="inline-flex w-fit rounded-full bg-[#cbf75e] px-4 py-2 text-xs font-black text-[#132016]">{{ $order->status->label() }}</span>
            </header>

            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_320px]">
                <div>
                    <h2 class="font-display text-xl font-extrabold text-[#132016]">Ringkasan layanan</h2>
                    <dl class="mt-5 grid gap-4 rounded-2xl bg-slate-50 p-5 sm:grid-cols-2">
                        <div><dt class="text-xs font-bold text-slate-400 uppercase">Layanan</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $order->service?->name ?? ucfirst($order->type) }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-400 uppercase">Nama pemesan</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $order->customer_name }}</dd></div>
                        @if($order->estimated_price !== null)<div><dt class="text-xs font-bold text-slate-400 uppercase">Estimasi</dt><dd class="mt-1 text-sm font-semibold text-slate-800">Rp {{ number_format($order->estimated_price, 0, ',', '.') }}</dd></div>@endif
                        @if($order->final_price !== null)<div><dt class="text-xs font-bold text-slate-400 uppercase">Harga akhir</dt><dd class="mt-1 text-sm font-semibold text-slate-800">Rp {{ number_format($order->final_price, 0, ',', '.') }}</dd></div>@endif
                        <div><dt class="text-xs font-bold text-slate-400 uppercase">Terbayar</dt><dd class="mt-1 text-sm font-semibold text-slate-800">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</dd></div>
                    </dl>

                    @if($order->estimated_price !== null || $order->final_price !== null)
                        <a href="{{ route('customer.orders.invoice', $order) }}" class="public-btn-primary mt-4 min-h-11">Lihat invoice</a>
                    @endif

                    @if($order->status === \App\Enums\ServiceOrderStatus::AwaitingCustomer)
                        <section class="mt-6 rounded-2xl border border-lime-300 bg-[#f5ffd8] p-5">
                            <h3 class="font-display text-lg font-extrabold text-[#132016]">Konfirmasi estimasi</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Periksa layanan dan estimasi di atas. Persetujuan akan melanjutkan pesanan ke tahap pembayaran.</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <form method="post" action="{{ route('customer.orders.estimate.accept', $order) }}">@csrf<button class="public-btn-dark min-h-12 w-full">Setujui estimasi</button></form>
                                <form method="post" action="{{ route('customer.orders.estimate.reject', $order) }}" class="grid gap-2"><label for="reject-reason" class="sr-only">Alasan penolakan</label><input id="reject-reason" name="reason" placeholder="Alasan penolakan" minlength="5" required><button class="min-h-11 rounded-xl border border-red-200 px-4 text-sm font-bold text-red-700 hover:bg-red-50">Tolak dan batalkan</button></form>
                            </div>
                        </section>
                    @endif

                    @if($order->status === \App\Enums\ServiceOrderStatus::AwaitingPayment)
                        <section class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <h3 class="font-display text-lg font-extrabold text-[#132016]">Konfirmasi pembayaran</h3>
                            @if($order->payment_confirmation_at)<p class="mt-2 text-sm text-emerald-800">Konfirmasi sudah dikirim {{ $order->payment_confirmation_at->diffForHumans() }} dan sedang diverifikasi operator.</p>@endif
                            <form method="post" action="{{ route('customer.orders.payment.confirm', $order) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<div><label for="payment-method">Metode</label><select id="payment-method" name="method" class="mt-2 w-full" required><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="cash">Tunai</option><option value="other">Lainnya</option></select></div><div><label for="payment-reference">Referensi (opsional)</label><input id="payment-reference" name="reference" class="mt-2 w-full" maxlength="100" placeholder="Nomor referensi"></div><button class="public-btn-dark min-h-12 sm:col-span-2">Kirim konfirmasi</button></form>
                        </section>
                    @endif

                    @if($order->files->isNotEmpty())
                        @if($canDownloadResults)
                            <section class="mt-6"><h3 class="text-sm font-extrabold text-[#132016]">File hasil</h3><div class="mt-3 grid gap-2">@foreach($order->files as $file)<a href="{{ URL::temporarySignedRoute('customer.orders.results.download', now()->addMinutes(10), [$order, $file]) }}" class="flex min-h-12 items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-900"><span class="truncate">{{ $file->original_name }}</span><span>Unduh</span></a>@endforeach</div></section>
                        @else
                            <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5"><h3 class="text-sm font-extrabold text-amber-950">File hasil belum dapat diunduh</h3><p class="mt-2 text-xs leading-5 text-amber-900">Unduhan dibuka setelah pembayaran terverifikasi lunas dan pesanan berstatus siap atau selesai.</p></section>
                        @endif
                    @endif

                    @if($order->status === \App\Enums\ServiceOrderStatus::Ready)
                        <section class="mt-6 rounded-2xl border border-slate-200 p-5"><h3 class="font-display text-lg font-extrabold text-[#132016]">Perlu revisi?</h3><p class="mt-2 text-xs leading-5 text-slate-500">Sisa kesempatan revisi: {{ max(0, config('services.orders.max_revision_requests', 3) - $order->revision_requests) }}.</p><form method="post" action="{{ route('customer.orders.revision.request', $order) }}" class="mt-4 grid gap-3">@csrf<label for="revision-notes">Catatan revisi</label><textarea id="revision-notes" name="notes" rows="3" minlength="5" maxlength="1000" required></textarea><button class="public-btn-dark min-h-12">Kirim permintaan revisi</button></form></section>
                    @endif

                    @if($order->notes)
                        <div class="mt-6"><h3 class="text-sm font-extrabold text-[#132016]">Catatan</h3><p class="mt-2 whitespace-pre-line rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600">{{ $order->notes }}</p></div>
                    @endif

                    @if($order->specifications)
                        <div class="mt-6"><h3 class="text-sm font-extrabold text-[#132016]">Spesifikasi</h3><dl class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 px-4">@foreach($order->specifications as $key => $value)@if(filled($value))<div class="flex justify-between gap-4 py-3 text-sm"><dt class="text-slate-500">{{ str($key)->replace('_', ' ')->title() }}</dt><dd class="text-right font-semibold text-slate-800">{{ is_array($value) ? implode(', ', $value) : $value }}</dd></div>@endif @endforeach</dl></div>
                    @endif
                </div>

                <aside>
                    <h2 class="font-display text-xl font-extrabold text-[#132016]">Perjalanan pesanan</h2>
                    <ol class="mt-5 space-y-0">
                        @foreach($order->statusHistories as $history)
                            @php($historyStatus = \App\Enums\ServiceOrderStatus::tryFrom($history->to_status))
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                @unless($loop->last)<span class="absolute top-5 bottom-0 left-[9px] w-px bg-emerald-200"></span>@endunless
                                <span class="relative mt-1 h-5 w-5 shrink-0 rounded-full border-4 border-emerald-100 bg-[#006c49]"></span>
                                <div><p class="text-sm font-bold text-slate-800">{{ $historyStatus?->label() ?? str($history->to_status)->replace('_', ' ')->title() }}</p><p class="mt-1 text-xs text-slate-400">{{ $history->created_at->translatedFormat('d M Y, H:i') }}</p>@if($history->notes)<p class="mt-2 text-xs leading-5 text-slate-500">{{ $history->notes }}</p>@endif</div>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            </div>
        </section>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 p-5 text-sm text-emerald-900"><p>Butuh bantuan mengenai pesanan ini?</p><a href="https://wa.me/{{ config('services.whatsapp.number') }}?text={{ urlencode('Halo Younz, saya ingin bertanya tentang pesanan '.$order->order_number) }}" target="_blank" rel="noopener noreferrer" class="font-bold hover:underline">Hubungi WhatsApp →</a></div>
    </div>
</main>
@endsection
