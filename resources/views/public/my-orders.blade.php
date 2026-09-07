@extends('layouts.public')
@section('content')
<main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <p class="text-sm font-bold text-brand-700">PORTAL PELANGGAN</p>
    <h1 class="mt-2 text-4xl font-black text-ink-900">Pesanan Saya</h1>
    <p class="mt-2 text-sm text-slate-500">Untuk menjaga privasi, masukkan nomor pesanan dan nomor WhatsApp yang digunakan saat memesan.</p>
    <form method="post" action="{{ route('public.my-orders.lookup') }}" class="card mt-8 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        @csrf
        <div class="grid gap-1.5"><label>Nomor pesanan</label><input name="order_number" value="{{ $orderNumber }}" placeholder="ORD-20260721-0001" required></div>
        <div class="grid gap-1.5"><label>Nomor WhatsApp</label><input name="phone" value="{{ $phone }}" placeholder="08123456789" required></div>
        <button class="btn-primary">Verifikasi Pesanan</button>
    </form>
    @if($attempted)
        @if($orders->isEmpty())
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-800">Pesanan tidak ditemukan. Periksa kembali kedua data verifikasi.</div>
        @else
            <p class="mt-6 text-sm text-slate-500">Verifikasi berhasil.</p>
            <div class="mt-4 space-y-4">
                @foreach($orders as $order)
                <section class="card">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><p class="text-xs text-slate-400">{{ $order->order_number }}</p>
                            <h2 class="font-display text-lg font-bold text-ink-900">{{ $order->service?->name ?? ucfirst($order->type) }}</h2>
                            <p class="text-xs text-slate-400">{{ $order->customer_name }} · {{ $order->created_at->format('d M Y H:i') }}</p>
                        </div>
                        @php
                            $badgeClass = match($order->status->value) {
                                'selesai' => 'bg-emerald-100 text-emerald-800',
                                'dibatalkan' => 'bg-red-100 text-red-800',
                                'siap_diambil' => 'bg-blue-100 text-blue-800',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $order->status->label() }}</span>
                    </div>
                    <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                        <div><p class="text-xs font-bold uppercase text-slate-400">Estimasi</p><p class="mt-0.5 text-sm font-bold">{{ $order->estimated_price ? 'Rp '.number_format($order->estimated_price,0,',','.') : 'Diperiksa' }}</p></div>
                        <div><p class="text-xs font-bold uppercase text-slate-400">Final</p><p class="mt-0.5 text-sm font-bold">{{ $order->final_price ? 'Rp '.number_format($order->final_price,0,',','.') : 'Belum ditetapkan' }}</p></div>
                        <div><p class="text-xs font-bold uppercase text-slate-400">Deadline</p><p class="mt-0.5 text-sm font-bold">{{ $order->deadline_at?->format('d M Y') ?? 'Belum ditentukan' }}</p></div>
                    </div>
                    <a href="{{ route('public.track.show', $order->public_token) }}" class="mt-3 inline-flex text-xs font-bold text-brand-600 hover:underline">Lihat detail →</a>
                </section>
                @endforeach
            </div>
        @endif
    @endif
</main>
@endsection
