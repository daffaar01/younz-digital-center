@extends('layouts.public')

@section('content')
@php
    $sampleService = $services->first();
    $serviceTones = [
        'print' => ['#d9fbe9', '#006c49', 'PRINT'],
        'fotokopi' => ['#e0f2fe', '#075985', 'COPY'],
        'scan' => ['#ede9fe', '#6d28d9', 'SCAN'],
        'ketik' => ['#fef3c7', '#92400e', 'TYPE'],
        'desain' => ['#ffe4e6', '#be123c', 'DESIGN'],
        'website' => ['#dbeafe', '#1d4ed8', 'WEB'],
        'aplikasi' => ['#e0e7ff', '#4338ca', 'APP'],
    ];
@endphp

<main data-landing-page class="overflow-hidden bg-[#f6f8f4]">
    <section class="landing-hero relative isolate overflow-hidden bg-[#071a16] text-white">
        <div class="landing-hero-grid absolute inset-0 opacity-40" aria-hidden="true"></div>
        <div class="landing-orb landing-orb-one" aria-hidden="true"></div>
        <div class="landing-orb landing-orb-two" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-4 py-16 sm:px-6 sm:py-20 lg:min-h-[660px] lg:grid-cols-[1.02fr_.98fr] lg:py-20">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-[11px] font-bold tracking-[.18em] text-emerald-200 uppercase">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_16px_#34d399]"></span>
                    Pusat layanan digital terpadu
                </div>

                <h1 class="font-display mt-7 text-4xl leading-[1] font-extrabold tracking-[-.045em] text-white sm:text-5xl lg:text-[4.5rem]" aria-label="Satu tempat. Semua beres.">
                    <span class="landing-typewriter-line">Satu tempat.</span><br>
                    <span class="landing-gradient-text landing-typewriter-line">Semua beres.</span>
                </h1>

                <p class="mt-7 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                    Dari cetak dokumen sampai membangun produk digital. Pesan, kirim file, pantau progres, dan konsultasikan kebutuhan Anda tanpa alur yang rumit.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('public.order', ['source' => 'hero']) }}" class="landing-button-bright" data-conversion-cta="hero">Mulai pesanan <span aria-hidden="true">&rarr;</span></a>
                    <a href="{{ route('public.track') }}" class="landing-button-ghost">
                        Lacak pesanan <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

                <div class="mt-9 flex flex-col gap-3 text-sm text-slate-300 sm:flex-row sm:flex-wrap sm:items-center sm:gap-5">
                    <a href="{{ config('services.store.maps_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 hover:text-white">
                        <span class="landing-meta-icon">01</span>{{ config('services.store.address') }}
                    </a>
                    <span class="hidden h-4 w-px bg-white/15 sm:block"></span>
                    <span class="inline-flex items-center gap-2"><span class="landing-meta-icon">02</span>{{ config('services.store.open_hours') }}</span>
                </div>
            </div>

            <div class="relative mx-auto hidden w-full max-w-xl lg:mx-0 lg:ml-auto lg:block" aria-hidden="true">
                <div class="landing-hero-visual">
                    <span class="landing-hero-visual-label">PRINT</span>
                    <span class="landing-hero-visual-label">DESIGN</span>
                    <span class="landing-hero-visual-label">WEB</span>
                    <span class="landing-hero-visual-label">TOP UP</span>
                    <strong>YDC</strong>
                </div>
            </div>
        </div>

        <div class="relative z-10 border-t border-white/10 bg-white/[.035]">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-px px-4 sm:px-6 lg:grid-cols-4">
                @foreach([
                    ['value' => $services->count().'+', 'label' => 'Layanan aktif', 'count' => $services->count(), 'suffix' => '+'],
                    ['value' => '3 langkah', 'label' => 'Pesan hingga diproses', 'count' => 3, 'suffix' => ' langkah'],
                    ['value' => 'Privat', 'label' => 'Penyimpanan file', 'count' => null, 'suffix' => ''],
                    ['value' => 'Realtime', 'label' => 'Pantau status pesanan', 'count' => null, 'suffix' => ''],
                ] as $stat)
                    <div class="border-white/10 px-3 py-6 odd:border-r lg:border-r lg:last:border-r-0 lg:px-8">
                        <p
                            class="font-display text-2xl font-extrabold text-white"
                            @if($stat['count'] !== null)
                                data-count-up
                                data-count-to="{{ $stat['count'] }}"
                                data-count-suffix="{{ $stat['suffix'] }}"
                            @endif
                        >{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="cara-kerja" class="py-16 lg:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="landing-eyebrow">Cara kerja</p>
                <h2 class="landing-title mt-4">Tiga langkah sederhana.</h2>
                <p class="mt-5 leading-7 text-slate-600">Mulai dari memilih layanan hingga memantau hasil, semuanya berada dalam satu alur yang jelas.</p>
            </div>
            <div class="mt-10 grid gap-4 md:grid-cols-3">
                <a href="{{ route('public.order', ['source' => 'process']) }}" class="landing-action-card border border-[#b8e84f] bg-[#cbf75e] text-[#132016]">
                    <div class="flex items-center justify-between"><span class="landing-action-index">01</span><span class="text-sm font-bold">Mulai &rarr;</span></div>
                    <h3 class="font-display mt-10 text-2xl font-extrabold tracking-tight">Pilih dan pesan layanan.</h3>
                    <p class="mt-3 text-sm leading-6 text-[#3e5520]">Isi kebutuhan dan kirim file bila diperlukan.</p>
                </a>
                <a href="{{ route('public.my-orders') }}" class="landing-action-card border border-slate-200 bg-white text-[#132016]">
                    <div class="flex items-center justify-between"><span class="landing-action-index">02</span><span class="text-sm font-bold text-emerald-700">Cek &rarr;</span></div>
                    <h3 class="font-display mt-10 text-2xl font-extrabold tracking-tight">Pantau progres pesanan.</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Verifikasi dengan nomor pesanan dan WhatsApp.</p>
                </a>
                <a href="#younz-ai" class="landing-action-card border border-slate-200 bg-white text-[#132016]">
                    <div class="flex items-center justify-between"><span class="landing-action-index">03</span><span class="text-sm font-bold text-emerald-700">Konsultasi &rarr;</span></div>
                    <h3 class="font-display mt-10 text-2xl font-extrabold tracking-tight">Konsultasi dengan AI.</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Dapatkan rekomendasi sebelum membuat pesanan.</p>
                </a>
            </div>
        </div>
    </section>

    <section id="layanan" class="scroll-mt-24 border-y border-slate-200 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex flex-col gap-7 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="landing-eyebrow">Katalog layanan</p>
                    <h2 class="landing-title mt-4">Satu partner untuk<br>beragam kebutuhan.</h2>
                </div>
                <div class="flex max-w-xl flex-col gap-5 md:items-end">
                    <p class="leading-7 text-slate-600 md:text-right">Harga awal di bawah berasal langsung dari katalog aktif. Operator akan mengonfirmasi spesifikasi dan harga final.</p>
                    <div class="flex gap-2" aria-label="Navigasi katalog layanan">
                        <button data-service-prev type="button" class="landing-round-button" aria-label="Layanan sebelumnya">&larr;</button>
                        <button data-service-next type="button" class="landing-round-button bg-[#132016] text-white" aria-label="Layanan berikutnya">&rarr;</button>
                    </div>
                </div>
            </div>

            <div data-service-track class="scrollbar-none mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto pb-5">
                @forelse($services as $service)
                    @php $tone = $serviceTones[$service->type] ?? ['#e2e8f0', '#334155', 'SERVICE']; @endphp
                    <article class="landing-service-card min-w-[88%] snap-start sm:min-w-[360px] lg:min-w-[calc((100%-2.5rem)/3)]">
                        <div class="landing-service-visual" style="background: {{ $tone[0] }}; color: {{ $tone[1] }}">
                            <span class="font-mono text-[10px] font-bold tracking-[.18em]">{{ $tone[2] }}</span>
                            <span class="landing-service-mark">{{ str($service->type)->substr(0, 1)->upper() }}</span>
                            <span class="absolute right-5 bottom-5 rounded-full border border-current/20 px-3 py-1 text-[10px] font-bold">{{ $service->unit }}</span>
                        </div>
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="font-display text-xl font-extrabold text-[#132016]">{{ $service->name }}</h3>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">AKTIF</span>
                            </div>
                            <p class="mt-3 min-h-12 text-sm leading-6 text-slate-500">{{ $service->description ?: 'Layanan profesional sesuai kebutuhan Anda.' }}</p>
                            <div class="mt-7 flex items-end justify-between gap-4 border-t border-slate-100 pt-5">
                                <div><p class="text-[10px] font-bold tracking-wider text-slate-600 uppercase">Mulai dari</p><p class="font-display mt-1 text-lg font-extrabold text-[#006c49]">Rp {{ number_format($service->base_price, 0, ',', '.') }}</p></div>
                                <a href="{{ route('public.order', ['service_id' => $service->id, 'source' => 'catalog']) }}" class="landing-card-link">Pesan &rarr;</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="w-full rounded-3xl border-2 border-dashed border-slate-200 p-10 text-center text-slate-500">Katalog layanan sedang disiapkan.</div>
                @endforelse
            </div>
        </div>
    </section>

    @if($featuredProducts->isNotEmpty())
    <section id="produk" class="scroll-mt-24 bg-[#f6f8f4] py-16 lg:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="landing-eyebrow">ATK & perlengkapan</p>
                    <h2 class="landing-title mt-4">Kebutuhan kerja, tinggal ambil.</h2>
                    <p class="mt-4 max-w-2xl leading-7 text-slate-600">Produk tersedia langsung di toko. Stok yang tampil mengikuti data inventori terbaru.</p>
                </div>
                <a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-emerald-700/20 bg-white px-5 py-3 text-sm font-extrabold text-[#006c49] transition hover:border-emerald-700/40 hover:bg-emerald-50">Cek stok via WhatsApp &rarr;</a>
            </div>

            <div data-product-grid class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($featuredProducts as $index => $product)
                    @php
                        $productName = str($product->name)->lower();
                        $productCode = $productName->contains('pulpen') ? 'PEN' : ($productName->contains('kertas') ? 'A4' : ($productName->contains('map') ? 'MAP' : ($productName->contains('buku') ? 'BOOK' : 'ATK')));
                    @endphp
                    <article data-product-card class="group flex min-h-[320px] flex-col rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-[0_14px_40px_-30px_rgba(15,23,42,.38)] transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_22px_48px_-28px_rgba(15,23,42,.42)]">
                        <div class="relative grid h-40 place-items-center overflow-hidden rounded-[1.15rem] bg-[linear-gradient(145deg,#eafff3,#d9fbe9)]">
                            <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[9px] font-bold text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>STOK {{ $product->stock }}</span>
                            <span class="font-display text-4xl font-black tracking-[-.05em] text-emerald-900/20 transition duration-300 group-hover:scale-105 group-hover:text-emerald-900/30">{{ $productCode }}</span>
                            <span class="absolute right-3 top-3 font-mono text-[9px] text-emerald-800/35">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-3 pt-4">
                            <p class="min-h-12 text-base font-extrabold leading-6 text-[#132016]">{{ $product->name }}</p>
                            <p class="mt-1 text-xs text-slate-600">Harga per {{ $product->unit }}</p>
                            <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-100 pt-4">
                                <p class="font-display text-lg font-extrabold text-[#006c49]">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</p>
                                <span class="text-[10px] font-bold tracking-wider text-slate-600 uppercase">Tersedia</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section id="younz-ai" class="scroll-mt-20 bg-[#091c18] py-16 text-white lg:py-20">
        <div class="mx-auto max-w-[92rem] px-4 sm:px-6">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-[10px] font-bold tracking-[.16em] text-emerald-200 uppercase"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>AI layanan aktif</span>
                <h2 class="font-display mt-6 text-4xl font-extrabold tracking-[-.035em] sm:text-5xl">Konsultasi dulu. <span class="text-emerald-300">Pesan lebih yakin.</span></h2>
                <p class="mt-6 max-w-4xl leading-7 text-slate-300">Konsultasikan pertanyaan umum atau informasi layanan Younz. Permintaan coding tidak dilayani, dan AI akan menyatakan keterbatasannya saat jawaban tidak dapat dipastikan.</p>

                <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['01', 'Topik umum & layanan Younz'],
                        ['02', 'Tidak melayani coding'],
                        ['03', 'Fakta Younz dari data terverifikasi'],
                        ['04', 'Mengakui saat belum yakin'],
                    ] as [$number, $copy])
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-3.5"><span class="font-mono text-[10px] text-emerald-300">{{ $number }}</span><p class="text-xs font-semibold text-slate-300">{{ $copy }}</p></div>
                    @endforeach
                </div>
            </div>

            <form action="{{ route('public.ai.chat') }}" method="post" data-ai-chat data-ai-history="{{ json_encode(session('ai_chat_history', [])) }}" class="landing-chat mt-12 flex w-full max-h-[42rem] flex-col overflow-hidden bg-white text-[#132016]">@csrf
                <template data-ai-avatar-template><x-younz-ai-avatar alt="" /></template>
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <span class="grid h-12 w-10 shrink-0 overflow-hidden rounded-2xl bg-[#cbf75e] ring-1 ring-emerald-900/10"><x-younz-ai-avatar /></span>
                    <div class="min-w-0 flex-1"><p class="text-sm font-extrabold">Younz AI</p><p class="mt-0.5 flex items-center gap-1.5 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Siap membantu</p></div>
                    <button type="button" data-ai-clear class="rounded-lg px-2 py-1 text-[10px] font-bold text-slate-500 hover:bg-slate-100" aria-label="Hapus riwayat percakapan">Hapus riwayat</button>
                </div>
                <div data-ai-messages class="flex-1 space-y-4 overflow-y-auto bg-[#f7f9f6] px-5 py-5 sm:px-6" aria-live="polite" style="min-height: 23rem;">
                    <div data-ai-empty class="grid min-h-[20rem] place-items-center text-center">
                        <div>
                            <span class="mx-auto grid h-40 w-32 overflow-hidden rounded-[2rem] bg-[#f4f1ed] shadow-[0_16px_40px_rgba(19,32,22,0.10)] ring-1 ring-slate-900/10"><x-younz-ai-avatar /></span>
                            <p class="mx-auto mt-4 max-w-xs text-sm leading-6 text-slate-600">Bahas apa saja selain coding. Untuk informasi Younz, jawaban menggunakan data layanan yang terverifikasi.</p>
                            <div class="mt-5 flex flex-wrap justify-center gap-2">
                                <button type="button" data-ai-suggest="Berapa harga print A4?" class="landing-suggestion">Harga print A4?</button>
                                <button type="button" data-ai-suggest="Jam buka dan alamat Younz?" class="landing-suggestion">Jam & alamat?</button>
                                <button type="button" data-ai-suggest="Jelaskan perbedaan CV dan portofolio." class="landing-suggestion">CV & portofolio?</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 bg-white p-4 sm:px-5">
                    <div class="flex gap-3"><label for="ai-message" class="sr-only">Pertanyaan Anda</label><input id="ai-message" name="message" class="min-h-12 flex-1 rounded-2xl border-0 bg-slate-100 px-4 text-[#132016] placeholder:text-slate-400" placeholder="Tulis pertanyaan Anda..." required maxlength="3000"><button class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#132016] text-lg font-bold text-[#cbf75e] transition hover:bg-[#1c332a] active:scale-95 disabled:cursor-wait disabled:opacity-60" type="submit" aria-label="Kirim pertanyaan">&rarr;</button></div>
                    <label class="mt-3 flex min-h-11 items-start gap-2 font-normal text-slate-500"><input name="ai_consent" value="1" type="checkbox" required class="mt-1 h-5 w-5 shrink-0"><span class="text-[10px] leading-5">Saya memahami pertanyaan dan riwayat terkait dapat diproses penyedia AI pihak ketiga. Jangan masukkan data pribadi atau rahasia. <a href="{{ route('privacy') }}" class="font-bold text-[#006c49] underline">Pelajari privasi</a>.</span></label>
                </div>
            </form>
        </div>
    </section>

    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="text-center">
                <p class="landing-eyebrow">{{ $testimonials->isNotEmpty() ? 'Testimoni terverifikasi' : 'Proses yang dapat diperiksa' }}</p>
                <h2 class="landing-title mx-auto mt-4 max-w-3xl">{{ $testimonials->isNotEmpty() ? 'Pengalaman nyata dari pelanggan.' : 'Pekerjaan kecil atau ide besar,' }}<br>{{ $testimonials->isNotEmpty() ? 'Ditampilkan dengan persetujuan.' : 'kami tangani dengan serius.' }}</h2>
            </div>
            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @forelse($testimonials as $testimonial)
                    <article class="rounded-[1.75rem] border border-slate-200 bg-[#f8faf7] p-6 sm:p-7">
                        <div class="text-amber-400" aria-label="{{ $testimonial->rating }} dari 5 bintang">{{ str_repeat('Ã¢Ëœâ€¦', $testimonial->rating) }}<span class="text-slate-300">{{ str_repeat('Ã¢Ëœâ€¦', 5 - $testimonial->rating) }}</span></div>
                        <blockquote class="font-display mt-6 text-xl font-bold leading-8 text-[#132016]">Ã¢â‚¬Å“{{ $testimonial->quote }}Ã¢â‚¬Â</blockquote>
                        <div class="mt-7 flex items-center gap-3 border-t border-slate-200 pt-5">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-[#132016] text-xs font-bold text-white">{{ str($testimonial->customer_name)->substr(0, 2)->upper() }}</span>
                            <div><p class="text-sm font-bold">{{ $testimonial->customer_name }}</p><p class="text-xs text-slate-600">{{ $testimonial->customer_role ?: $testimonial->source_label }}</p></div>
                        </div>
                    </article>
                @empty
                    @foreach([
                        ['Status transparan', 'Nomor pelacakan dibuat setelah formulir pesanan berhasil dikirim.'],
                        ['File tetap privat', 'Berkas pesanan disimpan pada storage privat dan dilayani melalui endpoint terautentikasi.'],
                        ['Estimasi diperiksa manusia', 'Harga dan waktu pengerjaan dikonfirmasi operator sebelum pekerjaan dimulai.'],
                    ] as [$factTitle, $fact])
                        <article class="rounded-[1.75rem] border border-slate-200 bg-[#f8faf7] p-6 sm:p-7">
                            <p class="text-xs font-black tracking-[.14em] text-emerald-700 uppercase">Proses terverifikasi</p>
                            <h3 class="font-display mt-5 text-xl font-extrabold text-[#132016]">{{ $factTitle }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $fact }}</p>
                        </article>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 bg-[#f6f8f4] py-20 lg:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-20">
            <div>
                <p class="landing-eyebrow">Estimasi cepat</p>
                <h2 class="landing-title mt-4">Hitung sebelum<br>datang ke toko.</h2>
                <p class="mt-5 max-w-md leading-7 text-slate-600">Pilih layanan dan jumlah. Estimasi memakai harga awal dari katalog aktif.</p>
                <form class="mt-8 rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-[0_18px_50px_-35px_rgba(15,23,42,.35)] sm:p-6" onsubmit="return false;">
                    <label for="estimator-service">Layanan</label>
                    <select id="estimator-service" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3">
                        <option value="">Pilih layanan...</option>
                        @foreach($services as $service)
                            <option value="{{ $service->base_price }}" data-unit="{{ $service->unit }}">{{ $service->name }} Ã¢â‚¬â€ Rp {{ number_format($service->base_price, 0, ',', '.') }}/{{ $service->unit }}</option>
                        @endforeach
                    </select>
                    <div class="mt-4 grid grid-cols-[1fr_auto] gap-3">
                        <div><label for="estimator-qty">Jumlah</label><input id="estimator-qty" type="number" min="1" value="1" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3"></div>
                        <button id="estimator-btn" type="button" class="mt-7 rounded-2xl bg-[#132016] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#1c332a]">Hitung</button>
                    </div>
                    <div id="estimator-result" class="mt-4 hidden rounded-2xl bg-[#cbf75e] p-5"><p class="text-xs font-bold text-[#36500d]">ESTIMASI AWAL</p><p class="font-display mt-1 text-3xl font-black text-[#132016]">Rp <span id="estimator-total">0</span></p><p class="mt-1 text-[10px] text-[#465b25]">Harga final dikonfirmasi operator.</p></div>
                </form>
            </div>

            <div>
                <p class="landing-eyebrow">Pertanyaan umum</p>
                <h2 class="landing-title mt-4">Sebelum memesan.</h2>
                <div class="mt-8 space-y-3">
                    @foreach([
                        ['Bagaimana cara memesan layanan?', 'Klik Ã¢â‚¬Å“Mulai pesananÃ¢â‚¬Â, pilih layanan, isi kebutuhan, dan unggah file bila diperlukan. Setelah dikirim, Anda mendapatkan tautan untuk memantau status.'],
                        ['Berapa lama proses pengerjaan?', 'Durasi bergantung pada layanan dan kompleksitas. Operator akan memberikan estimasi setelah memeriksa detail atau file Anda.'],
                        ['Format file apa yang dapat dikirim?', 'PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, dan TXT dapat dikirim. File berisiko atau executable akan ditolak demi keamanan.'],
                        ['Bagaimana menjaga privasi pesanan?', 'Halaman Pesanan Saya memerlukan nomor pesanan dan nomor WhatsApp yang sama dengan data saat memesan.'],
                        ['Apakah bisa konsultasi desain atau website?', 'Bisa. Berikan tujuan, referensi, materi, dan target waktu. Tim akan membantu menyusun ruang lingkup sebelum harga final dikonfirmasi.'],
                    ] as [$question, $answer])
                        <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition open:border-emerald-300">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-5 px-5 py-5 text-sm font-extrabold text-[#132016]"><span>{{ $question }}</span><span class="text-lg text-emerald-700 transition group-open:rotate-45">+</span></summary>
                            <p class="px-5 pb-5 text-sm leading-6 text-slate-500">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="landing-cta relative overflow-hidden rounded-[2rem] bg-[#cbf75e] px-6 py-14 text-[#132016] sm:px-10 lg:px-16 lg:py-16">
                <div class="absolute -right-12 -top-28 h-80 w-80 rounded-full border-[60px] border-[#132016]/[.06]" aria-hidden="true"></div>
                <div class="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div><p class="text-xs font-bold tracking-[.15em] uppercase">Siap mulai?</p><h2 class="font-display mt-4 max-w-3xl text-4xl font-black tracking-[-.04em] sm:text-5xl">Ide Anda berikutnya bisa dimulai hari ini.</h2></div>
                    <div class="flex flex-col gap-3 sm:flex-row lg:flex-col"><a href="{{ route('public.order', ['source' => 'final']) }}" class="landing-button-dark">Kirim kebutuhan &rarr;</a><a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener noreferrer" class="landing-button-light">WhatsApp {{ config('services.whatsapp.display_number') }}</a></div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection


