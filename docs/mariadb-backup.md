# Backup MariaDB dan uji pemulihan

## Operasi otomatis

Pada host Windows ini, service `YounzScheduler` sudah berstatus Automatic dan Running,
dengan working directory `C:\Daffa\Younz\YounzDigitalCenter`. Service memakai
`C:\xampp\php84-sampitmart\php.exe artisan schedule:work`.
Jadwal Laravel yang sudah ada menjalankan `younz:backup` setiap hari pukul **02.00 WIB**
(`APP_TIMEZONE=Asia/Jakarta`), dengan pencegah eksekusi tumpang tindih dan retensi 30 hari.
Komputer dan MariaDB harus menyala saat jadwal berjalan; jadwal harian tidak otomatis
mengganti eksekusi yang terlewat ketika komputer mati. Jalankan backup manual setelah
downtime yang melewati jadwal. Status dan hasil terjadwal ada di `storage/logs/backup.log`.

Perbaikan ini menambahkan driver MariaDB/MySQL pada perintah yang sudah dijadwalkan;
tidak membuat task ganda dan tidak mengubah `.env`, kredensial, atau cache konfigurasi utama.

```powershell
Set-Location 'C:\Daffa\Younz\YounzDigitalCenter'
php artisan younz:backup
php artisan schedule:list
Get-Service YounzScheduler
```

## Isi dan lokasi backup

- File baru: `storage/app/private/backups/mariadb/younz-YYYYMMDD-HHMMSS-XXXXXXXX.mariadb.enc`.
- Folder dibatasi ke pemilik, SYSTEM, dan Administrators pada Windows (0700 pada Unix).
- Dump konsisten menggunakan `--single-transaction` untuk tabel InnoDB; jangan menjalankan
  migrasi/DDL bersamaan dengan backup. Profil ini menolak view atau tabel non-InnoDB.
- SQL dikompresi dan dimasukkan ke manifest berisi SHA-256, daftar tabel, dan jumlah baris
  yang dihitung dari dump itu sendiri, lalu dienkripsi menggunakan Laravel `Crypt`.
- Setiap penulisan dibaca ulang, didekripsi, dan dicocokkan hash-nya sebelum dinyatakan berhasil.
- SQL tidak ditulis ke file plaintext. Kredensial CLI memakai option file sementara dengan
  ACL privat, bukan argumen password; file itu dihapus setelah proses berakhir.
- Rotasi MariaDB lokal hanya mencakup backup terkelola di subfolder `backups/mariadb`.
  Backup recovery/legacy yang sudah ada di folder induk tidak dihapus oleh job MariaDB.
- Backup ini mencakup database, **bukan** seluruh file upload, struk PDF, source code, atau `.env`.

Simpan `APP_KEY` yang sesuai secara terpisah di password manager atau penyimpanan rahasia
yang aman. Tanpanya, backup ini dan kolom database terenkripsi tidak dapat didekripsi.
Jangan mengganti `APP_KEY` hanya untuk mengaktifkan backup.

Saat ini backup berada pada disk lokal yang sama; R2 dan Google Drive tidak aktif.
Ini belum melindungi dari kerusakan/hilangnya seluruh komputer atau disk. Aktifkan tujuan
off-site hanya setelah kredensial dan kebijakan penyimpanannya disepakati.

Binary dideteksi dari instalasi XAMPP/PATH. Jika instalasi berbeda, atur
`MARIADB_DUMP_BINARY` dan `MARIADB_CLIENT_BINARY`, lalu terapkan konfigurasi melalui
prosedur deployment biasa. Tidak perlu mengganti database utama.
Profil otomatis saat ini memakai TCP lokal tanpa opsi PDO TLS/socket khusus; konfigurasi
remote/TLS membutuhkan profil tersendiri, bukan penghapusan pengaman.
Snapshot SQL dibatasi 64 MiB dan diproses di memori; sesuaikan arsitektur backup jika
database bertambah besar (jangan menaikkan batas tanpa meninjau kapasitas memori).

## Uji pemulihan aman

Gunakan nama file yang benar-benar dihasilkan backup:

```powershell
php artisan younz:backup-verify backups/mariadb/younz-YYYYMMDD-HHMMSS-XXXXXXXX.mariadb.enc
```

Perintah ini hanya menerima format path backup terkelola dan profil MariaDB TCP
`127.0.0.1`. Tidak tersedia argumen untuk menimpa database yang sudah ada.

1. Dekripsi dan validasi hash/manifest backup sebelum membuat database.
2. Buat database acak baru berawalan `younzrestorecheck`, tanpa `IF NOT EXISTS`.
3. Buat akun acak sementara dengan hak hanya pada database baru tersebut.
4. Pastikan akun itu ditolak saat membaca tabel `users` database utama.
5. Impor menggunakan akun terbatas, dengan perintah klien lokal/non-SQL dan LOCAL INFILE
   dinonaktifkan. SQL dump tidak pernah dijalankan oleh akun utama.
6. Cocokkan daftar tabel, jumlah baris manifest, `CHECK TABLE`, serta sidik data mentah.
   Database live dapat berubah setelah snapshot; perbedaan ini dilaporkan terpisah,
   bukan disembunyikan atau ditimpa.
7. Hapus hanya database/akun sementara yang dibuat oleh proses itu sendiri.
8. Simpan laporan tanpa isi baris/password di `storage/app/private/backup-verification`.

Akun pengelola perlu hak CREATE DATABASE, CREATE USER, GRANT pada schema uji, dan hak
pembersihan. Hak akun aplikasi yang ada tidak diubah oleh perintah ini. Uji awal dilakukan
pada 43 tabel InnoDB tanpa view/routine/event/trigger; dump dengan objek DEFINER atau
referensi lintas-database dapat ditolak oleh akun terbatas dan memerlukan tinjauan khusus.

Jika uji gagal, baca `failure_phase` dan kode numerik di laporan. Error SQL mentah tidak
dicatat karena dapat berisi kredensial atau data pelanggan. Jika pembersihan gagal,
laporan menyebutkan database/akun sementara yang perlu ditinjau; jangan menghapus database
utama atau menjalankan dump menggunakan root sebagai jalan pintas.

## Pengujian kode

`php artisan test --filter MariaDbBackupTest` berjalan pada SQLite memori dan mock dump.
Proses dump/restore native serta pembuatan akun/database nyata diblokir di PHPUnit,
termasuk ketika sebuah test mengganti label lingkungannya. Uji pemulihan MariaDB nyata
adalah perintah operasional eksplisit di atas, bukan bagian dari `RefreshDatabase`.
