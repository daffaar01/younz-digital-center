@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $canOperateSales = $user->hasRole('owner', 'admin', 'cashier');
    $canManage = $user->hasRole('owner', 'admin');
    $saleStatus = [
        'completed' => ['Selesai', 'bg-emerald-50 text-emerald-700'],
        'partially_refunded' => ['Refund sebagian', 'bg-amber-50 text-amber-700'],
        'refunded' => ['Direfund', 'bg-rose-50 text-rose-700'],
    ];
@endphp

<div class="mx-auto max-w-[1600px]">
    <header class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="font-mono text-[11px] font-medium tracking-[.16em] text-[#006c49] uppercase">Ringkasan hari ini</p>
            <h1 class="mt-2 font-display text-3xl font-extrabold tracking-[-.035em] text-[#131b2e] sm:text-4xl">Selamat datang, {{ str($user->name)->before(' ') }}</h1>
            <p class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                
                {{ now()->translatedFormat('l, d F Y · H:i') }} WIB
            </p>
        </div>
        @if($canOperateSales)
            <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-3 rounded-2xl bg-[#131b2e] px-5 py-3.5 text-sm font-bold text-white shadow-[0_10px_25px_rgba(19,27,46,.2)] transition hover:-translate-y-0.5 hover:bg-[#202a42]">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">
                    
                </span>
                Buka Kasir
            </a>
        @else
            <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-3 rounded-2xl bg-[#131b2e] px-5 py-3.5 text-sm font-bold text-white shadow-[0_10px_25px_rgba(19,27,46,.2)] transition hover:-translate-y-0.5 hover:bg-[#202a42]">
                Lihat Pesanan
            </a>
        @endif
    </header>

    @if($pendingApprovals)
        <a href="{{ route('approvals.index', ['status' => 'pending']) }}" class="group mt-7 flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 transition hover:border-amber-300 sm:flex-row sm:items-center sm:justify-between">
            <span class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700">
                    
                </span>
                <span>
                    <strong class="block font-display text-sm font-extrabold text-amber-900">{{ $pendingApprovals }} permintaan menunggu persetujuan</strong>
                    <span class="mt-0.5 block text-xs leading-5 text-amber-700">Tinjau permintaan refund atau perubahan sensitif sebelum diproses.</span>
                </span>
            </span>
            <span class="inline-flex items-center gap-2 self-start text-xs font-bold text-amber-800 sm:self-auto">Tinjau sekarang <span class="transition-transform group-hover:translate-x-1">→</span></span>
        </a>
    @endif

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik hari ini">
        <article class="app-kpi-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pendapatan</p>
                    <p class="mt-3 font-display text-2xl font-extrabold tracking-tight text-[#131b2e]">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</p>
                </div>
                <span class="app-kpi-icon bg-emerald-50 text-[#006c49]"></span>
            </div>
            <p class="mt-5 flex items-center gap-2 text-xs text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Setelah refund selesai</p>
        </article>

        <article class="app-kpi-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pengeluaran</p>
                    <p class="mt-3 font-display text-2xl font-extrabold tracking-tight text-[#131b2e]">Rp {{ number_format($todayExpenses, 0, ',', '.') }}</p>
                </div>
                <span class="app-kpi-icon bg-rose-50 text-rose-600"></span>
            </div>
            <p class="mt-5 flex items-center gap-2 text-xs text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-rose-300"></span> Tercatat hari ini</p>
        </article>

        <article class="app-kpi-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Transaksi</p>
                    <p class="mt-3 font-display text-2xl font-extrabold tracking-tight text-[#131b2e]">{{ number_format($todayTransactions, 0, ',', '.') }}</p>
                </div>
                <span class="app-kpi-icon bg-sky-50 text-sky-700"></span>
            </div>
            <p class="mt-5 flex items-center gap-2 text-xs text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span> Penjualan terselesaikan</p>
        </article>

        <article class="app-kpi-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pesanan aktif</p>
                    <p class="mt-3 font-display text-2xl font-extrabold tracking-tight text-[#131b2e]">{{ number_format($activeOrders, 0, ',', '.') }}</p>
                </div>
                <span class="app-kpi-icon bg-amber-50 text-amber-700"></span>
            </div>
            <p class="mt-5 flex items-center gap-2 text-xs text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span> Belum selesai / dibatalkan</p>
        </article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(340px,.75fr)]">
        <section class="app-surface min-w-0 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                <div>
                    <h2 class="font-display text-lg font-extrabold text-[#131b2e]">Transaksi terbaru</h2>
                    <p class="mt-1 text-xs text-slate-400">Aktivitas penjualan yang baru diselesaikan</p>
                </div>
                @if($canOperateSales)
                    <a href="{{ route('sales.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-[#006c49] transition hover:text-emerald-800">Lihat semua <span>→</span></a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-left font-mono text-[10px] tracking-[.12em] text-slate-400 uppercase">
                            <th class="px-5 py-3.5 font-medium sm:px-6">Invoice</th>
                            <th class="hidden px-4 py-3.5 font-medium md:table-cell">Waktu</th>
                            <th class="hidden px-4 py-3.5 font-medium lg:table-cell">Kasir</th>
                            <th class="px-4 py-3.5 text-right font-medium">Total</th>
                            <th class="px-5 py-3.5 text-right font-medium sm:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentSales as $sale)
                            @php
                                $meta = $saleStatus[$sale->status] ?? [str($sale->status)->headline(), 'bg-slate-100 text-slate-600'];
                            @endphp
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4 sm:px-6">
                                    @if($canOperateSales)
                                        <a href="{{ route('sales.show', $sale) }}" class="font-mono text-xs font-bold text-[#131b2e] hover:text-[#006c49]">{{ $sale->invoice_number }}</a>
                                    @else
                                        <span class="font-mono text-xs font-bold text-[#131b2e]">{{ $sale->invoice_number }}</span>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-4 text-xs text-slate-500 md:table-cell">{{ $sale->completed_at?->format('H:i') ?? '—' }} WIB</td>
                                <td class="hidden px-4 py-4 text-xs font-medium text-slate-600 lg:table-cell">{{ $sale->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-display text-sm font-extrabold text-[#131b2e]">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right sm:px-6"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-bold {{ $meta[1] }}">{{ $meta[0] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-14 text-center"><span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-slate-400"></span><p class="mt-3 text-sm font-semibold text-slate-500">Belum ada transaksi.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-3xl bg-[#131b2e] p-5 text-white shadow-[0_12px_35px_rgba(19,27,46,.18)] sm:p-6">
            <div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-emerald-400/10 blur-2xl"></div>
            <div class="relative flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[10px] tracking-[.15em] text-emerald-300 uppercase">Monitoring</p>
                    <h2 class="mt-2 font-display text-xl font-extrabold">Panel Perhatian</h2>
                </div>
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/8 text-amber-300"></span>
            </div>

            <div class="relative mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-white/8 bg-white/[.05] p-4">
                    <p class="text-2xl font-extrabold {{ $lateOrders ? 'text-amber-300' : 'text-emerald-300' }}">{{ $lateOrders }}</p>
                    <p class="mt-1 text-[11px] leading-4 text-slate-400">Pesanan melewati deadline</p>
                </div>
                <div class="rounded-2xl border border-white/8 bg-white/[.05] p-4">
                    <p class="text-2xl font-extrabold {{ $failedDigitalTransactions ? 'text-rose-300' : 'text-emerald-300' }}">{{ $failedDigitalTransactions }}</p>
                    <p class="mt-1 text-[11px] leading-4 text-slate-400">Transaksi digital gagal</p>
                </div>
            </div>

            <div class="relative mt-6">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-200">Stok menipis</p>
                    @if($canManage)<a href="{{ route('products.index') }}" class="text-[10px] font-bold text-emerald-300 hover:text-emerald-200">Kelola stok →</a>@endif
                </div>
                <div class="mt-3 space-y-4">
                    @forelse($lowStockProducts->take(5) as $product)
                        @php
                            $stockPercent = min(100, max(4, (int) round(max($product->stock, 0) * 100 / max($product->minimum_stock, 1))));
                            $critical = $product->stock <= 0;
                        @endphp
                        <div>
                            <div class="flex items-end justify-between gap-3 text-[11px]">
                                <span class="min-w-0"><strong class="block truncate text-slate-200">{{ $product->name }}</strong><span class="font-mono text-[9px] text-slate-500">{{ $product->sku }}</span></span>
                                <span class="shrink-0 font-bold {{ $critical ? 'text-rose-300' : 'text-amber-300' }}">{{ $product->stock }} / {{ $product->minimum_stock }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full {{ $critical ? 'bg-rose-400' : 'bg-amber-300' }}" style="width: {{ $stockPercent }}%"></div></div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-emerald-400/15 bg-emerald-400/[.06] p-4 text-xs text-emerald-200">Semua stok berada dalam batas aman.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section class="mt-6">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-base font-extrabold text-[#131b2e]">Akses cepat</h2>
            <span class="font-mono text-[9px] tracking-[.14em] text-slate-400 uppercase">Operasional</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @if($canOperateSales)
                <a href="{{ route('pos.index') }}" class="app-quick-action"><span class="app-quick-icon bg-emerald-50 text-[#006c49]"></span><span><strong>Kasir / POS</strong><small>Mulai transaksi penjualan</small></span><b>→</b></a>
            @endif
            <a href="{{ route('orders.index') }}" class="app-quick-action"><span class="app-quick-icon bg-sky-50 text-sky-700"></span><span><strong>Pesanan Jasa</strong><small>Pantau progres pekerjaan</small></span><b>→</b></a>
            @if($canManage)
                <a href="{{ route('products.index') }}" class="app-quick-action"><span class="app-quick-icon bg-amber-50 text-amber-700"></span><span><strong>Produk & Stok</strong><small>Perbarui persediaan</small></span><b>→</b></a>
                <a href="{{ route('reports.daily') }}" class="app-quick-action"><span class="app-quick-icon bg-violet-50 text-violet-700"></span><span><strong>Laporan Harian</strong><small>Lihat performa usaha</small></span><b>→</b></a>
            @elseif($canOperateSales)
                <a href="{{ route('customers.index') }}" class="app-quick-action"><span class="app-quick-icon bg-violet-50 text-violet-700"></span><span><strong>Pelanggan</strong><small>Kelola data pelanggan</small></span><b>→</b></a>
            @endif
        </div>
    </section>

    <section class="relative mt-6 overflow-hidden rounded-3xl bg-gradient-to-br from-[#006c49] to-[#004d35] p-6 text-white sm:p-8">
        <div class="absolute -right-12 -top-20 h-56 w-56 rounded-full border border-white/10"></div>
        <div class="absolute -right-2 -top-10 h-40 w-40 rounded-full border border-white/10"></div>
        <div class="relative max-w-2xl">
            <p class="font-mono text-[10px] tracking-[.16em] text-emerald-200 uppercase">Efisien & terpusat</p>
            <h2 class="mt-3 font-display text-2xl font-extrabold tracking-tight">Satu panel untuk seluruh operasional.</h2>
            <p class="mt-2 text-sm leading-6 text-emerald-100/80">Data transaksi, pesanan jasa, stok, dan persetujuan tersambung agar keputusan harian lebih cepat dan tetap terkendali.</p>
        </div>
    </section>
</div>
@endsection
