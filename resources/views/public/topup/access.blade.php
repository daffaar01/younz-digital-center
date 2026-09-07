@extends('layouts.public')

@php
    $title = 'Akses Transaksi Top Up · Younz Digital Center';
    $description = 'Buat ulang tautan aman untuk melihat status, struk, atau invoice transaksi top up.';
@endphp

@section('content')
<main class="bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <section class="mx-auto max-w-xl rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.35)] sm:p-10">
        <p class="public-label">Akses transaksi aman</p>
        <h1 class="mt-3 font-display text-4xl font-extrabold text-[#132016]">Buka kembali transaksi Anda.</h1>
        <p class="mt-4 text-sm leading-7 text-slate-500">Masukkan data yang sama seperti saat checkout. Jika cocok, kami akan membuat tautan status baru yang berlaku selama {{ max(1, min(30, (int) config('services.topup.access_link_days', 7))) }} hari.</p>

        @if($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('topup.access.resolve') }}" class="mt-8 space-y-5">
            @csrf
            <div><label for="order_number" class="form-label">Nomor transaksi</label><input id="order_number" name="order_number" value="{{ old('order_number') }}" required autocomplete="off" placeholder="TOP-20260722-0001" class="form-input mt-2 w-full"></div>
            <div><label for="customer_email" class="form-label">Email checkout</label><input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" required autocomplete="email" placeholder="nama@email.com" class="form-input mt-2 w-full"></div>
            <div><label for="customer_phone" class="form-label">Nomor WhatsApp checkout</label><input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" required inputmode="tel" autocomplete="tel" placeholder="0821xxxxxxxx" class="form-input mt-2 w-full"></div>
            <button type="submit" class="public-btn-dark w-full">Buat tautan akses baru <span>→</span></button>
        </form>
    </section>
</main>
@endsection
