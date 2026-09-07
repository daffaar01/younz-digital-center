@extends('layouts.public')

@php
    $title = 'Prabayar & Pascabayar · Younz Digital Center';
    $description = 'Beli produk prabayar atau cek dan bayar tagihan pascabayar melalui Midtrans.';
    $selectedId = (string) old('product_id', '');
@endphp

@section('content')
<main class="bg-[#eef8f3]">
    <section class="relative overflow-hidden bg-[#102720] text-white">
        <div class="landing-hero-grid absolute inset-0"></div>
        <div class="landing-orb landing-orb-one"></div>
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.15fr_.85fr] lg:items-center lg:py-20">
            <div>
                <span class="inline-flex rounded-full border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 font-mono text-[10px] tracking-[.16em] text-emerald-300 uppercase">Top Up Digital</span>
                <h1 class="mt-6 max-w-3xl font-display text-4xl leading-[1.04] font-extrabold tracking-[-.04em] sm:text-6xl">Prabayar dan tagihan, <span class="landing-gradient-text">selesai dari sini.</span></h1>
                <p class="mt-5 max-w-2xl text-sm leading-7 text-emerald-50/70 sm:text-base">Beli pulsa dan paket data, atau cek tagihan pascabayar terlebih dahulu. Pembayaran dilanjutkan melalui halaman resmi Midtrans dan statusnya dapat dipantau tanpa login.</p>
                <a href="{{ route('topup.access') }}" class="mt-6 inline-flex min-h-11 items-center rounded-full border border-white/15 bg-white/10 px-5 text-sm font-bold text-white transition hover:bg-white/15">Buka transaksi sebelumnya →</a>
            </div>
            <div class="grid grid-cols-3 gap-3">
                @foreach([['01','Pilih layanan'],['02','Cek & bayar'],['03','Diproses']] as [$number, $label])
                    <div class="rounded-2xl border border-white/10 bg-white/[.06] p-4 backdrop-blur">
                        <span class="font-mono text-xs text-[#cbf75e]">{{ $number }}</span>
                        <p class="mt-8 text-xs font-bold text-white sm:text-sm">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:py-14">
        @unless($integrationsReady)
            <div class="mb-7 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                
                <div><strong class="block">Pembelian belum diaktifkan</strong><span>Katalog dapat dilihat setelah sinkronisasi, tetapi checkout baru tersedia setelah kredensial Digiflazz dan Midtrans diisi oleh pengelola.</span></div>
            </div>
        @endunless

        <div class="mb-5 grid grid-cols-2 gap-2 rounded-2xl border border-emerald-900/10 bg-white p-2 shadow-sm sm:max-w-md">
            @foreach(\App\Enums\DigiflazzTransactionType::cases() as $tab)
                <a href="{{ route('topup.index', ['mode' => $tab->value]) }}" class="flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-extrabold transition {{ $mode === $tab ? 'bg-[#132016] text-white shadow-sm' : 'text-slate-500 hover:bg-emerald-50 hover:text-[#006c49]' }}">
                    {{ $tab->label() }}
                </a>
            @endforeach
        </div>

        @if($mode === \App\Enums\DigiflazzTransactionType::Postpaid)
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900"><strong>Cara pascabayar:</strong> pilih layanan dan masukkan nomor pelanggan. Nominal serta nama pelanggan akan dicek ke Digiflazz sebelum halaman pembayaran Midtrans dibuat. Biaya admin Younz <strong>Rp {{ number_format((int) config('services.digiflazz.postpaid_admin_fee'), 0, ',', '.') }}</strong>.</div>
        @endif

        <form method="get" action="{{ route('topup.index') }}" class="mb-8 grid gap-3 rounded-3xl border border-emerald-900/10 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_220px_auto]">
            <input type="hidden" name="mode" value="{{ $mode->value }}">
            <label class="sr-only" for="topup-search">Cari produk</label>
            <input id="topup-search" name="q" value="{{ request('q') }}" placeholder="Cari pulsa, data, atau brand…">
            <label class="sr-only" for="topup-category">Kategori</label>
            <select id="topup-category" name="category"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select>
            <label class="sr-only" for="topup-brand">Brand</label>
            <select id="topup-brand" name="brand"><option value="">Semua brand</option>@foreach($brands as $brand)<option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach</select>
            <button class="public-btn-dark" type="submit">Tampilkan</button>
        </form>

        <form method="post" action="{{ route('topup.store') }}" data-topup-checkout>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
                <div>
                    <div class="mb-5 flex items-end justify-between gap-4">
                        <div><p class="public-label">Katalog {{ $mode->label() }}</p><h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-[#132016]">{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Pilih jenis tagihan' : 'Pilih produk digital' }}</h2></div>
                        <span class="text-xs font-semibold text-slate-500">{{ $products->total() }} produk</span>
                    </div>

                    @if($products->isEmpty())
                        <div class="rounded-3xl border border-dashed border-emerald-300 bg-white p-10 text-center">
                            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-2xl">↻</div>
                            <h3 class="mt-4 font-display text-xl font-extrabold text-[#132016]">Katalog {{ strtolower($mode->label()) }} sedang disiapkan</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Layanan akan muncul setelah katalog Digiflazz berhasil disinkronkan. Coba lagi beberapa saat atau hubungi operator.</p>
                        </div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($products as $product)
                                <label data-topup-product data-name="{{ $product->product_name }}" data-brand="{{ $product->brand }}" data-postpaid="{{ $product->transaction_type === \App\Enums\DigiflazzTransactionType::Postpaid ? 'true' : 'false' }}" data-price="{{ $product->selling_price }}" class="group relative cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-400 hover:shadow-md {{ $selectedId === (string) $product->id ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200' }}">
                                    <input class="sr-only" type="radio" name="product_id" value="{{ $product->id }}" @checked($selectedId === (string) $product->id)>
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 font-mono text-[9px] font-bold tracking-wider text-[#006c49] uppercase">{{ $product->category }}</span>
                                    <h3 class="mt-4 min-h-10 font-display text-sm leading-5 font-extrabold text-[#132016]">{{ $product->product_name }}</h3>
                                    <p class="mt-1 text-xs text-slate-400">{{ $product->brand }}</p>
                                    <div class="mt-5 flex items-end justify-between gap-2"><span class="text-[10px] font-semibold text-slate-400">{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Nominal tagihan' : 'Harga total' }}</span><strong class="text-base text-[#006c49]">{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Cek tagihan' : 'Rp '.number_format($product->selling_price, 0, ',', '.') }}</strong></div>
                                    <span data-product-check class="absolute top-3 right-3 hidden h-6 w-6 place-items-center rounded-full bg-[#006c49] text-xs font-bold text-white">✓</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-7">{{ $products->links() }}</div>
                    @endif
                </div>

                <aside class="sticky top-24 overflow-hidden rounded-[1.75rem] border border-emerald-900/10 bg-white shadow-[0_24px_60px_-35px_rgba(15,23,42,.45)]">
                    <div class="bg-[#132016] p-6 text-white">
                        <p class="font-mono text-[10px] tracking-[.15em] text-emerald-300 uppercase">Ringkasan pesanan</p>
                        <h2 data-topup-summary-name class="mt-3 font-display text-xl font-extrabold">Pilih produk dahulu</h2>
                        <div class="mt-5 flex items-end justify-between gap-4"><span data-topup-summary-brand class="text-xs text-emerald-50/60">Belum dipilih</span><div class="text-right"><span class="block font-mono text-[9px] tracking-wider text-emerald-50/50 uppercase">{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Total setelah cek' : 'Harga total' }}</span><strong data-topup-summary-price class="mt-1 block text-xl text-[#cbf75e]">Rp 0</strong></div></div>
                    </div>
                    <div class="space-y-4 p-6">
                        <div><label for="destination">{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Nomor pelanggan / ID tagihan' : 'Nomor / ID tujuan' }}</label><input class="mt-1.5 w-full" id="destination" name="destination" value="{{ old('destination') }}" maxlength="40" autocomplete="off" placeholder="Contoh: 08219207240" required></div>
                        <div><label for="destination_confirmation">Ulangi nomor / ID</label><input class="mt-1.5 w-full" id="destination_confirmation" name="destination_confirmation" value="{{ old('destination_confirmation') }}" maxlength="40" autocomplete="off" placeholder="Ketik ulang untuk memastikan" required></div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <div><label for="customer_name">Nama</label><input class="mt-1.5 w-full" id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" maxlength="100" autocomplete="name" required></div>
                            <div><label for="customer_phone">Nomor WhatsApp</label><input class="mt-1.5 w-full" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', auth()->user()?->phone) }}" maxlength="20" autocomplete="tel" inputmode="tel" required></div>
                        </div>
                        <div><label for="customer_email">Email bukti transaksi</label><input class="mt-1.5 w-full" type="email" id="customer_email" name="customer_email" value="{{ old('customer_email', auth()->user()?->email) }}" maxlength="150" autocomplete="email" required></div>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-3 text-xs leading-5 font-normal text-slate-600"><input class="mt-1 shrink-0" type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>Saya sudah memeriksa nomor tujuan dan menyetujui <a class="font-bold text-[#006c49] underline" href="{{ route('terms') }}" target="_blank">syarat layanan</a>. Transaksi sukses tidak dapat salah tujuan.</span></label>
                        <button data-topup-submit @if(! $integrationsReady) data-integration-disabled @endif type="submit" class="public-btn-primary w-full" @disabled(! $integrationsReady || $products->isEmpty())>{{ $mode === \App\Enums\DigiflazzTransactionType::Postpaid ? 'Cek tagihan & lanjut' : 'Lanjut ke pembayaran' }} <span aria-hidden="true">→</span></button>
                        <div class="flex items-center justify-center gap-2 text-[10px] font-semibold text-slate-400">Pembayaran diproses di Midtrans</div>
                    </div>
                </aside>
            </div>
        </form>
    </section>
</main>
@endsection
