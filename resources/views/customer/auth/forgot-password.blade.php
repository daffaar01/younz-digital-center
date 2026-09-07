@php($title = 'Lupa Password · Younz Digital Center')
@extends('layouts.public')
@section('content')
<main class="relative overflow-hidden bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <div class="absolute inset-0 public-grid-pattern opacity-70"></div>
    <section class="relative mx-auto max-w-lg rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.45)] sm:p-10">
        <p class="public-label">Pemulihan akun</p>
        <h1 class="mt-3 font-display text-3xl font-extrabold text-[#132016]">Reset password pelanggan</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Masukkan email akun. Jika terdaftar, kami akan mengirim tautan reset melalui email.</p>
        <form method="post" action="{{ route('password.email') }}" class="mt-7 space-y-5">
            @csrf
            <div class="grid gap-2"><label for="reset-request-email">Email</label><input id="reset-request-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
            <button class="public-btn-dark min-h-12 w-full">Kirim tautan reset</button>
        </form>
        <a href="{{ route('customer.login') }}" class="mt-5 inline-flex min-h-11 items-center text-sm font-bold text-[#006c49] hover:underline">Kembali ke login</a>
    </section>
</main>
@endsection
