@extends('layouts.public')
@section('content')
<main class="relative min-h-[70vh] overflow-hidden bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <div class="absolute inset-0 public-grid-pattern opacity-70"></div>
    <div class="relative mx-auto max-w-3xl rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.45)] sm:p-10">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="public-label">Profil pelanggan</p>
                <h1 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[#132016]">Lengkapi data pemesan</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-slate-500">Nomor WhatsApp diperlukan untuk konfirmasi harga, progres, dan pengambilan pesanan.</p>
            </div>
            @if(auth()->user()->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="Foto profil {{ auth()->user()->name }}" referrerpolicy="no-referrer" class="h-16 w-16 rounded-2xl border-4 border-emerald-50 object-cover">
            @endif
        </div>

        @if(filled(auth()->user()->firebase_phone))
            <div class="mt-7 flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-xs font-black text-emerald-700">SMS</span>
                <div class="min-w-0"><p class="text-xs font-bold text-emerald-900">Nomor Firebase terverifikasi</p><p class="truncate text-xs text-emerald-700/70">{{ auth()->user()->phone }}</p></div>
            </div>
        @else
            <div class="mt-7 flex items-center gap-3 rounded-2xl border border-blue-100 bg-blue-50 p-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white font-display font-black text-blue-600">G</span>
                <div class="min-w-0"><p class="text-xs font-bold text-blue-900">Akun Google terverifikasi</p><p class="truncate text-xs text-blue-700/70">{{ auth()->user()->email }}</p></div>
            </div>
        @endif

        <form method="post" action="{{ route('customer.profile.update') }}" class="mt-8 grid gap-5 sm:grid-cols-2">
            @csrf
            @method('PATCH')
            <div class="grid gap-2">
                <label for="profile-name">Nama lengkap</label>
                <input id="profile-name" name="name" value="{{ old('name', $customer?->name ?? auth()->user()->name) }}" autocomplete="name" required autofocus>
            </div>
            <div class="grid gap-2">
                <label for="profile-phone">Nomor WhatsApp</label>
                <input id="profile-phone" name="phone" type="tel" value="{{ old('phone', $customer?->phone ?? auth()->user()->phone) }}" autocomplete="tel" inputmode="numeric" placeholder="08219207240" required>
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <label for="profile-address">Alamat <span class="font-normal text-slate-400">(opsional)</span></label>
                <textarea id="profile-address" name="address" rows="3" placeholder="Alamat pengantaran atau domisili">{{ old('address', $customer?->address) }}</textarea>
            </div>
            <label class="flex items-start gap-3 font-normal text-slate-600 sm:col-span-2">
                <input class="mt-1 h-4 w-4 shrink-0" type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent', $customer?->marketing_consent))>
                <span class="text-sm leading-6">Saya bersedia menerima informasi layanan atau promo melalui WhatsApp (opsional).</span>
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row">
                <button class="public-btn-dark min-h-12 flex-1">Simpan dan lanjutkan</button>
                @if(filled($customer?->phone))<a href="{{ route('customer.dashboard') }}" class="public-btn-primary min-h-12 flex-1">Kembali ke akun</a>@endif
            </div>
        </form>
    </div>
</main>
@endsection
