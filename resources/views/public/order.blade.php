@extends('layouts.public')
@section('content')
@php
    $accountUser = auth()->check() && ! auth()->user()->isStaff() ? auth()->user() : null;
    $accountCustomer = $accountUser?->customer;
    $allowedSources = ['hero', 'catalog', 'process', 'sticky', 'final', 'direct'];
    $orderSource = in_array(request('source'), $allowedSources, true) ? request('source') : 'direct';
@endphp
<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <p class="text-sm font-bold text-brand-700">PESAN LAYANAN</p>
    <h1 class="mt-2 text-4xl font-black text-ink-900">Kirim kebutuhan dan file Anda</h1>
    <p class="mt-3 max-w-2xl text-slate-500">Isi detail singkat. Operator memeriksa file dan spesifikasi sebelum mengonfirmasi harga serta waktu selesai melalui WhatsApp.</p>
    <ol class="mt-7 grid gap-3 text-sm sm:grid-cols-3" aria-label="Tahapan pemesanan">
        @foreach([['1', 'Kirim kebutuhan'], ['2', 'Diperiksa operator'], ['3', 'Konfirmasi & proses']] as [$number, $label])
            <li class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span class="grid size-8 place-items-center rounded-full bg-brand-50 font-black text-brand-700">{{ $number }}</span>
                <span class="font-bold text-ink-900">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    @if($accountUser)
        <div class="mt-7 flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 sm:flex-row sm:items-center sm:justify-between"><p><strong>Pesanan terhubung ke akun.</strong> Identitas pemesan menggunakan profil {{ $accountCustomer?->name ?? $accountUser->name }}.</p><a href="{{ route('customer.dashboard') }}" class="shrink-0 font-bold underline">Buka akun</a></div>
    @endif
    <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="post" action="{{ route('public.order.store') }}" enctype="multipart/form-data" class="card grid gap-5 sm:grid-cols-2" data-order-form>
            @csrf
            <input type="hidden" name="source" value="{{ old('source', $orderSource) }}">
            <div class="grid gap-1.5"><label>Nama</label><input name="customer_name" value="{{ old('customer_name', $accountCustomer?->name ?? $accountUser?->name) }}" @readonly($accountUser) required autocomplete="name"></div>
            <div class="grid gap-1.5"><label>Nomor WhatsApp</label><input name="customer_phone" value="{{ old('customer_phone', $accountCustomer?->phone ?? $accountUser?->phone) }}" @readonly($accountUser && filled($accountCustomer?->phone ?? $accountUser?->phone)) required autocomplete="tel" inputmode="tel"></div>
            <div class="grid gap-1.5"><label>Jenis layanan</label><select name="type" required data-order-type><option value="">Pilih layanan</option>@foreach(['print','fotokopi','scan','ketik','desain','website','aplikasi'] as $type)<option @selected(old('type')===$type) value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="grid gap-1.5"><label>Paket layanan</label><select name="service_id" data-order-service><option value="">Akan ditentukan operator</option>@foreach($services as $service)<option value="{{ $service->id }}" data-service-type="{{ $service->type }}" @selected((string) old('service_id', request('service_id')) === (string) $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="grid gap-1.5"><label>Ukuran kertas</label><select name="specifications[paper_size]"><option value="">Tidak berlaku / belum tahu</option>@foreach(['A4','F4','A3','A5'] as $size)<option>{{ $size }}</option>@endforeach</select></div>
            <div class="grid gap-1.5"><label>Mode warna</label><select name="specifications[color_mode]"><option value="">Belum ditentukan</option><option value="black_white">Hitam putih</option><option value="color">Warna</option></select></div>
            <div class="grid gap-1.5 sm:col-span-2"><label>Catatan dan spesifikasi</label><textarea name="notes" rows="4" placeholder="Jumlah rangkap, satu/dua sisi, finishing, deadline…">{{ old('notes') }}</textarea></div>
            <div class="grid gap-1.5 sm:col-span-2"><label>File (maksimal 20 MB)</label><input type="file" name="file"><p class="text-xs text-slate-500">PDF, Office, gambar, atau teks. Arsip ZIP dan file executable tidak diterima. File disimpan privat.</p></div>
            <div class="sticky bottom-3 z-10 rounded-2xl bg-white/95 py-2 backdrop-blur sm:static sm:col-span-2 sm:bg-transparent sm:py-0"><button class="btn-primary w-full sm:w-auto" data-order-submit>Kirim pesanan untuk diperiksa</button></div>
        </form>
        <aside class="card lg:sticky lg:top-24">
            <h2 class="text-lg font-black text-ink-900">Sebelum pesanan diproses</h2>
            <ul class="mt-4 grid gap-4 text-sm text-slate-600">
                <li><strong class="block text-ink-900">Harga dikonfirmasi lebih dulu</strong>Tidak ada pembayaran otomatis sebelum operator memeriksa kebutuhan.</li>
                <li><strong class="block text-ink-900">File disimpan privat</strong>File hanya dipakai untuk mengerjakan pesanan dan dilindungi akses aplikasi.</li>
                <li><strong class="block text-ink-900">Status dapat dipantau</strong>Nomor pelacakan diberikan setelah formulir berhasil dikirim.</li>
            </ul>
            <a href="https://wa.me/{{ config('services.whatsapp.number') }}?text={{ urlencode('Halo Younz Digital Center, saya ingin bertanya sebelum memesan.') }}" class="btn-secondary mt-6 w-full" rel="noopener">Tanya lewat WhatsApp</a>
        </aside>
    </div>
</main>
@endsection
