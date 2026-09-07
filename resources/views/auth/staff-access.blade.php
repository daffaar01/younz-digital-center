<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Gerbang Akses Pegawai · Younz Digital Center</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f7f9fb] text-[#191c1e]">
<main class="customer-auth-page staff-gate-page">
    <div class="customer-auth-backdrop public-grid-pattern" aria-hidden="true"></div>

    <section class="staff-auth-shell staff-gate-shell" aria-label="Gerbang akses admin Younz Digital Center">
        <aside class="staff-auth-intro">
            <x-brand-logo class="staff-auth-logo" />
            <p class="staff-auth-badge">Gerbang admin</p>
            <h2>Satu kode sebelum<br><span>login pegawai.</span></h2>
            <p class="staff-auth-intro-copy">Lapisan awal untuk membatasi akses ke portal internal Younz Digital Center.</p>

            <ul class="staff-auth-benefits" aria-label="Lapisan keamanan portal admin">
                <li>
                    <span>01</span>
                    <div><strong>Kode akses awal</strong><small>Hanya dibagikan kepada tim yang berwenang.</small></div>
                </li>
                <li>
                    <span>02</span>
                    <div><strong>Password pegawai</strong><small>Tetap diperlukan setelah gerbang terbuka.</small></div>
                </li>
                <li>
                    <span>03</span>
                    <div><strong>Autentikasi 2FA</strong><small>Verifikasi terakhir memakai kode autentikator.</small></div>
                </li>
            </ul>
        </aside>

        <div class="staff-auth-form">
            <form method="post" action="{{ route('staff.access.verify') }}" class="staff-auth-form-inner">
                @csrf
                <div class="text-center">
                    <span class="staff-gate-shield" aria-hidden="true">
                        
                    </span>
                    <p class="public-label">Akses terbatas</p>
                    <h1 class="customer-auth-heading">Masukkan kode akses</h1>
                    <p class="customer-auth-subtitle">Gunakan kode yang diberikan langsung oleh owner.</p>
                </div>

                <div class="customer-auth-input customer-auth-password staff-gate-input" data-password-field>
                    <label class="sr-only" for="staff-access-code">Kode akses</label>
                    <input
                        id="staff-access-code"
                        data-password-input
                        name="access_code"
                        type="password"
                        autocomplete="one-time-code"
                        autocapitalize="characters"
                        spellcheck="false"
                        maxlength="128"
                        placeholder="Kode akses"
                        aria-invalid="{{ $errors->has('access_code') ? 'true' : 'false' }}"
                        required
                        autofocus
                    >
                    <span class="customer-password-beam" data-password-beam aria-hidden="true"></span>
                    <button class="customer-password-toggle" type="button" data-password-toggle aria-controls="staff-access-code" aria-pressed="false">
                        <span class="sr-only" data-password-toggle-label>Tampilkan kode akses</span>
                        <span class="customer-password-eye" aria-hidden="true"></span>
                    </button>
                </div>
                @error('access_code')<p class="customer-auth-field-error staff-gate-error" role="alert">{{ $message }}</p>@enderror

                <button class="customer-auth-primary staff-gate-submit" type="submit">Buka portal pegawai</button>

                <p class="staff-auth-security-note">Percobaan dibatasi dan dicatat. Kode ini bukan pengganti password maupun autentikasi dua faktor.</p>
                <a class="staff-gate-back" href="{{ config('app.url') }}">← Kembali ke situs utama</a>
            </form>
        </div>
    </section>
</main>
</body>
</html>
