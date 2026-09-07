# Bot Discord Younz Digital Center

Bot Discord untuk memantau operasional Younz Digital Center, menjawab
pertanyaan lewat Younz AI, serta menangani moderasi dan tiket support.

## Fitur

**Integrasi Younz Digital Center**
- `/younz ringkasan` — pendapatan, pengeluaran, pesanan, dan approval hari ini
- `/younz stok` — produk dengan stok pada atau di bawah batas minimum
- `/younz transaksi` — sepuluh transaksi terakhir
- `/younz health` — cek respons situs publik dan portal admin
- Notifikasi otomatis dari Laravel: pesanan baru, top up dibayar, top up gagal,
  approval menunggu, stok menipis, dan peringatan sistem

**Younz AI**
- `/younzai tanya` — tanya asisten virtual (OpenAI-compatible)
- `/younzai cekstatus` — cek status pesanan layanan atau top up
- `/younzai hapus` — hapus riwayat percakapan

**Moderasi**
- `/moderasi peringatan` — peringatan tercatat, dikirim juga lewat DM
- `/moderasi riwayat` — riwayat peringatan seorang anggota
- `/moderasi timeout` — bisukan sementara
- `/moderasi kick` dan `/moderasi ban`
- `/moderasi bersihkan` — hapus hingga 100 pesan terakhir
- Anti-spam otomatis: timeout bila pesan terlalu cepat, hapus tautan undangan
- Semua tindakan dicatat ke channel log

**Tiket support**
- `/tiket buka` — channel privat per pengguna, hanya terlihat olehnya dan staff
- `/tiket tutup` atau tombol Tutup tiket
- Tombol Tangani untuk menandai staff yang menangani
- Batas dua tiket per pengguna setiap sepuluh menit

**Umum**
- `/bantuan` — daftar perintah, menyesuaikan role pemanggil
- `/ping` — latensi dan status fitur
- Sambutan member baru dan auto-role

## Kebutuhan

- Node.js 20 atau lebih baru
- Bot Discord dengan **Server Members Intent** dan **Message Content Intent**
  aktif pada Developer Portal
- Token Sanctum staff Younz Digital Center (opsional)
- Penyedia AI OpenAI-compatible (opsional)

## Instalasi lokal

```bash
cd discord-bot
npm install
cp .env.example .env
# isi .env, minimal DISCORD_TOKEN dan DISCORD_CLIENT_ID
npm run deploy
npm start
```

`npm run deploy` mendaftarkan slash command. Jalankan ulang setiap kali ada
perintah baru atau deskripsi perintah berubah.

## Konfigurasi

Wajib:

| Variabel | Keterangan |
|---|---|
| `DISCORD_TOKEN` | Token bot dari Developer Portal |
| `DISCORD_CLIENT_ID` | Application ID bot |

Sangat disarankan:

| Variabel | Keterangan |
|---|---|
| `DISCORD_GUILD_ID` | ID server. Tanpa ini command didaftarkan global dan lambat muncul |
| `ROLE_STAFF_ID` | Role yang boleh menjalankan perintah operasional |
| `CHANNEL_NOTIFICATION_ID` | Tujuan notifikasi dari Laravel |
| `CHANNEL_LOG_ID` | Tujuan log moderasi dan anti-spam |

Opsional per fitur:

| Fitur | Variabel |
|---|---|
| Tiket | `CATEGORY_TICKET_ID` |
| Sambutan | `CHANNEL_WELCOME_ID`, `ROLE_AUTO_ID` |
| Data bisnis | `YOUNZ_API_URL`, `YOUNZ_API_TOKEN` |
| Younz AI | `OPENAI_COMPATIBLE_URL`, `OPENAI_COMPATIBLE_API_KEY`, `OPENAI_COMPATIBLE_MODEL` |
| Webhook | `WEBHOOK_TOKEN` (minimal 32 karakter), `WEBHOOK_HOST`, `SERVER_PORT`/`WEBHOOK_PORT` |

Fitur yang variabelnya belum diisi otomatis dinonaktifkan. Bot tetap berjalan.

## Izin bot di Discord

Undang bot dengan scope `bot` dan `applications.commands`, lalu berikan izin:

- View Channels, Send Messages, Embed Links, Read Message History
- Manage Messages — untuk `/moderasi bersihkan` dan hapus tautan undangan
- Manage Channels — untuk membuat dan menghapus channel tiket
- Moderate Members — untuk timeout dan anti-spam
- Kick Members dan Ban Members — bila memakai kedua perintah tersebut
- Manage Roles — bila memakai auto-role

Posisi role bot harus berada **di atas** role anggota yang akan dimoderasi dan
di atas role auto-role.

## Deploy ke panel hosting

Kebutuhan bot ini ringan; konsumsi normal sekitar 120–200 MB RAM. Anda dapat
menjalankannya pada panel hosting (misal Pterodactyl/Pelican) atau VPS biasa.

1. Buat server dengan egg **Node.js** generik.
2. Setel `Startup Command` menjadi:
   ```
   npm start
   ```
3. Setel variabel egg:
   - `Main File` atau `STARTUP_FILE`: `src/index.js`
   - `Node Packages`: biarkan kosong, dependensi diambil dari `package.json`
   - `Auto Update`: nonaktifkan bila Anda mengunggah manual
4. Unggah seluruh isi folder `discord-bot` ke direktori root server, kecuali
   `node_modules`.
5. Buka tab **Startup**, tambahkan variabel lingkungan sesuai tabel di atas.
   Panel menyuntikkan environment variable, sehingga file `.env` tidak wajib.
6. Jalankan sekali di konsol server untuk mendaftarkan command:
   ```
   npm install && npm run deploy
   ```
7. Nyalakan server. Log akan menampilkan `Bot siap`.

Bila panel tidak mengizinkan variabel bebas, unggah file `.env` ke root server.
Nilai dari panel selalu menang atas isi `.env`.

## Integrasi notifikasi dari Laravel

Bot menerima webhook langsung pada allocation publik panel hosting, sehingga
Laravel tidak perlu menjalankan Cloudflare Tunnel atau service lokal. Pastikan
`WEBHOOK_HOST=0.0.0.0` (default) dan `SERVER_PORT`/`WEBHOOK_PORT` cocok dengan
allocation port panel, lalu buka firewall panel hanya untuk alamat IP keluar
Laravel.

Tambahkan pada `.env` Laravel:

```dotenv
DISCORD_NOTIFY_ENABLED=true
DISCORD_BOT_WEBHOOK_URL=https://bot.younzdigitalcenter.my.id
DISCORD_BOT_WEBHOOK_TOKEN=<sama dengan WEBHOOK_TOKEN pada bot>
```

Nilai `DISCORD_BOT_WEBHOOK_URL` harus mengarah ke alamat publik yang sama
dengan allocation bot. Bila menggunakan IP, tulis lengkap
`http://203.0.113.10:3200`; bila menggunakan domain, sebaiknya terminating
HTTPS di reverse proxy (Caddy/Nginx) di depan bot.

Uji koneksi:

```bash
php artisan discord:notify --test --mention
```

Kirim notifikasi dari kode:

```php
use App\Integrations\Discord\DiscordNotifier;

app(DiscordNotifier::class)->notify(
    event: 'topup.paid',
    title: 'Top Up Berhasil',
    message: "Pesanan {$order->order_number} telah dibayar.",
    fields: [
        ['name' => 'Produk', 'value' => $order->product_name, 'inline' => true],
        ['name' => 'Tujuan', 'value' => $order->target, 'inline' => true],
    ],
    amount: $order->total,
    reference: $order->order_number,
    mentionStaff: true,
);
```

Event yang dikenali bot beserta warna dan ikonnya: `order.created`,
`order.status_changed`, `topup.paid`, `topup.failed`, `approval.pending`,
`stock.low`, `system.alert`. Event lain tetap terkirim dengan gaya netral.

**Catatan jaringan.** Bot bind ke `0.0.0.0` pada allocation publik panel.
Setiap `POST /notify` divalidasi dengan bearer token dan HMAC SHA-256
bertimestamp (`X-Younz-Timestamp` dan `X-Younz-Signature`) agar payload tidak
dapat dipalsukan atau diputar ulang. Laravel menandatangani body JSON yang
persis sama dengan yang dikirim. Pastikan port allocation dibuka hanya untuk
IP keluar Laravel (atau batasi di reverse proxy) agar tidak menjadi jalur
DM terbuka.

## Keamanan

- Token dan API key tidak pernah ditulis ke log. Field sensitif diredaksi.
- Webhook membandingkan token secara timing-safe dan membatasi payload 32 KB.
- Setiap webhook wajib memuat HMAC SHA-256 bertimestamp; request di luar
  toleransi `WEBHOOK_MAX_SKEW_SECONDS` ditolak.
- Bind gagal (mis. port allocation dipakai) menghentikan proses, sehingga panel
  tidak menampilkan bot "berjalan" tanpa endpoint aktif.
- Perintah operasional memeriksa role staff, bukan hanya izin Discord.
- Perintah hasil balasan bersifat ephemeral kecuali kontrol yang sengaja publik
  agar jejaknya terlihat tim.

## Struktur proyek

```
discord-bot/
├── src/
│   ├── index.js                  entrypoint
│   ├── deploy-commands.js        pendaftaran slash command
│   ├── core/                     config, env, logger, http, loader, permissions
│   ├── commands/                 bantuan, ping, younz, younzai, moderasi, tiket
│   ├── events/                   ready, interaction, member join, anti-spam
│   └── services/                 younz, younz-ai, tickets, webhook, store
└── test/                         unit test tanpa dependensi eksternal
```

## Pemeliharaan

```bash
npm test          # unit test
npm run dev       # jalankan dengan auto-reload
npm run deploy    # daftarkan ulang slash command
```

Data ringan seperti nomor tiket dan riwayat peringatan tersimpan di `data/`
sebagai JSON. Sertakan direktori itu pada backup panel bila riwayat penting.

## Pemecahan masalah

| Gejala | Penyebab umum |
|---|---|
| Command tidak muncul | `npm run deploy` belum dijalankan, atau `DISCORD_GUILD_ID` kosong sehingga menunggu propagasi global |
| `Login gagal` | `DISCORD_TOKEN` salah atau sudah diregenerasi |
| Sambutan tidak terkirim | Server Members Intent belum aktif di Developer Portal |
| Anti-spam diam | Message Content Intent belum aktif |
| `/younz` menolak | Token Sanctum kedaluwarsa |
| Tiket gagal dibuat | Bot tidak punya Manage Channels, atau `CATEGORY_TICKET_ID` salah |
| Moderasi gagal | Role bot berada di bawah role target |
