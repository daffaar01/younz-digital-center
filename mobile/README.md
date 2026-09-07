# Younz Digital Center Mobile

Aplikasi Flutter Android untuk pengalaman pelanggan Younz Digital Center. Aplikasi memakai REST API Laravel yang sama dengan frontend Next.js.

## Fitur

- Beranda, layanan, produk unggulan, dan informasi toko dari `/api/v1/site`.
- Katalog produk digital dengan pencarian, filter kategori, detail, varian, stok, kuantitas, dan pemesanan WhatsApp.
- Pemesanan layanan dengan spesifikasi dan unggah file.
- Katalog serta checkout PPOB/top-up melalui Midtrans.
- Pelacakan pesanan menggunakan nomor pesanan dan WhatsApp.
- Chat Younz AI.
- Login, registrasi, dan ringkasan portal pelanggan.

## Menjalankan

Pastikan Flutter dan Android SDK sudah terpasang, lalu jalankan:

```powershell
cd mobile
flutter pub get
flutter run --dart-define=YOUNZ_API_URL=http://10.0.2.2:8000
```

`10.0.2.2` adalah alamat host komputer dari Android Emulator. Untuk perangkat fisik, gunakan URL HTTPS publik atau alamat LAN backend.

Build APK release:

```powershell
flutter build apk --release --dart-define=YOUNZ_API_URL=https://younzdigitalcenter.my.id
```

APK akan tersedia di `build/app/outputs/flutter-apk/app-release.apk`. Sebelum distribusi publik atau upload ke Play Console, ganti signing konfigurasi debug bawaan dengan keystore release milik Younz.

Jika folder Android perlu disegarkan oleh versi Flutter yang terpasang, jalankan `flutter create --platforms=android .` dari folder `mobile` sebelum build pertama.
