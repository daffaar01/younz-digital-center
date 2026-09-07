# Aktivasi Top Up Digiflazz + Midtrans

Fitur Top Up menggunakan katalog lokal dari Digiflazz, pembayaran Snap Redirect Midtrans, dan queue Laravel untuk meneruskan transaksi setelah pembayaran sah diterima. Mulai dari mode testing/sandbox sebelum mengaktifkan transaksi produksi.

## 1. Isi konfigurasi `.env`

```dotenv
DIGIFLAZZ_ENABLED=true
DIGIFLAZZ_USERNAME=
DIGIFLAZZ_API_KEY=
DIGIFLAZZ_TESTING=true
DIGIFLAZZ_WEBHOOK_SECRET=
DIGIFLAZZ_MARKUP_PERCENT=3
DIGIFLAZZ_MARKUP_FIXED=1000
TOPUP_ADMIN_FEE=0

MIDTRANS_ENABLED=true
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_PRODUCTION=false
MIDTRANS_EXPIRY_MINUTES=60
```

Jangan menyimpan credential di repository. Untuk produksi, ubah `DIGIFLAZZ_TESTING=false` dan `MIDTRANS_PRODUCTION=true` hanya setelah sandbox berhasil diuji.

## 2. Daftarkan webhook

- Midtrans Payment Notification URL: `https://younzdigitalcenter.my.id/webhooks/midtrans`
- Digiflazz webhook/callback URL: `https://younzdigitalcenter.my.id/webhooks/digiflazz`
- Nilai secret webhook Digiflazz harus sama dengan `DIGIFLAZZ_WEBHOOK_SECRET`.

Kedua endpoint hanya menerima `POST`, memverifikasi signature resmi provider, dan aman saat notifikasi yang sama dikirim ulang.

## 3. Terapkan konfigurasi dan sinkronkan katalog

```shell
php artisan optimize
php artisan digiflazz:sync-products
php artisan queue:restart
```

Pastikan queue worker dan scheduler aktif. Scheduler menyegarkan katalog setiap 15 menit saat Digiflazz diaktifkan.

## 4. Uji alur lengkap

1. Pilih produk dari `/topup` dan periksa harga jual.
2. Buat transaksi sandbox dan selesaikan pembayaran pada halaman Midtrans.
3. Pastikan status pembayaran berubah menjadi diterima hanya setelah webhook Midtrans sah.
4. Pastikan request Digiflazz memakai referensi order yang sama dan halaman status berakhir pada `Top up berhasil`.
5. Ulangi webhook yang sama untuk memastikan saldo/produk tidak diproses dua kali.

Jika pembayaran berhasil tetapi provider gagal, status order tetap tercatat untuk pemeriksaan operator; jangan mengirim ulang secara manual memakai referensi baru.
