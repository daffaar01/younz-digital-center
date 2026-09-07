@php($title = 'Buat Password Baru · Younz Digital Center')
@extends('layouts.public')
@section('content')
<main class="relative overflow-hidden bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <div class="absolute inset-0 public-grid-pattern opacity-70"></div>
    <section class="relative mx-auto max-w-lg rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.45)] sm:p-10">
        <p class="public-label">Keamanan akun</p>
        <h1 class="mt-3 font-display text-3xl font-extrabold text-[#132016]">Buat password baru</h1>
        <form method="post" action="{{ route('password.update') }}" class="mt-7 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="grid gap-2"><label for="reset-email">Email</label><input id="reset-email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required></div>
            <div class="grid gap-2"><label for="reset-password">Password baru</label><input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="12" required><p class="text-xs text-slate-400">Minimal 12 karakter, berisi huruf besar, huruf kecil, dan angka.</p></div>
            <div class="grid gap-2"><label for="reset-password-confirmation">Ulangi password</label><input id="reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="public-btn-dark min-h-12 w-full">Simpan password baru</button>
        </form>
    </section>
</main>
@endsection
