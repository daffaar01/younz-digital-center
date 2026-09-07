# Younz Digital Center

Platform bisnis digital Younz Digital Center — print, fotokopi, desain, website, aplikasi, ATK, dan top-up — dalam satu **monorepo**: backend Laravel, frontend Next.js, gateway WhatsApp, dan bot Discord.

| Komponen | Teknologi | Lokasi | Peran |
|---|---|---|---|
| **Backend** | Laravel 12 · PHP 8.2 · Livewire 4 · Sanctum | `./` (root) | API, kasir, stok, pesanan jasa, top-up, approval, laporan, Younz AI |
| **Frontend** | Next.js 16 · React 19 · Tailwind CSS | `frontend/` | Website publik, flow top-up, halaman admin |
| **WhatsApp Gateway** | Node.js · Baileys | `whatsapp-gateway/` | Notifikasi WhatsApp & struk pembayaran |
| **Discord Bot** | Node.js · discord.js 14 | `discord-bot/` | Notifikasi bisnis, `/younzai`, tiket, moderasi |

## Arsitektur Monorepo

```
                    HTTPS          /api/*, /backend/*
┌─────────────┐  ──────────────▶  ┌──────────────────┐
│   Frontend  │   /topup/status   │     Backend      │
│  Next.js 16 │                   │   Laravel 12     │
└─────────────┘                   │    PHP 8.2       │
                                  └───┬──────────┬───┘
                         webhook push │          │ internal HTTP
                                      ▼          ▼
                              ┌──────────────┐ ┌────────────────┐
                              │ Discord Bot  │ │ WhatsApp GW    │
                              │ discord.js   │ │ Baileys + QR   │
                              └──────────────┘ └────────────────┘
```

- **Frontend** → memanggil API backend (`LARAVEL_API_URL`); route `/api/v1/*` dan `/webhooks/*` di-rewrite ke backend di `next.config.mjs`.
- **Backend** → mengirim notifikasi ke Discord (webhook HTTP + token bersama) dan ke WhatsApp gateway (token internal).
- **Discord Bot** → menerima webhook dari Laravel, membaca ringkasan dashboard via Sanctum token, dan mem-follow signed URL status top-up ke backend langsung (`YOUNZ_BACKEND_URL`).

## Persyaratan

- PHP **8.2+** dan Composer 2
- Node.js **≥ 20** (22 disarankan) untuk `frontend/`, `discord-bot/`, `whatsapp-gateway/`
- MariaDB/MySQL untuk development; CI memakai PostgreSQL 17
- Redis opsional (cache/queue) untuk production

## Setup Lokal

### 1. Backend

```bash
composer install
cp .env.example .env          # isi DB_* dan kunci integrasi
php artisan key:generate
php artisan migrate --seed
npm install && npm run build  # aset Vite
composer run dev              # server + queue + pail + vite sekaligus
```

Aplikasi tersedia di `http://localhost:8000`. Buat database `younz_digital_center` di MariaDB lalu set di `.env`:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_DATABASE=younz_digital_center
DB_USERNAME=root
DB_PASSWORD=
```

> `composer run setup` menjalankan langkah-langkah di atas secara otomatis.

### 2. Frontend (pengembangan UI)

```bash
cd frontend && npm install
```

Buat `frontend/.env.local`:

```dotenv
LARAVEL_API_URL=http://localhost:8000     # arahkan ke backend Laravel
SITE_URL=http://localhost:3000
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

```bash
npm run dev
```

### 3. WhatsApp Gateway

```bash
cd whatsapp-gateway && npm install
```

Buat `whatsapp-gateway/.env` (nilai `WHATSAPP_INTERNAL_TOKEN` harus sama dengan backend):

```dotenv
PORT=3000
WHATSAPP_INTERNAL_TOKEN=token-rahasia-bersama-minimal-32-karakter
WHATSAPP_AUTH_DIR=./data/auth
WHATSAPP_WEBHOOK_URL=https://younzdigitalcenter.my.id/webhooks/whatsapp/messages
LOG_LEVEL=info
```

```bash
npm start
```

Status dan QR dibaca melalui endpoint gateway yang terautentikasi atau halaman admin WhatsApp. Di HP, buka WhatsApp → Perangkat tertaut → Tautkan perangkat, lalu scan QR tersebut.

Untuk startup otomatis sebelum login Windows, gunakan [installer Windows service WhatsApp](docs/whatsapp-windows-service.md). Installer mempertahankan sesi lama dan menonaktifkan task logon lama agar tidak berjalan ganda.

Worker job production harus menggunakan antrean database. Lihat [pemulihan service YounzQueue](docs/younz-queue-service.md).

### 4. Discord Bot

```bash
cd discord-bot && npm install
cp .env.example .env          # isi DISCORD_TOKEN, channel IDs, dll.
node src/deploy-commands.js   # daftarkan slash command ke guild
npm run serve
```

Bot membuka server webhook di `SERVER_PORT` (default `3200`). Set di `.env` backend:

```dotenv
DISCORD_NOTIFY_ENABLED=true
DISCORD_BOT_WEBHOOK_URL=http://localhost:3200   # Laravel menambahkan /notify otomatis
DISCORD_BOT_WEBHOOK_TOKEN=token-sama-dengan-WEBHOOK_TOKEN-bot
```

Detail lengkap bot: [discord-bot/README.md](discord-bot/README.md).

## Kualitas & Pengujian

| Check | Command |
|---|---|
| Backend unit/feature test | `php artisan test` |
| Backend style | `vendor/bin/pint --test` |
| Backend static analysis | `composer analyse` (PHPStan) |
| Backend dependency audit | `composer audit` |
| Frontend typecheck | `npm run typecheck --prefix frontend` |
| Frontend build | `npm run build --prefix frontend` |
| Frontend boundary check | `npm run check:boundary --prefix frontend` |
| Discord bot test | `npm test --prefix discord-bot` |
| Browser E2E (Playwright) | `npx playwright test` |

Semua check di atas dijalankan otomatis oleh GitHub Actions (`.github/workflows/ci.yml` dan `quality.yml`).

### Isolasi database pengujian

`php artisan test`, `composer test`, dan `php vendor/bin/phpunit` memakai `tests/bootstrap.php`
serta `tests/.env.testing`, bukan `.env` utama. Database test selalu **SQLite `:memory:`**:
data hanya hidup di proses PHP test dan tidak membutuhkan database MariaDB khusus.
Cache konfigurasi/rute, log, dan storage test berada dalam folder sementara unik
`younz-tests-*` di direktori temp sistem, terpisah dari file aplikasi yang berjalan.
`composer test` tidak lagi menghapus cache konfigurasi aplikasi utama.

Sebelum `RefreshDatabase` atau migrasi berjalan, bootstrap memeriksa lingkungan test.
Factory koneksi test menolak koneksi MariaDB/MySQL/PostgreSQL, SQLite berbasis file,
URL database, serta override read/write. Koneksi tambahan untuk fixture hanya boleh
SQLite `:memory:`. Permintaan HTTP yang belum dipalsukan dengan `Http::fake()` juga ditolak.
Pengaman ini berlaku pada koneksi Laravel di test, bukan sandbox untuk kode PHP arbitrer;
jangan membuat koneksi PDO langsung atau memanggil CLI database production dari test.

Periksa pengaman dengan `php artisan test --filter TestDatabaseIsolationTest`.
Jika muncul `TEST DATABASE SAFETY`, perbaiki konfigurasi/fixture test; jangan menonaktifkan
pengaman atau mengarahkan test ke database utama. Suite ini belum menjalankan integrasi
khusus MariaDB/PostgreSQL; profil integrasi tersebut memerlukan database disposable dan
pengaman terpisah.

## Deploy

### Production saat ini (Windows — FrankenPHP)

- Tiga service Windows: `YounzOrigin` (web), `YounzQueue` (queue worker), `YounzScheduler` (scheduler `schedule:work`)
- PHP: `C:\Program Files\YounzDigitalCenter\FrankenPHP\php.exe`
- Script operasional di `scripts/`: reset kredensial staff, cek runtime, rekonsiliasi top-up, test login

### VPS / Linux

- `docs/upcloud-initialization.sh` — bootstrap VPS UpCloud (user deploy, SSH key, Docker, nginx, cloudflared)
- `deploy/cloudflared.service` — Cloudflare Tunnel ke domain publik
- `deploy/nginx-younz.conf` — reverse proxy + TLS Let's Encrypt

### Checklist production

- `APP_ENV=production`, `APP_DEBUG=false`; generate `APP_KEY` hanya saat instalasi baru, pertahankan key pada deployment yang sudah memiliki data terenkripsi
- Queue worker berjalan (`queue:work`) dan scheduler aktif
- Backup off-site terenkripsi: `php artisan younz:backup` (rotasi otomatis, opsional Google Drive / R2)
- Backup MariaDB lokal otomatis dan uji restore terisolasi: lihat [panduan MariaDB](docs/mariadb-backup.md). Off-site belum aktif pada host Windows saat ini.
- Konfigurasi antivirus ClamAV (`ANTIVIRUS_*`) untuk file pesanan
- Gunakan `.env.production.example` sebagai acuan variabel production

## Keamanan

- `.env` dan `*.env.local` tidak pernah di-commit; semua template `.env.example` hanya berisi placeholder
- Owner diwajibkan TOTP 2FA; kode akses staff di-hash (argon2id); token Sanctum diberi ability terbatas
- Harga memakai snapshot database, bukan nilai client; refund & approval memakai transaksi terpisah
- File pesanan di disk private, tautan unduhan ditandatangani & kedaluwarsa, antivirus dapat diwajibkan
- Younz AI tidak menghitung harga final, riwayat terbatas & dapat dihapus, dengan budget harian
- Semua request webhook diverifikasi dengan token bersama + toleransi waktu (HMAC)
- **Rotasi kredensial** (token AI, webhook, API key) segera jika pernah terekspos

## Struktur Direktori

```
├── app/                 # Backend Laravel (controllers, jobs, models, support)
├── config/  routes/  resources/  database/  public/
├── frontend/            # Next.js 16 (website publik + admin)
├── whatsapp-gateway/    # Node.js Baileys gateway
├── discord-bot/         # Bot Discord (commands, events, services, webhook)
├── scripts/             # Script operasional (PowerShell/Node/PHP)
├── deploy/              # cloudflared.service, nginx-younz.conf
├── docs/                # MVP_STATUS, TOPUP_INTEGRATION, SECURITY_OPERATIONS
└── .github/workflows/   # CI & quality checks
```

## Dokumentasi Terkait

- [docs/MVP_STATUS.md](docs/MVP_STATUS.md) — status implementasi
- [docs/TOPUP_INTEGRATION.md](docs/TOPUP_INTEGRATION.md) — integrasi top-up (Digiflazz + Midtrans)
- [docs/SECURITY_OPERATIONS.md](docs/SECURITY_OPERATIONS.md) — operasional keamanan
- [discord-bot/README.md](discord-bot/README.md) — dokumentasi bot Discord
- `PRD_Younz_Digital_Center_Laravel13_AI.md` — PRD awal

### Pembelian PPOB langsung dari WhatsApp

Nomor operator yang sudah diizinkan dapat mengirim `Beli pulsa Telkomsel 10.000 ke nomor 081234567890` atau format pascabayar yang setara. Sistem membuat draft, meminta sandi konfirmasi, lalu memasukkan transaksi ke antrean `ProcessTopupOrder` setelah sandi diverifikasi. Sandi disimpan sebagai hash di `YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH`, sedangkan nomor yang diizinkan diatur melalui `YOUNZ_PPOB_WHATSAPP_OPERATOR_NUMBERS`. Queue worker harus aktif agar eksekusi provider berjalan.

Untuk membuat hash tanpa menyimpan sandi di source code, jalankan `php artisan tinker`, gunakan `Hash::make('kode-rahasia-anda')`, lalu salin hasil hash ke `.env` dengan tanda kutip tunggal:

```dotenv
YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH='$argon2id$hasil-dari-tinker'
YOUNZ_PPOB_WHATSAPP_OPERATOR_NUMBERS=628xxxxxxxxxx
```

Setelah itu jalankan `php artisan optimize:clear`, `php artisan config:cache`, dan `php artisan queue:restart` pada server produksi.
