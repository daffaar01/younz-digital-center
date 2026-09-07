<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = $title ?? 'Younz Digital Center Ã‚Â· Solusi Digital Terpadu';
        $pageDescription = $description ?? 'Print, fotokopi, ATK, pulsa, pembayaran, desain, website, dan aplikasi di Younz Digital Center.';
        $canonicalUrl = $canonical ?? url()->current();
        $socialImage = $image ?? asset('favicon.webp');
        $isStaffHost = strcasecmp(request()->getHost(), (string) config('app.staff_host')) === 0;
    @endphp
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#071a16">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="Younz Digital Center">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="Layanan Younz Digital Center">
    <meta name="twitter:card" content="summary_large_image">
    <title>{{ $pageTitle }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('favicon.webp') }}?v=1">
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Younz Digital Center",
        "description": "Print, fotokopi, ATK, pulsa, pembayaran, desain, website, dan aplikasi dalam satu tempat.",
        "url": "{{ config('app.url') }}",
        "telephone": "+{{ config('services.whatsapp.number') }}",
        "openingHours": "{{ config('services.store.open_hours') }}",
        "address": { "@type": "PostalAddress", "streetAddress": "{{ config('services.store.address') }}" },
        "hasMap": "{{ config('services.store.maps_url') }}",
        "priceRange": "Rp",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+{{ config('services.whatsapp.number') }}",
            "contactType": "customer service",
            "availableLanguage": "Indonesian"
        },
        "sameAs": ["https://wa.me/{{ config('services.whatsapp.number') }}"]
    }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body @if(! $isStaffHost) data-service-worker-enabled @endif class="min-h-screen overflow-x-hidden bg-[#f7f9fb] text-[#191c1e]">
<header data-public-nav class="fixed inset-x-0 top-0 z-50 h-20 border-b border-[#bbcabf]/40 bg-[#f7f9fb]/85 backdrop-blur-xl transition-shadow">
    <div class="mx-auto flex h-full max-w-7xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('home') }}" aria-label="Beranda Younz Digital Center">
            <x-brand-logo :responsive="true" />
        </a>

        <nav class="hidden items-center gap-7 text-sm font-semibold lg:flex" aria-label="Navigasi utama">
            <a class="border-b-2 pb-1 {{ request()->routeIs('home') ? 'border-[#006c49] text-[#006c49]' : 'border-transparent text-slate-600 hover:text-[#006c49]' }}" href="{{ route('home') }}">Beranda</a>
            <a class="border-b-2 border-transparent pb-1 text-slate-600 hover:text-[#006c49]" href="{{ route('home') }}#layanan">Layanan</a>
            <a class="border-b-2 pb-1 {{ request()->routeIs('topup.*') ? 'border-[#006c49] text-[#006c49]' : 'border-transparent text-slate-600 hover:text-[#006c49]' }}" href="{{ route('topup.index') }}">Top Up</a>
            <a class="border-b-2 border-transparent pb-1 text-slate-600 hover:text-[#006c49]" href="{{ route('home') }}#cara-kerja">Cara Kerja</a>
            <a class="border-b-2 border-transparent pb-1 text-slate-600 hover:text-[#006c49]" href="{{ route('home') }}#younz-ai">Younz AI</a>
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('public.track') }}" class="hidden rounded-xl px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-emerald-50 hover:text-[#006c49] sm:inline-flex">Cek Pesanan</a>
            @auth
                @if(auth()->user()->isStaff())
                    <a href="{{ route('dashboard') }}" class="public-btn-dark">Dashboard</a>
                @else
                    <a href="{{ route('customer.dashboard') }}" class="public-btn-dark">Akun Saya</a>
                @endif
            @else
                @if($isStaffHost)
                    <a href="{{ route('login') }}" data-staff-nav-link="desktop" class="hidden rounded-xl px-3 py-2 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-[#132016] xl:inline-flex">Pegawai</a>
                @endif
                <a href="{{ route('customer.login') }}" class="public-btn-dark">Masuk Pelanggan</a>
            @endauth
            <button type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="public-mobile-menu" class="public-menu-toggle lg:hidden">
                <span class="sr-only" data-mobile-menu-label>Buka menu</span>
                <span class="public-menu-toggle-icon" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>
        </div>
    </div>
    <nav id="public-mobile-menu" data-mobile-menu class="public-mobile-menu lg:hidden" aria-label="Navigasi mobile" aria-hidden="true" inert>
        <span class="public-mobile-menu-orb" aria-hidden="true"></span>
        <div class="public-mobile-menu-inner">
            <p class="public-mobile-menu-eyebrow">Jelajahi Younz</p>

            <div class="public-mobile-menu-primary">
                <a class="public-mobile-menu-link public-mobile-menu-link-lime" href="{{ route('home') }}"><span>01</span><strong>Beranda</strong></a>
                <a class="public-mobile-menu-link public-mobile-menu-link-cyan" href="{{ route('home') }}#layanan"><span>02</span><strong>Layanan</strong></a>
                <a class="public-mobile-menu-link public-mobile-menu-link-coral" href="{{ route('topup.index') }}"><span>03</span><strong>Top Up</strong></a>
                <a class="public-mobile-menu-link public-mobile-menu-link-lime" href="{{ route('home') }}#cara-kerja"><span>04</span><strong>Cara Kerja</strong></a>
                <a class="public-mobile-menu-link public-mobile-menu-link-cyan" href="{{ route('home') }}#younz-ai"><span>05</span><strong>Younz AI</strong></a>
            </div>

            <div class="public-mobile-menu-utilities">
                <a href="{{ route('public.order') }}">Kirim File <span>Ã¢â€ â€”</span></a>
                <a href="{{ route('public.track') }}">Cek Pesanan <span>Ã¢â€ â€”</span></a>
                <a href="{{ route('public.my-orders') }}">Pesanan Saya <span>Ã¢â€ â€”</span></a>
            </div>

            <div class="public-mobile-menu-actions">
                @auth
                    <a class="public-mobile-menu-account" href="{{ auth()->user()->isStaff() ? route('dashboard') : route('customer.dashboard') }}">{{ auth()->user()->isStaff() ? 'Dashboard Pegawai' : 'Akun Pelanggan' }}</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="public-mobile-menu-signout">Keluar</button></form>
                @else
                    <a class="public-mobile-menu-account" href="{{ route('customer.login') }}">Masuk Pelanggan</a>
                    <a class="public-mobile-menu-register" href="{{ route('customer.register') }}">Daftar Pelanggan</a>
                    @if($isStaffHost)
                        <a class="public-mobile-menu-staff" data-staff-nav-link="mobile" href="{{ route('login') }}">Login Pegawai</a>
                    @endif
                @endauth
                <a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener noreferrer" class="public-mobile-menu-whatsapp">
                    
                    WhatsApp {{ config('services.whatsapp.display_number') }}
                </a>
            </div>
        </div>
    </nav>
</header>

<div class="pt-20">
    @if(session('status'))<div class="mx-auto mt-5 max-w-5xl rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mx-auto mt-5 max-w-5xl rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</div>

@if(request()->routeIs('home'))
    <aside data-sticky-order-cta aria-hidden="true" class="pointer-events-none fixed inset-x-0 bottom-4 z-40 translate-y-24 px-4 opacity-0 transition duration-300">
        <div class="pointer-events-auto mx-auto flex max-w-xl items-center justify-between gap-4 rounded-2xl border border-white/10 bg-[#132016]/95 p-3 pl-4 text-white shadow-[0_24px_70px_-24px_rgba(15,23,42,.8)] backdrop-blur-xl">
            <div class="min-w-0"><p class="text-xs font-extrabold">Sudah tahu kebutuhannya?</p><p class="truncate text-[10px] text-slate-300">Kirim detail, operator akan memeriksa.</p></div>
            <a data-conversion-cta="sticky" href="{{ route('public.order', ['source' => 'sticky']) }}" class="shrink-0 rounded-xl bg-[#cbf75e] px-4 py-2.5 text-xs font-extrabold text-[#132016]">Mulai pesan</a>
        </div>
    </aside>
@endif

<footer class="public-footer rounded-t-[2.5rem] border-t-4 border-[#10b981] bg-[#131b2e] text-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1.4fr_.7fr_.8fr] lg:grid-cols-[1.35fr_.65fr_.75fr_1.15fr] lg:gap-10 lg:py-12">
        <div class="max-w-md">
            <a href="{{ route('home') }}"><x-brand-logo :inverse="true" /></a>
            <p class="mt-4 text-sm leading-6 text-slate-400">Partner digital terpercaya untuk kebutuhan cetak, desain, dan pengembangan teknologi dalam satu layanan yang efisien.</p>
            <div class="mt-4 space-y-2 text-xs text-slate-400"><p class="flex items-center gap-2">{{ config('services.store.open_hours') }}</p><p class="flex items-center gap-2"><a href="{{ config('services.store.maps_url') }}" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-300">{{ config('services.store.address') }}</a></p><p class="flex items-center gap-2"><a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-300">{{ config('services.whatsapp.display_number') }}</a></p></div>
            <span class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-2 font-mono text-xs text-emerald-300"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>Sistem online</span>
        </div>
        <div>
            <h2 class="font-display font-bold">Layanan</h2>
            <ul class="mt-4 space-y-1 text-sm text-slate-400"><li><a class="hover:text-emerald-300" href="{{ route('home') }}#layanan">Print & Fotokopi</a></li><li><a class="hover:text-emerald-300" href="{{ route('topup.index') }}">Top Up Pulsa & Data</a></li><li><a class="hover:text-emerald-300" href="{{ route('home') }}#layanan">Desain Grafis</a></li><li><a class="hover:text-emerald-300" href="{{ route('home') }}#layanan">Web Development</a></li></ul>
        </div>
        <div>
            <h2 class="font-display font-bold">Bantuan</h2>
            <ul class="mt-4 space-y-1 text-sm text-slate-400"><li><a class="hover:text-emerald-300" href="{{ route('public.order') }}">Kirim File</a></li><li><a class="hover:text-emerald-300" href="{{ route('public.track') }}">Cek Status</a></li><li><a class="hover:text-emerald-300" href="{{ route('customer.login') }}">Akun Pelanggan</a></li><li><a class="hover:text-emerald-300" href="{{ route('home') }}#younz-ai">Knowledge Base</a></li><li><a class="hover:text-emerald-300" href="{{ route('privacy') }}">Kebijakan Privasi</a></li><li><a class="hover:text-emerald-300" href="{{ route('terms') }}">Syarat Layanan</a></li><li><a class="hover:text-emerald-300" href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener noreferrer">Hubungi WA</a></li><li><a class="hover:text-emerald-300" href="{{ route('login') }}">Login Pegawai</a></li></ul>
        </div>
        <div class="md:col-span-3 lg:col-span-1">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-bold">Lokasi Kami</h2>
                <a href="{{ config('services.store.maps_url') }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-emerald-300 hover:text-emerald-200">Buka Maps Ã¢â€ â€™</a>
            </div>
            <iframe
                class="mt-4 h-52 w-full rounded-2xl border border-white/10 bg-slate-800 lg:h-44"
                src="{{ config('services.store.maps_embed_url') }}"
                title="Peta lokasi Younz Digital Center"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen>
            </iframe>
        </div>
    </div>
    <div class="border-t border-white/10"><div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 text-xs text-slate-400 sm:px-6 md:flex-row md:items-center md:justify-between"><p>Ã‚Â© {{ date('Y') }} Younz Digital Center. Solusi Digital Terpadu untuk Ide Anda.</p><div class="flex flex-wrap items-center gap-3"><button type="button" data-cookie-settings class="transition hover:text-emerald-300">Pengaturan cookie</button><span aria-hidden="true">Ã‚Â·</span><p>Print Ã‚Â· Desain Ã‚Â· Teknologi</p></div></div></div>
</footer>
<x-cookie-consent />
</body>
</html>

