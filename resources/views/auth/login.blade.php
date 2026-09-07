@extends('layouts.public')

@section('content')
<main class="customer-auth-page staff-auth-page">
    <div class="customer-auth-backdrop public-grid-pattern" aria-hidden="true"></div>

    <section class="staff-auth-shell" aria-label="Akses akun pegawai Younz Digital Center">
        <aside class="staff-auth-intro">
            <x-brand-logo class="staff-auth-logo" />
            <span class="sr-only">Logo Younz Digital Center</span>
            <p class="staff-auth-badge">Portal internal</p>
            <h2>Akses operasional,<br><span>tetap terlindungi.</span></h2>
            <p class="staff-auth-intro-copy">Ruang kerja khusus owner dan pegawai untuk mengelola layanan Younz Digital Center.</p>

            <ul class="staff-auth-benefits" aria-label="Keamanan portal pegawai">
                <li>
                    <span>01</span>
                    <div><strong>Akses sesuai peran</strong><small>Menu disesuaikan dengan wewenang akun.</small></div>
                </li>
                <li>
                    <span>02</span>
                    <div><strong>Verifikasi dua faktor</strong><small>Login pegawai dilindungi kode autentikator.</small></div>
                </li>
                <li>
                    <span>03</span>
                    <div><strong>Aktivitas tercatat</strong><small>Akses penting masuk ke audit keamanan.</small></div>
                </li>
            </ul>
        </aside>

        <div class="staff-auth-form">
            <form method="post" action="{{ route('login') }}" class="staff-auth-form-inner">
                @csrf
                <div class="text-center">
                    <p class="public-label">Portal pegawai</p>
                    <h1 class="customer-auth-heading">Masuk ke dashboard</h1>
                    <p class="customer-auth-subtitle">Gunakan akun pegawai yang dibuat dan diaktifkan oleh owner.</p>
                </div>

                <div class="customer-auth-input">
                    <label class="sr-only" for="staff-email">Email pegawai</label>
                    <input
                        id="staff-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        placeholder="Email pegawai"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        required
                        autofocus
                    >
                    
                </div>
                @error('email')<p class="customer-auth-field-error" role="alert">{{ $message }}</p>@enderror

                <div class="customer-auth-input customer-auth-password" data-password-field>
                    <label class="sr-only" for="staff-password">Password</label>
                    <input
                        id="staff-password"
                        data-password-input
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        required
                    >
                    <span class="customer-password-beam" data-password-beam aria-hidden="true"></span>
                    <button class="customer-password-toggle" type="button" data-password-toggle aria-controls="staff-password" aria-pressed="false">
                        <span class="sr-only" data-password-toggle-label>Tampilkan password</span>
                        <span class="customer-password-eye" aria-hidden="true"></span>
                    </button>
                </div>
                @error('password')<p class="customer-auth-field-error" role="alert">{{ $message }}</p>@enderror

                <div class="customer-auth-options staff-auth-options">
                    <label class="customer-auth-check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Ingat saya</span>
                    </label>
                    <span class="staff-auth-2fa"><span aria-hidden="true"></span>Dilindungi 2FA</span>
                </div>

                <button class="customer-auth-primary" type="submit">Masuk sebagai pegawai</button>

                <p class="staff-auth-security-note">
                    Setelah password diverifikasi, akun pegawai melanjutkan ke autentikasi dua faktor.
                </p>

                <p class="customer-auth-staff-link">Anda pelanggan? <a href="{{ route('customer.login') }}">Masuk ke akun pelanggan</a></p>
            </form>
        </div>
    </section>
</main>
@endsection
