@extends('layouts.public')
@section('content')
<main class="min-h-[70vh] bg-[#f3f7f5]">
    <section class="relative overflow-hidden bg-[#102720] text-white">
        <div class="absolute inset-0 landing-hero-grid"></div>
        <div class="relative mx-auto flex max-w-7xl flex-col gap-7 px-4 py-12 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:py-16">
            <div>
                <p class="font-mono text-[11px] tracking-[.16em] text-emerald-300 uppercase">Akun Pelanggan</p>
                <h1 class="mt-3 font-display text-4xl font-extrabold tracking-tight sm:text-5xl">Halo, {{ Str::before($customer->name, ' ') }}.</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-emerald-50/65">Pantau semua pesanan yang dibuat saat Anda masuk ke akun ini.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('public.order') }}" class="landing-button-bright min-h-12">+ Buat pesanan</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="landing-button-ghost min-h-12">Keluar</button></form>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan pesanan">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-xs font-bold tracking-wider text-slate-400 uppercase">Semua pesanan</p><p class="mt-3 font-display text-4xl font-extrabold text-[#132016]">{{ $totalOrders }}</p></article>
            <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6"><p class="text-xs font-bold tracking-wider text-emerald-700 uppercase">Sedang diproses</p><p class="mt-3 font-display text-4xl font-extrabold text-[#006c49]">{{ $activeOrders }}</p></article>
            <article class="rounded-3xl border border-lime-200 bg-[#f5ffd8] p-6"><p class="text-xs font-bold tracking-wider text-lime-800 uppercase">Selesai</p><p class="mt-3 font-display text-4xl font-extrabold text-[#132016]">{{ $completedOrders }}</p></article>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_280px]">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-5 sm:px-7">
                    <div><h2 class="font-display text-xl font-extrabold text-[#132016]">Pesanan terbaru</h2><p class="mt-1 text-xs text-slate-400">Klik pesanan untuk melihat detail progres.</p></div>
                    <a href="{{ route('public.order') }}" class="text-sm font-bold text-[#006c49] hover:underline">Pesan baru</a>
                </div>

                @forelse($orders as $order)
                    @php
                        $tone = match($order->status) {
                            \App\Enums\ServiceOrderStatus::Completed => 'bg-emerald-100 text-emerald-800',
                            \App\Enums\ServiceOrderStatus::Cancelled => 'bg-red-100 text-red-700',
                            \App\Enums\ServiceOrderStatus::Ready => 'bg-lime-100 text-lime-800',
                            \App\Enums\ServiceOrderStatus::InProgress, \App\Enums\ServiceOrderStatus::Queued => 'bg-blue-100 text-blue-800',
                            default => 'bg-amber-100 text-amber-800',
                        };
                    @endphp
                    <a href="{{ route('customer.orders.show', $order) }}" class="group grid gap-3 border-b border-slate-100 px-5 py-5 transition last:border-0 hover:bg-emerald-50/50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-7">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><strong class="font-mono text-sm text-[#132016]">{{ $order->order_number }}</strong><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $tone }}">{{ $order->status->label() }}</span></div>
                            <p class="mt-2 truncate text-sm font-semibold text-slate-700">{{ $order->service?->name ?? ucfirst($order->type) }}</p>
                            <p class="mt-1 text-xs text-slate-400">Dibuat {{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <span class="text-sm font-bold text-[#006c49] transition group-hover:translate-x-1">Lihat detail →</span>
                    </a>
                @empty
                    <div class="px-6 py-16 text-center">
                        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-2xl">✦</div>
                        <h3 class="mt-5 font-display text-xl font-extrabold text-[#132016]">Belum ada pesanan</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Pesanan baru yang dibuat saat Anda masuk akan otomatis tampil di sini.</p>
                        <a href="{{ route('public.order') }}" class="public-btn-dark mt-6">Buat pesanan pertama</a>
                    </div>
                @endforelse

                @if($orders->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $orders->links() }}</div>@endif
            </div>

            <aside class="space-y-4">
                <div class="rounded-3xl bg-[#cbf75e] p-6 text-[#132016]">
                    <p class="text-xs font-black tracking-wider uppercase">Profil pemesan</p>
                    <p class="mt-4 font-display text-lg font-extrabold">{{ $customer->name }}</p>
                    @if(filled($customer->email))<p class="mt-1 break-all text-xs opacity-70">{{ $customer->email }}</p>@endif
                    <p class="mt-1 text-xs opacity-70">WA {{ $customer->phone }}</p>
                    <p class="mt-5 text-xs leading-5 opacity-70">Nama dan WhatsApp ini dipakai otomatis pada pesanan baru.</p>
                    <a href="{{ route('customer.profile.edit') }}" class="mt-4 inline-flex text-xs font-black underline decoration-[#132016]/30 underline-offset-4">Ubah profil</a>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="font-display font-extrabold text-[#132016]">Pesanan lama belum tampil?</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Pesanan yang dibuat sebelum memiliki akun tetap dapat dicek memakai nomor pesanan dan WhatsApp.</p>
                    <a href="{{ route('public.my-orders') }}" class="mt-4 inline-flex text-sm font-bold text-[#006c49] hover:underline">Cek pesanan lama →</a>
                </div>
            </aside>
        </section>
    </div>
</main>
@endsection
