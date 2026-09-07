@extends('layouts.public')
@section('content')
<main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <p class="text-sm font-bold text-brand-700">PELACAKAN</p><h1 class="mt-2 text-4xl font-black text-ink-900">Cek status pesanan</h1>
    <form method="post" action="{{ route('public.track.lookup') }}" class="card mt-8 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        @csrf
        <div class="grid gap-1.5"><label>Nomor pesanan</label><input name="order_number" value="{{ old('order_number') }}" placeholder="ORD-20260720-0001" required></div>
        <div class="grid gap-1.5"><label>Nomor WhatsApp</label><input name="phone" value="{{ old('phone') }}" required></div>
        <button class="btn-primary">Periksa</button>
    </form>
    @if($attempted)
        @if($order)
        <section class="card mt-6"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm text-slate-500">{{ $order->order_number }}</p><h2 class="text-2xl font-black text-ink-900">{{ ucfirst($order->type) }}</h2></div><span class="badge bg-emerald-100 text-emerald-800">{{ $order->status->label() }}</span></div><div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3"><div><p class="text-xs font-bold uppercase text-slate-400">Estimasi</p><p class="mt-1 font-bold">{{ $order->estimated_price ? 'Rp '.number_format($order->estimated_price,0,',','.') : 'Diperiksa operator' }}</p></div><div><p class="text-xs font-bold uppercase text-slate-400">Deadline</p><p class="mt-1 font-bold">{{ $order->deadline_at?->format('d M Y H:i') ?? 'Belum ditentukan' }}</p></div><div><p class="text-xs font-bold uppercase text-slate-400">Terakhir diperbarui</p><p class="mt-1 font-bold">{{ $order->updated_at->diffForHumans() }}</p></div></div></section>
        @else<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-800">Pesanan tidak ditemukan. Periksa nomor dan WhatsApp.</div>@endif
    @endif
</main>
@endsection
