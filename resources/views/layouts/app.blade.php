<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · Younz Digital Center</title>
    <link rel="icon" type="image/webp" href="{{ asset('favicon.webp') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#f7f9fb]">
@php
    $currentUser = auth()->user();
    $canOperateSales = $currentUser->hasRole('owner', 'admin', 'cashier');
    $canManage = $currentUser->hasRole('owner', 'admin');
    $initial = mb_strtoupper(mb_substr(trim($currentUser->name), 0, 1));
@endphp

<div class="min-h-screen bg-[#f7f9fb]">
    <div data-app-sidebar-overlay class="fixed inset-0 z-40 hidden bg-[#131b2e]/60 backdrop-blur-sm lg:hidden"></div>

    <aside data-app-sidebar class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-[#131b2e] text-white transition-transform duration-300 lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-white/8 px-6">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" aria-label="Younz Digital Center">
                <x-brand-logo :inverse="true" :compact="true" />
                <span class="min-w-0">
                    <strong class="block truncate font-display text-base font-extrabold tracking-tight">Younz Digital</strong>
                    <small class="block font-mono text-[9px] tracking-[.16em] text-emerald-300 uppercase">Operational Hub</small>
                </span>
            </a>
            <button type="button" data-app-menu-close class="grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white lg:hidden" aria-label="Tutup menu">
                
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-6">
            <p class="px-3 font-mono text-[9px] font-medium tracking-[.18em] text-slate-500 uppercase">Workspace</p>
            <nav class="mt-3 space-y-1" aria-label="Navigasi utama">
                <x-app-nav-link route="dashboard" label="Dashboard" icon="dashboard" />
                @if($canOperateSales)
                    <x-app-nav-link route="pos.index" label="Kasir / POS" icon="pos" :matches="['pos.*']" />
                    <x-app-nav-link route="sales.index" label="Penjualan" icon="sales" :matches="['sales.*']" />
                    <x-app-nav-link route="customers.index" label="Pelanggan" icon="customers" :matches="['customers.*']" />
                    <x-app-nav-link route="digital.index" label="Transaksi Digital" icon="digital" :matches="['digital.*']" />
                @endif
                <x-app-nav-link route="orders.index" label="Pesanan Jasa" icon="orders" :matches="['orders.*']" />
            </nav>

            @if($canManage)
                <p class="mt-8 px-3 font-mono text-[9px] font-medium tracking-[.18em] text-slate-500 uppercase">Manajemen</p>
                <nav class="mt-3 space-y-1" aria-label="Navigasi manajemen">
                    <x-app-nav-link route="products.index" label="Produk & Stok" icon="products" :matches="['products.*']" />
                    <x-app-nav-link route="suppliers.index" label="Supplier" icon="suppliers" :matches="['suppliers.*']" />
                    <x-app-nav-link route="expenses.index" label="Pengeluaran" icon="expenses" :matches="['expenses.*']" />
                    <x-app-nav-link route="reports.daily" label="Laporan" icon="reports" :matches="['reports.*']" />
                    <x-app-nav-link route="approvals.index" label="Persetujuan" icon="approvals" :matches="['approvals.*']" />
                    <x-app-nav-link route="knowledge.index" label="Knowledge Base" icon="knowledge" :matches="['knowledge.*']" />
                    <x-app-nav-link route="testimonials.index" label="Testimoni" icon="testimonials" :matches="['testimonials.*']" />
                    <x-app-nav-link route="whatsapp.index" label="WhatsApp Gateway" icon="whatsapp" :matches="['whatsapp.*']" />
                    @if($currentUser->hasRole('owner'))
                        <x-app-nav-link route="employees.index" label="Pegawai" icon="employees" :matches="['employees.*']" />
                    @endif
                </nav>
            @endif
        </div>

        <div class="border-t border-white/8 p-4">
            <div class="flex items-center gap-3 rounded-2xl bg-white/[.06] p-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-400 font-display font-extrabold text-[#073d2b]">{{ $initial }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-white">{{ $currentUser->name }}</p>
                    <p class="truncate text-[11px] text-slate-400">{{ $currentUser->role->label() }}</p>
                </div>
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button type="submit" class="grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Keluar" title="Keluar">
                        
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="min-w-0 lg:ml-72">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3 sm:gap-6">
                <button type="button" data-app-menu-toggle aria-expanded="false" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 lg:hidden" aria-label="Buka menu">
                    
                </button>
                <nav class="hidden items-center gap-6 text-sm font-semibold sm:flex" aria-label="Navigasi cepat">
                    <a href="{{ route('dashboard') }}" @class(['app-top-link', 'app-top-link-active' => request()->routeIs('dashboard')])>Overview</a>
                    <a href="{{ route('orders.index') }}" @class(['app-top-link', 'app-top-link-active' => request()->routeIs('orders.*')])>Pesanan</a>
                    @if($canManage)
                        <a href="{{ route('reports.daily') }}" @class(['app-top-link', 'app-top-link-active' => request()->routeIs('reports.*')])>Laporan</a>
                    @endif
                </nav>
                <span class="truncate font-display text-sm font-bold text-[#131b2e] sm:hidden">{{ $title ?? 'Operational Hub' }}</span>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @if($canManage)
                    <a href="{{ route('approvals.index') }}" class="grid h-10 w-10 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-[#006c49]" aria-label="Buka persetujuan" title="Persetujuan">
                        
                    </a>
                @endif
                <a href="{{ $canOperateSales ? route('pos.index') : route('orders.index') }}" class="app-create-button">
                    
                    <span class="hidden sm:inline">{{ $canOperateSales ? 'Transaksi Baru' : 'Pesanan Baru' }}</span>
                </a>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-100 font-display text-sm font-extrabold text-[#006c49]">{{ $initial }}</span>
            </div>
        </header>

        <main class="min-w-0 p-4 pb-24 sm:p-6 sm:pb-24 lg:p-8 lg:pb-10">
            @if(session('status'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <strong>Periksa kembali input:</strong>
                    <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    <nav class="fixed inset-x-3 bottom-3 z-30 grid grid-cols-4 rounded-2xl border border-slate-200 bg-white/95 p-1.5 shadow-[0_14px_40px_rgba(15,23,42,.18)] backdrop-blur-xl lg:hidden" aria-label="Navigasi seluler">
        <a href="{{ route('dashboard') }}" @class(['app-mobile-link', 'app-mobile-link-active' => request()->routeIs('dashboard')])>
            <span>Home</span>
        </a>
        <a href="{{ route('orders.index') }}" @class(['app-mobile-link', 'app-mobile-link-active' => request()->routeIs('orders.*')])>
            <span>Pesanan</span>
        </a>
        @if($canOperateSales)
            <a href="{{ route('pos.index') }}" @class(['app-mobile-link', 'app-mobile-link-active' => request()->routeIs('pos.*')])>
                <span>Kasir</span>
            </a>
            <a href="{{ route('sales.index') }}" @class(['app-mobile-link', 'app-mobile-link-active' => request()->routeIs('sales.*')])>
                <span>Penjualan</span>
            </a>
        @else
            <a href="{{ route('orders.index') }}" class="app-mobile-link"><span>Buat</span></a>
            <button type="button" data-app-menu-toggle class="app-mobile-link"><span>Menu</span></button>
        @endif
    </nav>
</div>
@livewireScripts
<x-cookie-consent />
</body>
</html>
