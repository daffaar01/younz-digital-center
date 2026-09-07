@php
    $showRegister = ($authMode ?? 'login') === 'register';
@endphp

<main class="customer-auth-page">
    <div class="customer-auth-backdrop public-grid-pattern" aria-hidden="true"></div>

    <section
        class="customer-auth-shell {{ $showRegister ? 'is-register' : '' }}"
        data-customer-auth-shell
        data-auth-mode="{{ $showRegister ? 'register' : 'login' }}"
        data-login-url="{{ route('customer.login') }}"
        data-register-url="{{ route('customer.register') }}"
        aria-label="Akses akun pelanggan Younz Digital Center"
    >
        <div class="customer-auth-form customer-auth-form-login" data-auth-panel="login" @if($showRegister) inert @endif>
            <form method="post" action="{{ route('customer.login.store') }}" class="customer-auth-form-inner">
                @csrf
                <div class="text-center">
                    <p class="public-label">Portal pelanggan</p>
                    <h1 class="customer-auth-heading">Masuk ke akun</h1>
                    <p class="customer-auth-subtitle">Pantau pesanan dan layanan Anda dalam satu tempat.</p>
                </div>

                @include('customer.auth.firebase-button', ['firebaseLabel' => 'Masuk dengan Google'])

                <div class="customer-auth-input">
                    <label class="sr-only" for="customer-email">Email</label>
                    <input
                        id="customer-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        placeholder="Email pelanggan"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        required
                    >
                    
                </div>
                @error('email')<p class="customer-auth-field-error">{{ $message }}</p>@enderror

                <div class="customer-auth-input customer-auth-password" data-password-field>
                    <label class="sr-only" for="customer-password">Password</label>
                    <input id="customer-password" data-password-input name="password" type="password" autocomplete="current-password" placeholder="Password" required>
                    <span class="customer-password-beam" data-password-beam aria-hidden="true"></span>
                    <button class="customer-password-toggle" type="button" data-password-toggle aria-controls="customer-password" aria-pressed="false">
                        <span class="sr-only" data-password-toggle-label>Tampilkan password</span>
                        <span class="customer-password-eye" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="customer-auth-options">
                    <label class="customer-auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ingat saya</span>
                    </label>
                    <a href="{{ route('password.request') }}">Lupa password?</a>
                </div>

                <button class="customer-auth-primary" type="submit">Masuk ke akun</button>
                <p class="customer-auth-staff-link">Anda pegawai Younz? <a href="{{ route('login') }}">Login Pegawai</a></p>
            </form>
        </div>

        <div class="customer-auth-form customer-auth-form-register" data-auth-panel="register" @unless($showRegister) inert @endunless>
            <form method="post" action="{{ route('customer.register.store') }}" class="customer-auth-form-inner customer-auth-register-form">
                @csrf
                <div class="text-center">
                    <p class="public-label">Pelanggan baru</p>
                    <h1 class="customer-auth-heading">Buat akun</h1>
                    <p class="customer-auth-subtitle">Daftar sekali, lalu kelola semua pesanan lebih mudah.</p>
                </div>

                @include('customer.auth.firebase-button', ['firebaseLabel' => 'Daftar dengan Google'])

                <div class="customer-auth-register-grid">
                    <div>
                        <div class="customer-auth-input">
                            <label class="sr-only" for="register-name">Nama lengkap</label>
                            <input id="register-name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Nama lengkap" required>
                            
                        </div>
                        @error('name')<p class="customer-auth-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="customer-auth-input">
                            <label class="sr-only" for="register-phone">Nomor WhatsApp</label>
                            <input id="register-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="numeric" placeholder="Nomor WhatsApp" required>
                            
                        </div>
                        @error('phone')<p class="customer-auth-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="customer-auth-register-wide">
                        <div class="customer-auth-input">
                            <label class="sr-only" for="register-email">Email</label>
                            <input id="register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="Email pelanggan" required>
                            
                        </div>
                        @error('email')<p class="customer-auth-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="customer-auth-input customer-auth-password" data-password-field>
                            <label class="sr-only" for="register-password">Password</label>
                            <input id="register-password" data-password-input name="password" type="password" autocomplete="new-password" minlength="12" placeholder="Password min. 12 karakter" required>
                            <span class="customer-password-beam" data-password-beam aria-hidden="true"></span>
                            <button class="customer-password-toggle" type="button" data-password-toggle aria-controls="register-password" aria-pressed="false">
                                <span class="sr-only" data-password-toggle-label>Tampilkan password</span>
                                <span class="customer-password-eye" aria-hidden="true"></span>
                            </button>
                        </div>
                        @error('password')<p class="customer-auth-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="customer-auth-input customer-auth-password" data-password-field>
                            <label class="sr-only" for="register-password-confirmation">Ulangi password</label>
                            <input id="register-password-confirmation" data-password-input name="password_confirmation" type="password" autocomplete="new-password" placeholder="Ulangi password" required>
                            <span class="customer-password-beam" data-password-beam aria-hidden="true"></span>
                            <button class="customer-password-toggle" type="button" data-password-toggle aria-controls="register-password-confirmation" aria-pressed="false">
                                <span class="sr-only" data-password-toggle-label>Tampilkan password</span>
                                <span class="customer-password-eye" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <label class="customer-auth-check customer-auth-consent">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                    <span>Saya menyetujui <a href="{{ route('privacy') }}">Kebijakan Privasi</a> dan <a href="{{ route('terms') }}">Syarat Layanan</a>.</span>
                </label>
                @error('terms')<p class="customer-auth-field-error">{{ $message }}</p>@enderror

                <label class="customer-auth-check customer-auth-consent">
                    <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent'))>
                    <span>Terima informasi layanan melalui WhatsApp (opsional).</span>
                </label>

                <button class="customer-auth-primary" type="submit">Buat akun pelanggan</button>
            </form>
        </div>

        <div class="customer-auth-toggle" aria-live="polite">
            <div class="customer-auth-toggle-panel customer-auth-toggle-login">
                <x-brand-logo class="customer-auth-toggle-logo" />
                <h2>Halo, selamat datang!</h2>
                <p>Belum punya akun pelanggan?</p>
                <button type="button" data-customer-auth-toggle="register">Daftar sekarang</button>
            </div>

            <div class="customer-auth-toggle-panel customer-auth-toggle-register">
                <x-brand-logo class="customer-auth-toggle-logo" />
                <h2>Selamat datang kembali!</h2>
                <p>Sudah memiliki akun pelanggan?</p>
                <button type="button" data-customer-auth-toggle="login">Masuk sekarang</button>
            </div>
        </div>
    </section>
</main>
