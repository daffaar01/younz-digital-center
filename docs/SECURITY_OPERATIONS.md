# Security Operations — Younz Digital Center

Dokumen ini mencatat kontrol keamanan yang wajib dipertahankan untuk aplikasi produksi.

## Pemeriksaan rutin

Jalankan setelah mengubah `.env`, dependency, autentikasi, pembayaran, atau tunnel:

```powershell
php artisan younz:security-audit
composer audit --locked
npm audit --audit-level=high
php artisan test
php vendor/bin/phpstan analyse --memory-limit=1G
```

`younz:security-audit` juga dijadwalkan setiap hari pukul 01:30. Kegagalan dikirim ke `SECURITY_ALERT_EMAIL` bila SMTP tersedia. Backup terenkripsi berjalan pukul 02:00.

## Kontrol aplikasi aktif

- Laravel hanya menerima host dalam `TRUSTED_HOSTS` untuk mencegah Host-header poisoning pada URL reset password.
- Portal pegawai hanya menerima login dan route dashboard pada `STAFF_URL`; akses dari domain publik dialihkan ke `admin.younzdigitalcenter.my.id` dan POST pada host yang salah ditolak.
- Login dibatasi berlapis berdasarkan kombinasi email+IP, email, dan IP.
- Register, reset password, TOTP, Firebase, top-up, webhook, dan API mempunyai limiter terpisah.
- Seluruh akun pegawai wajib mengaktifkan TOTP; sesi remember-me tidak melewati pemeriksaan TOTP.
- Password baru menggunakan Argon2id dan minimal 12 karakter. Password pegawai juga wajib memiliki simbol.
- Session terenkripsi, Secure, HttpOnly, SameSite=Lax, dan host-only.
- Callback Midtrans diverifikasi dengan SHA-512, callback Digiflazz dengan HMAC, dan transaksi diproses idempoten.
- Upload dipindai menggunakan antivirus dan gagal tertutup saat scanner tidak tersedia.
- Audit log immutable meredaksi password, token, secret, API key, authorization, cookie, dan data kartu.
- CSP, HSTS, frame protection, MIME sniffing protection, Referrer Policy, dan Permissions Policy aktif.

SameSite tetap `Lax`, bukan `Strict`, karena tautan verifikasi/reset yang dibuka dari Gmail harus masih dapat membawa sesi pada navigasi tingkat atas.

## Cloudflare — kontrol produksi aktif

Konfigurasi berikut diterapkan pada 21 Juli 2026 untuk paket Free:

1. Cloudflare Managed Ruleset aktif otomatis dan tidak dapat dinonaktifkan. Ruleset OWASP terpisah memerlukan paket Pro, sehingga tidak tersedia pada paket saat ini.
2. Custom WAF rule `Younz - Blokir probe file sensitif` aktif dengan tindakan Block untuk probe `.env`, `.git`, `vendor`, `_ignition`, `telescope`, `composer.json`, lockfile dependency, `phpinfo.php`, `server-status`, dan `artisan`.
3. Rate limiting rule `Younz - Proteksi endpoint sensitif` aktif untuk POST autentikasi, pemesanan, top-up, Tanya AI, dan API. Batas paket Free adalah 10 request per 10 detik per IP, lalu Block selama 10 detik.
4. `/webhooks/midtrans` dan `/webhooks/digiflazz` sengaja tidak dimasukkan ke rate limit edge. Keduanya tetap POST-only, dibatasi Laravel, dan signature provider tetap menjadi otoritas.
5. `Always Use HTTPS` aktif, minimum TLS adalah 1.2, TLS 1.3 aktif, Universal SSL aktif, dan mode enkripsi origin dikelola otomatis pada mode Full untuk Cloudflare Tunnel.
6. Public hostname `admin.younzdigitalcenter.my.id` aktif pada Tunnel dan mengarah ke origin loopback yang sama. Aplikasi tetap memisahkan sesi pelanggan dan pegawai menggunakan cookie host-only.
7. Audit Security Events terakhir pada 21 Juli 2026 menunjukkan satu request diblokir oleh custom rule dalam 24 jam terakhir. Pantau halaman ini setelah perubahan route; endpoint POST sensitif baru harus ditambahkan ke rate limiting rule bila limiter aplikasi tidak memadai.
8. Cloudflare Access belum aktif karena aktivasi Zero Trust Free meminta metode pembayaran dan otorisasi penagihan bila penggunaan melewati batas gratis. Setelah pemilik menyetujui checkout, buat aplikasi self-hosted hanya untuk `admin.younzdigitalcenter.my.id` dengan allow-list email pegawai. Jangan mengaktifkan Access pada domain utama karena akan memblokir pelanggan dan webhook.

Jangan memblokir User-Agent `curl`, `wget`, atau `python` secara global. User-Agent mudah dipalsukan dan aturan tersebut dapat memutus webhook, uptime monitor, serta integrasi yang sah.

## Infrastruktur produksi aktif

- PostgreSQL 18 berjalan hanya pada `127.0.0.1`/`::1`, menggunakan SCRAM-SHA-256 dan role aplikasi `younz_app` tanpa hak superuser, create database, create role, atau replication. Password aplikasi dibaca dari file ACL-terbatas di `C:\ProgramData\YounzDigitalCenter\secrets`.
- Migrasi final dari SQLite selesai dengan 38 tabel dan 391 baris terverifikasi. File SQLite lama dipertahankan hanya sebagai sumber rollback dan tidak lagi menjadi database aktif.
- Origin memakai FrankenPHP pada `127.0.0.1:8092`. Origin, queue worker, scheduler, PostgreSQL, dan Cloudflare Tunnel berjalan sebagai Windows Service otomatis.
- PHP bawaan FrankenPHP wajib memakai CA bundle resmi pada `C:\Program Files\YounzDigitalCenter\FrankenPHP\cacert.pem`. `curl.cainfo` dan `openssl.cafile` di `php.ini` harus menunjuk ke file tersebut agar koneksi Midtrans dan provider lain tetap memverifikasi sertifikat TLS. Jangan pernah menggantinya dengan `verify=false` atau mode insecure.
- Token Tunnel disimpan pada file ACL-terbatas `C:\ProgramData\Cloudflared\younzdigitalcenter.token`; service memakai `--token-file`. Rotasi token harus selalu diikuti restart service dan pemeriksaan `/up`.
- Backup PostgreSQL terenkripsi, diverifikasi setelah ditulis, dan dirotasi setiap hari. Salinan lokal tetap dibuat lebih dahulu. Dukungan mirror R2 memakai API S3-compatible langsung melalui SDK AWS yang sudah terpasang.

## Backup otomatis ke Google Drive

Backup pukul 02:00 dapat disalin otomatis ke Google Drive tanpa mengaktifkan R2. File yang dikirim tetap berupa hasil terenkripsi (`.enc`), diunduh kembali untuk verifikasi checksum, dan dirotasi mengikuti `BACKUP_RETENTION_DAYS`. Jika Google Drive gagal, salinan lokal tetap ada tetapi job berstatus gagal dan mengirim peringatan ke `SECURITY_ALERT_EMAIL` bila SMTP tersedia.

Aktivasi OAuth dilakukan satu kali:

1. Di Google Cloud Console, pilih project milik Younz lalu aktifkan **Google Drive API**.
2. Pada Google Auth Platform, lengkapi Branding/Audience. Mode **Testing** hanya boleh dipakai untuk uji awal karena refresh token Drive akan kedaluwarsa setelah 7 hari. Sebelum dipakai scheduler, ubah Publishing status menjadi **In production**, lalu terbitkan refresh token baru.
3. Buat OAuth Client bertipe **Web application**. Tambahkan `https://developers.google.com/oauthplayground` sebagai Authorized redirect URI.
4. Buka Google OAuth 2.0 Playground, aktifkan **Use your own OAuth credentials**, isi Client ID dan Client Secret, lalu otorisasi scope `https://www.googleapis.com/auth/drive.file` dengan offline access. Tukarkan authorization code dan salin refresh token.
5. Isi `.env` tanpa tanda kutip tambahan:

```dotenv
GOOGLE_DRIVE_BACKUP_ENABLED=true
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=
GOOGLE_DRIVE_FOLDER_NAME="Younz Digital Center Backups"
GOOGLE_DRIVE_TIMEOUT=120
```

`GOOGLE_DRIVE_FOLDER_ID` boleh kosong. Pada backup pertama aplikasi akan membuat folder **Younz Digital Center Backups** sendiri. Hal ini cocok dengan scope sempit `drive.file`, yang hanya memberi akses ke file yang dibuat atau dipilih melalui aplikasi. Jika ID folder diisi manual, folder tersebut harus sudah dapat diakses oleh OAuth client.

Setelah `.env` lengkap:

```powershell
php artisan optimize
php artisan younz:security-audit
php artisan younz:backup
Get-Content storage\logs\backup.log -Tail 50
```

Pastikan output memuat `Google Drive terverifikasi`, lalu cek file `.enc` di Google Drive. Jangan pernah mengirim atau commit Client Secret dan Refresh Token.

## Operasi layanan

```powershell
Get-Service YounzOrigin,YounzQueue,YounzScheduler,Cloudflared,postgresql-x64-18
Restart-Service YounzOrigin
Restart-Service YounzQueue
Restart-Service YounzScheduler
php artisan younz:backup
```

Jangan memakai `php artisan queue:restart` sebagai satu-satunya prosedur deployment karena worker dibungkus Windows Service. Setelah perubahan kode/config, jalankan `php artisan optimize`, lalu `Restart-Service YounzOrigin,YounzQueue,YounzScheduler`.

## Pekerjaan yang memerlukan persetujuan billing

- R2 belum diaktifkan karena dashboard meminta subscription. Google Drive dapat menjadi salinan off-site tanpa R2. Jika R2 tetap dipilih, buat bucket privat `younz-backups`, token API bucket-scoped Read/Write, isi `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, lalu ubah `BACKUP_R2_ENABLED=true` dan uji `php artisan younz:backup`.
- Cloudflare Access/Zero Trust menunggu persetujuan checkout seperti dijelaskan di atas.
- Proteksi bot agresif tetap tidak diaktifkan karena dapat mengganggu webhook dan integrasi server-ke-server pada paket Free.
