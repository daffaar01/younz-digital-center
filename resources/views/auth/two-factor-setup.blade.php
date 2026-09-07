@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-2xl">
    <p class="text-sm font-bold text-brand-700">KEAMANAN OWNER</p>
    <h1 class="mt-2 text-3xl font-black text-ink-900">Aktifkan autentikasi dua faktor</h1>
    <section class="card mt-6 space-y-5">
        <p class="text-sm leading-6 text-slate-600">Tambahkan akun berikut ke Google Authenticator, Microsoft Authenticator, 1Password, atau aplikasi TOTP lain. Secret disimpan terenkripsi.</p>
        <div><p class="text-xs font-bold uppercase text-slate-400">Secret manual</p><code class="mt-2 block break-all rounded-xl bg-slate-100 p-4 text-sm">{{ $secret }}</code></div>
        <details><summary class="cursor-pointer text-sm font-bold text-brand-700">Tampilkan URI konfigurasi</summary><code class="mt-2 block break-all rounded-xl bg-slate-100 p-4 text-xs">{{ $uri }}</code></details>
        <form method="post" action="{{ route('two-factor.setup.confirm') }}" class="space-y-3">
            @csrf
            <label for="code">Masukkan kode 6 digit untuk mengonfirmasi</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn-primary">Aktifkan 2FA</button>
        </form>
    </section>
</div>
@endsection
