@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="font-mono text-[10px] font-bold tracking-[.16em] text-emerald-700 uppercase">Integrasi</p>
            <h1 class="font-display mt-2 text-3xl font-extrabold tracking-tight text-[#131b2e]">WhatsApp Gateway</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Hubungkan satu perangkat WhatsApp untuk notifikasi status pesanan. QR hanya tersedia untuk owner dan admin.</p>
        </div>
        <span @class([
            'inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold',
            'bg-emerald-100 text-emerald-800' => $gateway['connected'],
            'bg-amber-100 text-amber-800' => ! $gateway['connected'],
        ])>
            <span @class(['h-2 w-2 rounded-full', 'bg-emerald-500' => $gateway['connected'], 'bg-amber-500' => ! $gateway['connected']])></span>
            {{ $gateway['connected'] ? 'Terhubung' : ucfirst(str_replace('_', ' ', $gateway['state'])) }}
        </span>
    </div>

    @if(session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.1fr_.9fr]">
        <section class="app-surface p-6 sm:p-8">
            @if($gateway['qr'])
                <div class="text-center">
                    <p class="font-display text-xl font-extrabold text-[#131b2e]">Scan QR dari WhatsApp</p>
                    <p class="mt-2 text-sm text-slate-500">Buka WhatsApp, pilih Perangkat tertaut, lalu tautkan perangkat baru.</p>
                    <img src="{{ $gateway['qr'] }}" alt="QR untuk menghubungkan WhatsApp" width="360" height="360" class="mx-auto mt-6 w-full max-w-80 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="mt-4 text-xs text-slate-400">Muat ulang halaman jika QR kedaluwarsa.</p>
                </div>
            @elseif($gateway['connected'])
                <div class="grid min-h-80 place-items-center text-center">
                    <div>
                        <span class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-emerald-100 text-4xl text-emerald-700">&#10003;</span>
                        <h2 class="font-display mt-5 text-2xl font-extrabold text-[#131b2e]">WhatsApp siap digunakan</h2>
                        <p class="mt-2 text-sm text-slate-500">Akun terhubung: <strong class="text-slate-700">{{ $gateway['account'] ?? 'Tersambung' }}</strong></p>
                    </div>
                </div>
            @else
                <div class="grid min-h-80 place-items-center text-center">
                    <div>
                        <span class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-amber-100 text-3xl text-amber-700">!</span>
                        <h2 class="font-display mt-5 text-2xl font-extrabold text-[#131b2e]">Gateway belum siap</h2>
                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">{{ $gateway['lastError'] ?? 'Hubungkan ulang untuk menghasilkan QR baru.' }}</p>
                    </div>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="app-surface p-6">
                <h2 class="font-display text-lg font-extrabold text-[#131b2e]">Kontrol sesi</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Rekoneksi tidak menghapus sesi. Keluar akan menghapus tautan perangkat dan membutuhkan QR baru.</p>
                <div class="mt-5 grid gap-3">
                    <form method="post" action="{{ route('whatsapp.reconnect') }}">@csrf
                        <button class="btn-primary w-full" type="submit">Hubungkan ulang</button>
                    </form>
                    @if($gateway['connected'])
                        <form method="post" action="{{ route('whatsapp.logout') }}" onsubmit="return confirm('Keluarkan perangkat WhatsApp ini?')">@csrf @method('delete')
                            <button class="btn-secondary w-full border-red-200 text-red-700 hover:bg-red-50" type="submit">Keluarkan perangkat</button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="rounded-3xl bg-[#131b2e] p-6 text-white shadow-sm">
                <p class="font-mono text-[10px] font-bold tracking-[.15em] text-emerald-300 uppercase">Fitur aktif</p>
                <h2 class="font-display mt-3 text-lg font-extrabold">Otomasi WhatsApp</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
                    <li><strong class="text-white">Notifikasi:</strong> perubahan status pesanan dikirim otomatis.</li>
                    <li><strong class="text-white">Younz AI:</strong> chat personal biasa dijawab menggunakan knowledge base.</li>
                    <li><strong class="text-white">Pemesanan:</strong> awali pesan dengan <code class="rounded bg-white/10 px-1.5 py-0.5 text-emerald-200">PESAN</code> untuk membuat draft.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
@endsection
