@extends('layouts.public')
@section('content')
<main class="mx-auto max-w-lg px-4 py-16 sm:px-6">
    <section class="card">
        <p class="text-sm font-bold text-brand-700">VERIFIKASI OWNER</p>
        <h1 class="mt-2 text-3xl font-black text-ink-900">Masukkan kode autentikator</h1>
        <p class="mt-2 text-sm text-slate-500">Gunakan kode 6 digit terbaru dari aplikasi autentikator Anda.</p>
        <form method="post" action="{{ route('two-factor.challenge.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div class="grid gap-1.5"><label for="code">Kode 6 digit</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></div>
            <button class="btn-primary w-full">Verifikasi dan masuk</button>
        </form>
    </section>
</main>
@endsection
