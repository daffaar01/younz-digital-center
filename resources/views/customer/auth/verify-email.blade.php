@extends('layouts.public')
@section('content')
<main class="relative overflow-hidden bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <div class="absolute inset-0 public-grid-pattern opacity-70"></div>
    <section class="relative mx-auto max-w-xl rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.45)] sm:p-10">
        <p class="public-label">Keamanan akun</p>
        <h1 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[#132016]">Verifikasi email Anda</h1>
        <p class="mt-4 text-sm leading-7 text-slate-600">Kami telah mengirim tautan verifikasi ke <strong>{{ auth()->user()->email }}</strong>. Buka tautan tersebut sebelum mengakses portal pelanggan.</p>

        @if(session('status'))
            <p class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif

        <div class="mt-7 grid gap-3 sm:grid-cols-2">
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="public-btn-primary min-h-12 w-full">Kirim ulang email</button>
            </form>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="public-btn-dark min-h-12 w-full">Keluar</button>
            </form>
        </div>
        <p class="mt-5 text-xs leading-5 text-slate-500">Tidak menerima email? Periksa folder spam dan pastikan alamat email sudah benar.</p>
    </section>
</main>
@endsection
