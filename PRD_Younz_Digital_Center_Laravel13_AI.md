# Product Requirements Document (PRD)
## Younz Digital Center — Laravel 13 + AI

**Versi:** 1.0  
**Status:** Draft Pengembangan  
**Tanggal:** 20 Juli 2026  
**Platform:** Web responsif, REST API, dan aplikasi Android tahap lanjutan  
**Backend utama:** Laravel 13  
**Nama produk:** Younz Digital Center  

---

# 1. Ringkasan Eksekutif

Younz Digital Center adalah platform operasional dan layanan pelanggan untuk sebuah toko offline yang menyediakan:

- Penjualan alat tulis kantor (ATK).
- Fotokopi.
- Print dokumen dan foto.
- Scan dokumen.
- Jasa ketik.
- Pulsa.
- Paket data.
- Top-up e-wallet.
- Voucher game.
- Token PLN.
- Pembayaran PLN pascabayar.
- Pembayaran PDAM.
- Pembayaran internet pascabayar.
- Jasa desain.
- Pembuatan website.
- Pembuatan aplikasi Android.

Produk ini akan dibangun menggunakan Laravel 13 dan dirancang agar dapat diintegrasikan dengan kecerdasan buatan sejak awal.

Sistem terdiri dari:

1. Website publik.
2. Dashboard owner dan pegawai.
3. Sistem kasir atau Point of Sale (POS).
4. Manajemen produk dan stok.
5. Manajemen pesanan jasa.
6. Pencatatan transaksi digital dan PPOB.
7. Manajemen proyek desain, website, dan aplikasi Android.
8. Portal pelanggan.
9. Laporan operasional dan keuangan.
10. Asisten AI untuk pelanggan dan pegawai.
11. REST API untuk aplikasi Android dan integrasi pihak ketiga.

Tujuan utamanya adalah menyatukan kegiatan toko yang sebelumnya tersebar di aplikasi kasir, catatan manual, chat WhatsApp, aplikasi distributor PPOB, dan spreadsheet menjadi satu sistem yang terukur, aman, dan dapat dikembangkan.

---

# 2. Latar Belakang

Operasional Younz Digital Center memiliki beberapa jenis transaksi dengan karakter berbeda.

Transaksi ATK membutuhkan:

- Kasir.
- Stok.
- Harga beli dan jual.
- Supplier.
- Retur.
- Laporan laba.

Transaksi jasa print dan desain membutuhkan:

- Data pelanggan.
- Upload file.
- Brief.
- Antrean kerja.
- Estimasi biaya.
- Status pengerjaan.
- Revisi.
- Penyerahan hasil.

Transaksi pulsa dan pembayaran digital membutuhkan:

- Nomor tujuan.
- Provider.
- Harga modal.
- Harga jual.
- Nomor referensi.
- Status berhasil atau gagal.
- Rekonsiliasi.

Proyek website dan aplikasi membutuhkan:

- Analisis kebutuhan.
- Nilai proyek.
- Uang muka.
- Milestone.
- Revisi.
- File.
- Deadline.
- Maintenance.

Tanpa sistem terpadu, risiko yang muncul meliputi:

- Stok tidak akurat.
- Transaksi tidak tercatat.
- Perhitungan laba sulit.
- Pesanan pelanggan terlambat.
- File pelanggan tercecer.
- Kesalahan nomor tujuan transaksi digital.
- Lupa menagih pembayaran.
- Tidak ada riwayat perubahan.
- Sulit mengetahui performa layanan.
- Sulit mengelola pekerjaan ketika pegawai bertambah.

---

# 3. Visi Produk

Menjadikan Younz Digital Center sebagai pusat layanan digital lokal yang menggabungkan toko fisik, pemesanan online, sistem operasional, kasir, stok, layanan pembayaran, manajemen proyek, dan AI dalam satu platform.

---

# 4. Sasaran Produk

## 4.1 Sasaran Bisnis

- Mengurangi pencatatan manual.
- Mempercepat pelayanan pelanggan.
- Mengetahui pendapatan dan laba per kategori.
- Mengurangi selisih stok.
- Memastikan semua pesanan memiliki status dan penanggung jawab.
- Mempermudah pelanggan mengirim file dan memantau pesanan.
- Mempercepat pembuatan brief dan estimasi awal dengan AI.
- Menyediakan fondasi untuk aplikasi Android.
- Menyiapkan sistem yang dapat berkembang ke multi-outlet.

## 4.2 Sasaran Pengguna

### Owner

- Melihat kondisi usaha dalam satu dashboard.
- Mengetahui pendapatan, laba, pengeluaran, dan stok.
- Memantau pekerjaan pegawai.
- Mengendalikan akses dan pengaturan.
- Meninjau rekomendasi AI.

### Kasir

- Membuat transaksi dengan cepat.
- Mencari produk berdasarkan nama atau barcode.
- Menerima beberapa metode pembayaran.
- Mencetak atau mengirim struk.
- Menutup kas dengan mudah.

### Operator

- Melihat antrean print dan jasa.
- Membuka file pelanggan.
- Memperbarui status.
- Mencatat hasil dan kendala.

### Desainer dan Developer

- Melihat pekerjaan yang ditugaskan.
- Mengakses brief dan file.
- Memperbarui progres.
- Mengelola revisi dan hasil final.

### Pelanggan

- Melihat layanan dan harga.
- Mengirim file.
- Membuat pesanan.
- Menyetujui estimasi.
- Membayar.
- Melacak status.
- Mengunduh hasil digital.

---

# 5. Sasaran yang Tidak Termasuk pada MVP

Fitur berikut tidak menjadi persyaratan wajib untuk versi pertama:

- Marketplace multi-merchant.
- Franchise.
- Multi-negara.
- Akuntansi penuh seperti jurnal umum dan neraca.
- Payroll kompleks.
- Otomatisasi pembelian tanpa persetujuan manusia.
- Pembayaran atau refund yang diputuskan oleh AI.
- Promosi massal otomatis tanpa persetujuan.
- Aplikasi Android pada rilis pertama.
- Integrasi semua provider PPOB sekaligus.
- Sistem produksi cetak skala industri.
- Generator desain final sepenuhnya otomatis.

---

# 6. Pengguna dan Hak Akses

## 6.1 Role Utama

### Owner

Memiliki akses penuh terhadap:

- Dashboard bisnis.
- Produk.
- Stok.
- Supplier.
- Pembelian.
- Penjualan.
- Pesanan.
- Proyek.
- Transaksi digital.
- Pelanggan.
- Pengeluaran.
- Laporan.
- Pegawai.
- Hak akses.
- Pengaturan AI.
- Audit log.
- Integrasi.
- Backup dan maintenance.

### Admin

Memiliki akses terhadap:

- Produk dan kategori.
- Stok dan supplier.
- Pelanggan.
- Pesanan.
- Transaksi digital.
- Portofolio.
- Konten website.
- Laporan operasional sesuai izin.

### Kasir

Memiliki akses terhadap:

- POS.
- Transaksi.
- Pembayaran.
- Cetak struk.
- Sesi kas.
- Riwayat transaksi sendiri.
- Pelanggan dasar.
- Pesanan sederhana.

Kasir tidak boleh:

- Mengubah role.
- Menghapus transaksi tanpa otorisasi.
- Melihat seluruh laba usaha.
- Mengubah konfigurasi AI.
- Mengubah harga modal tanpa izin.

### Operator Print

Memiliki akses terhadap:

- Antrean print.
- File pelanggan sesuai tugas.
- Detail pekerjaan.
- Status pengerjaan.
- Catatan operator.
- Hasil pekerjaan.

### Desainer

Memiliki akses terhadap:

- Pesanan desain.
- Brief.
- Referensi.
- Revisi.
- File preview.
- File final.
- Status pekerjaan desain.

### Developer

Memiliki akses terhadap:

- Proyek website dan aplikasi.
- Requirement.
- Milestone.
- Catatan.
- File proyek.
- Revisi.
- Status progres.

### Pelanggan

Memiliki akses terhadap:

- Profil sendiri.
- Pesanan sendiri.
- Invoice sendiri.
- File sendiri.
- Riwayat sendiri.
- Chat AI sendiri.
- Proyek sendiri.

## 6.2 Aturan Role

- Role dibuat dan diberikan oleh server.
- User tidak boleh memilih role admin, kasir, operator, desainer, developer, atau owner dari sisi client.
- Semua akses harus diperiksa melalui middleware, policy, atau gate.
- Menu tidak cukup hanya disembunyikan; endpoint tetap harus dilindungi.
- Setiap perubahan role harus dicatat di audit log.

---

# 7. Ruang Lingkup Produk

## 7.1 Website Publik

Website publik berfungsi sebagai:

- Identitas digital usaha.
- Daftar layanan.
- Daftar harga.
- Portofolio.
- Kanal pemesanan.
- Kanal upload file.
- Kanal cek status.
- Kanal komunikasi AI.
- Kanal kontak dan lokasi.

## 7.2 Dashboard Internal

Dashboard digunakan untuk:

- Kasir.
- Stok.
- Pesanan.
- Proyek.
- Pelanggan.
- Transaksi digital.
- Keuangan.
- Laporan.
- AI assistant.
- Pengaturan.

## 7.3 Portal Pelanggan

Portal pelanggan digunakan untuk:

- Melihat pesanan.
- Menyetujui estimasi.
- Melakukan konfirmasi pembayaran.
- Mengirim revisi.
- Mengunduh hasil.
- Melihat invoice.
- Melihat riwayat.

## 7.4 API

API disiapkan untuk:

- Aplikasi Android.
- Payment gateway.
- PPOB.
- WhatsApp gateway.
- Mitra eksternal.
- Aplikasi kasir tambahan.
- Integrasi internal lainnya.

---

# 8. Modul Fungsional

# 8.1 Autentikasi dan Akun

## Fitur

- Login.
- Logout.
- Lupa password.
- Reset password.
- Verifikasi email.
- Autentikasi dua faktor untuk owner.
- Manajemen sesi.
- Blokir akun.
- Aktivasi dan nonaktifkan pegawai.
- Login pelanggan.
- Login pegawai terpisah secara logis.
- Pencatatan login gagal.
- Rate limiting.

## Persyaratan

- Password disimpan menggunakan hashing.
- Sesi pegawai dan pelanggan harus dapat dibedakan melalui role dan policy.
- Owner dapat mengeluarkan sesi aktif pengguna.
- Sistem mencatat waktu login terakhir.
- Registrasi publik hanya menghasilkan role pelanggan.
- Registrasi pegawai hanya dapat dilakukan owner atau admin berizin.

## Acceptance Criteria

- Pengguna tanpa autentikasi tidak dapat membuka dashboard.
- Kasir tidak dapat membuka pengaturan owner.
- Pelanggan tidak dapat membuka data pelanggan lain.
- Login gagal berulang kali dibatasi.
- Aktivitas login penting tercatat.

---

# 8.2 Dashboard

## Dashboard Owner

Menampilkan:

- Pendapatan hari ini.
- Pendapatan bulan berjalan.
- Penjualan ATK.
- Pendapatan jasa print.
- Pendapatan desain.
- Pendapatan proyek.
- Pendapatan transaksi digital.
- Pengeluaran hari ini.
- Pengeluaran bulan berjalan.
- Estimasi laba kotor.
- Saldo kas.
- Pesanan aktif.
- Pesanan melewati deadline.
- Produk hampir habis.
- Produk habis.
- Transaksi PPOB gagal.
- Piutang.
- Proyek aktif.
- Aktivitas terbaru.

## Dashboard Kasir

Menampilkan:

- Sesi kasir.
- Total transaksi hari ini.
- Tunai.
- Transfer.
- QRIS.
- E-wallet.
- Transaksi tertahan.
- Transaksi dibatalkan.
- Selisih kas.
- Antrean pelanggan.

## Dashboard Operator

Menampilkan:

- Antrean baru.
- Sedang dikerjakan.
- Prioritas.
- Deadline terdekat.
- Pesanan menunggu konfirmasi.
- Pesanan siap diambil.

## Dashboard AI

Menampilkan:

- Ringkasan bisnis.
- Rekomendasi reorder.
- Produk lambat terjual.
- Pesanan berisiko terlambat.
- Transaksi tidak biasa.
- Rekomendasi promosi.
- Biaya penggunaan AI.

## Acceptance Criteria

- Setiap role hanya melihat data yang relevan.
- Data dashboard mengikuti filter tanggal.
- Nilai keuangan berasal dari transaksi valid.
- Transaksi dibatalkan tidak dihitung sebagai pendapatan.
- Rekomendasi AI diberi label sebagai rekomendasi, bukan keputusan final.

---

# 8.3 Kasir atau Point of Sale

## Fitur

- Pencarian produk berdasarkan nama.
- Pencarian SKU.
- Pencarian barcode.
- Filter kategori.
- Tambah produk ke keranjang.
- Tambah jasa ke keranjang.
- Ubah kuantitas.
- Diskon per item.
- Diskon transaksi.
- Pajak opsional.
- Catatan transaksi.
- Pelanggan opsional.
- Pembayaran tunai.
- Transfer bank.
- QRIS.
- E-wallet.
- Pembayaran campuran.
- Hitung uang kembali.
- Tahan transaksi.
- Lanjutkan transaksi.
- Cetak struk.
- Kirim struk digital.
- Pembatalan transaksi.
- Retur.
- Refund dengan persetujuan.
- Buka sesi kasir.
- Tutup sesi kasir.

## Aturan Bisnis

- Penjualan dan pengurangan stok dilakukan dalam database transaction.
- Produk tidak boleh dijual melebihi stok kecuali produk mengizinkan stok negatif.
- Diskon di atas batas tertentu membutuhkan otorisasi.
- Pembatalan transaksi harus memiliki alasan.
- Refund harus menyimpan pengguna yang menyetujui.
- Nomor invoice harus unik.
- Transaksi tertahan tidak mengurangi stok final sebelum dikonfirmasi.
- Harga pada item transaksi harus menyimpan snapshot saat transaksi.

## Acceptance Criteria

- Kasir dapat menyelesaikan transaksi umum tanpa membuka halaman lain.
- Stok berkurang setelah transaksi berhasil.
- Stok tidak berkurang apabila transaksi gagal.
- Struk dapat dicetak ulang.
- Semua pembayaran tersimpan.
- Selisih kas dapat diketahui saat tutup kasir.

---

# 8.4 Produk dan Kategori

## Data Produk

- Nama.
- Slug.
- SKU.
- Barcode.
- Kategori.
- Merek.
- Satuan.
- Deskripsi.
- Harga modal.
- Harga jual.
- Harga grosir.
- Stok.
- Stok minimum.
- Lokasi rak.
- Supplier utama.
- Foto.
- Status aktif.
- Produk fisik atau digital.
- Dapat dijual tanpa stok atau tidak.
- Tanggal kedaluwarsa jika relevan.

## Kategori Awal

- Kertas.
- Alat tulis.
- Buku.
- Map dan arsip.
- Tinta printer.
- Aksesori komputer.
- Perlengkapan sekolah.
- Perlengkapan kantor.
- Produk digital.
- Jasa.

## Acceptance Criteria

- SKU unik.
- Barcode unik jika diisi.
- Produk nonaktif tidak muncul di POS.
- Produk yang sudah memiliki transaksi tidak boleh dihapus permanen.
- Harga modal hanya terlihat oleh role tertentu.

---

# 8.5 Stok

## Fitur

- Stok masuk.
- Stok keluar.
- Penyesuaian stok.
- Retur pelanggan.
- Retur supplier.
- Barang rusak.
- Barang hilang.
- Pemakaian internal.
- Transfer antar lokasi tahap lanjutan.
- Riwayat stok.
- Stok minimum.
- Peringatan stok.
- Stock opname.
- Import stok.
- Export stok.

## Data Pergerakan Stok

- Produk.
- Jenis pergerakan.
- Stok sebelum.
- Jumlah perubahan.
- Stok sesudah.
- Referensi transaksi.
- Alasan.
- Pegawai.
- Waktu.

## Aturan Bisnis

- Stok tidak boleh diubah langsung tanpa membuat stock movement.
- Stock adjustment harus menyimpan alasan.
- Penghapusan stock movement tidak diperbolehkan.
- Koreksi dilakukan dengan membuat movement baru.
- Produk hampir habis ditentukan dari stok minimum.

## Acceptance Criteria

- Setiap perubahan stok dapat ditelusuri.
- Nilai stok saat ini sesuai akumulasi movement.
- Produk hampir habis muncul di dashboard.
- Stock opname menghasilkan selisih yang tercatat.

---

# 8.6 Supplier dan Pembelian

## Fitur

- Data supplier.
- Kontak supplier.
- Produk supplier.
- Purchase order.
- Penerimaan barang.
- Pembayaran pembelian.
- Hutang supplier.
- Retur pembelian.
- Riwayat harga beli.
- Cetak purchase order.
- Status pembelian.

## Status Pembelian

- Draft.
- Dipesan.
- Sebagian diterima.
- Diterima.
- Dibatalkan.
- Dibayar sebagian.
- Lunas.

## Acceptance Criteria

- Penerimaan barang menambah stok.
- Pembatalan penerimaan membuat koreksi stok.
- Harga beli dapat berbeda per transaksi.
- Sistem menyimpan hutang supplier jika belum lunas.

---

# 8.7 Pesanan Print, Fotokopi, Scan, dan Ketik

## Data Pesanan

- Nomor pesanan.
- Pelanggan.
- Nomor WhatsApp.
- Jenis layanan.
- File.
- Ukuran kertas.
- Jenis kertas.
- Warna atau hitam putih.
- Satu sisi atau bolak-balik.
- Jumlah halaman.
- Jumlah rangkap.
- Finishing.
- Catatan.
- Jadwal pengambilan.
- Metode pengambilan.
- Estimasi harga.
- Harga final.
- Uang muka.
- Pelunasan.
- Penanggung jawab.
- Deadline.

## Status

1. Draft.
2. Menunggu pemeriksaan.
3. Dianalisis AI.
4. Menunggu konfirmasi operator.
5. Menunggu persetujuan pelanggan.
6. Menunggu pembayaran.
7. Masuk antrean.
8. Sedang dikerjakan.
9. Menunggu revisi.
10. Siap diambil.
11. Selesai.
12. Dibatalkan.

## Aturan Bisnis

- Estimasi AI bukan harga final.
- Operator harus memeriksa halaman dan spesifikasi.
- Pelanggan harus menyetujui perubahan harga.
- File bersifat private.
- File hanya dapat diakses pengguna yang berwenang.
- Status memiliki histori.
- Pesanan selesai tidak dapat diedit tanpa reopening.

## Acceptance Criteria

- Pelanggan dapat upload file.
- Operator dapat melihat file sesuai izin.
- Pelanggan dapat melihat status.
- Semua perubahan status tercatat.
- Pesanan melewati deadline muncul sebagai peringatan.

---

# 8.8 Pesanan Desain

## Jenis Layanan

- Logo.
- Banner.
- Spanduk.
- Poster.
- Brosur.
- Undangan.
- Kartu nama.
- Konten media sosial.
- Desain kemasan.
- Edit foto.

## Data Brief

- Nama bisnis.
- Tujuan desain.
- Target audiens.
- Ukuran.
- Platform.
- Warna.
- Gaya.
- Teks utama.
- Referensi.
- Format hasil.
- Deadline.
- Jumlah revisi.
- Anggaran.
- Catatan.

## Workflow

1. Brief masuk.
2. Brief dianalisis AI.
3. Desainer memeriksa.
4. Estimasi dikirim.
5. Pelanggan menyetujui.
6. Uang muka diterima.
7. Pengerjaan.
8. Preview dikirim.
9. Review pelanggan.
10. Revisi.
11. Disetujui.
12. Pelunasan.
13. File final dikirim.
14. Selesai.

## Acceptance Criteria

- Semua revisi tercatat.
- File preview dan final dibedakan.
- Jumlah revisi dapat dihitung.
- Pelanggan dapat memberi komentar.
- File final hanya dapat diunduh sesuai aturan pembayaran.

---

# 8.9 Proyek Website dan Aplikasi Android

## Data Proyek

- Nama proyek.
- Pelanggan.
- Jenis proyek.
- Ringkasan.
- Requirement.
- Fitur.
- Teknologi.
- Domain.
- Hosting.
- Nilai proyek.
- Uang muka.
- Termin.
- Tanggal mulai.
- Deadline.
- Penanggung jawab.
- Status.
- Masa garansi.
- Maintenance.

## Milestone

- Discovery.
- Analisis kebutuhan.
- PRD.
- Wireframe.
- UI/UX.
- Development.
- Testing.
- Revisi.
- Deployment.
- Serah terima.
- Maintenance.

## Fitur

- Milestone.
- Task.
- File.
- Catatan.
- Riwayat revisi.
- Pembayaran termin.
- Invoice.
- Progress.
- Laporan progres.
- Credential vault opsional.
- Dokumen serah terima.
- Masa dukungan.

## Aturan Keamanan

- Password tidak disimpan sebagai teks biasa.
- Credential sensitif menggunakan enkripsi.
- Akses credential dibatasi.
- Setiap pembacaan credential dicatat.
- Data pelanggan tidak dikirim ke AI tanpa penyaringan.

## Acceptance Criteria

- Progress proyek dapat dihitung dari milestone.
- Pelanggan hanya melihat proyeknya.
- Pembayaran termin dapat dilacak.
- Semua perubahan requirement tercatat.

---

# 8.10 Transaksi Digital dan PPOB

## Produk Digital

- Pulsa.
- Paket data.
- Top-up e-wallet.
- Voucher game.
- Token PLN.
- PLN pascabayar.
- PDAM.
- Internet pascabayar.

## Data Transaksi

- Nomor transaksi.
- Jenis produk.
- Provider.
- Nomor tujuan.
- Nominal.
- Harga modal.
- Harga jual.
- Biaya admin.
- Keuntungan.
- Referensi provider.
- Status.
- Pesan error.
- Waktu.
- Operator.
- Idempotency key.
- Raw response yang telah disanitasi.

## Status

- Draft.
- Menunggu.
- Diproses.
- Berhasil.
- Gagal.
- Dibatalkan.
- Dikembalikan.

## Tahap MVP

- Pencatatan manual.
- Input nomor tujuan.
- Input harga modal.
- Input harga jual.
- Input referensi.
- Status.
- Laporan laba.
- Riwayat.

## Tahap Lanjutan

- API distributor.
- Inquiry.
- Pembelian.
- Cek status.
- Cek saldo.
- Callback.
- Retry aman.
- Rekonsiliasi.
- Webhook signature.
- Idempotency.

## Aturan Keamanan

- Jangan menyimpan PIN.
- Jangan menyimpan OTP.
- Jangan menyimpan password e-wallet.
- Nomor tujuan harus dikonfirmasi.
- Transaksi tidak boleh diproses ulang dengan idempotency key sama.
- AI tidak boleh menjalankan transaksi tanpa konfirmasi manusia.

## Acceptance Criteria

- Laba transaksi digital dapat dihitung.
- Transaksi gagal tidak dihitung sebagai pendapatan.
- Nomor tujuan tampil di halaman konfirmasi.
- Callback duplikat tidak menggandakan transaksi.

---

# 8.11 Pelanggan

## Data

- Nama.
- Nomor WhatsApp.
- Email.
- Alamat.
- Jenis pelanggan.
- Catatan.
- Total transaksi.
- Total pesanan.
- Piutang.
- Poin loyalitas tahap lanjutan.
- Preferensi komunikasi.
- Izin promosi.
- Riwayat.

## Kategori Pelanggan

- Umum.
- Pelajar.
- Guru.
- Sekolah.
- Kantor.
- UMKM.
- Instansi.
- Pelanggan tetap.

## Acceptance Criteria

- Pelanggan dapat dicari dari nama, nomor, atau email.
- Data duplikat dapat dideteksi.
- Riwayat transaksi pelanggan dapat dilihat.
- Promosi hanya dikirim jika pelanggan memberi izin.

---

# 8.12 Keuangan

## Fitur

- Pemasukan.
- Pengeluaran.
- Kas masuk.
- Kas keluar.
- Uang muka.
- Pelunasan.
- Piutang.
- Hutang supplier.
- Refund.
- Modal pembelian.
- Rekonsiliasi kasir.
- Tutup buku harian.
- Laporan laba kotor.
- Laporan arus kas sederhana.
- Kategori pengeluaran.

## Kategori Pengeluaran Awal

- Listrik.
- Internet.
- Sewa.
- Gaji.
- Kertas.
- Tinta.
- Perawatan printer.
- Peralatan toko.
- Transportasi.
- Domain.
- Hosting.
- Software.
- Pengeluaran lainnya.

## Aturan Bisnis

- Transaksi keuangan tidak dihapus permanen.
- Koreksi menggunakan reversal atau adjustment.
- Refund membutuhkan alasan.
- Refund di atas batas memerlukan owner.
- Saldo kas berdasarkan transaksi valid.
- AI hanya memberikan analisis.

## Acceptance Criteria

- Owner dapat melihat pemasukan dan pengeluaran.
- Laba kotor dapat dipisahkan per kategori.
- Piutang dapat dilacak.
- Tutup kasir dapat membandingkan uang fisik dan sistem.

---

# 8.13 Laporan

## Laporan Operasional

- Penjualan harian.
- Penjualan bulanan.
- Penjualan per kasir.
- Produk terlaris.
- Produk tidak laku.
- Jasa terlaris.
- Pesanan belum selesai.
- Pesanan terlambat.
- Performa operator.
- Proyek aktif.
- Proyek terlambat.

## Laporan Keuangan

- Pendapatan.
- Pengeluaran.
- Laba kotor.
- Pendapatan per kategori.
- Laba per kategori.
- Piutang.
- Hutang supplier.
- Saldo kas.
- Rekonsiliasi kasir.

## Laporan Stok

- Stok saat ini.
- Nilai stok.
- Produk hampir habis.
- Produk habis.
- Stok masuk.
- Stok keluar.
- Barang rusak.
- Stock adjustment.
- Pembelian supplier.

## Export

- PDF.
- Excel.
- CSV.
- Cetak.

## Acceptance Criteria

- Filter tanggal tersedia.
- Laporan mengikuti timezone toko.
- Data export sama dengan data di layar.
- Nilai yang dibatalkan tidak masuk laporan pendapatan.

---

# 9. Website Publik

## Halaman

- Beranda.
- Tentang Kami.
- Layanan.
- Harga.
- ATK.
- Print dan fotokopi.
- Pulsa dan pembayaran.
- Desain.
- Website.
- Aplikasi Android.
- Portofolio.
- Pesan layanan.
- Upload file.
- Cek pesanan.
- FAQ.
- Kontak.
- Lokasi.
- Kebijakan privasi.
- Syarat layanan.

## Hero Utama

**Younz Digital Center**

Print, Fotokopi, ATK, Pulsa, Pembayaran, Desain, Website & Aplikasi.

## CTA

- Pesan Sekarang.
- Kirim File.
- Lihat Harga.
- Cek Pesanan.
- Tanya AI.
- Hubungi WhatsApp.

## Persyaratan SEO

- Meta title.
- Meta description.
- Open Graph.
- Canonical URL.
- Sitemap.
- Robots.txt.
- Structured data bisnis lokal.
- Halaman cepat.
- Mobile-first.
- URL ramah mesin pencari.

---

# 10. Integrasi AI

# 10.1 Tujuan Integrasi AI

AI digunakan untuk:

- Membantu pelanggan.
- Mempercepat input.
- Mengubah bahasa natural menjadi data terstruktur.
- Membantu menyusun brief.
- Membantu analisis stok.
- Membantu analisis penjualan.
- Membantu ringkasan keuangan.
- Membantu pembuatan konten.
- Membantu dokumentasi proyek.
- Mencari informasi dari knowledge base.

AI tidak digunakan untuk:

- Menyetujui pembayaran.
- Menjalankan refund.
- Mengubah stok secara langsung.
- Menghapus transaksi.
- Menentukan harga final tanpa pemeriksaan.
- Mengirim promosi massal tanpa persetujuan.
- Menjalankan PPOB tanpa konfirmasi.
- Mengakses seluruh data tanpa batas.

---

# 10.2 AI Agent

Agent yang direncanakan:

- `CustomerServiceAgent`
- `OrderIntakeAgent`
- `PrintEstimatorAgent`
- `DesignBriefAgent`
- `ProjectPlannerAgent`
- `StockAdvisorAgent`
- `SalesAnalystAgent`
- `FinancialSummaryAgent`
- `ContentWriterAgent`
- `KnowledgeBaseAgent`

Setiap agent wajib memiliki:

- Instructions.
- Scope.
- Input schema.
- Output schema.
- Tools.
- Policy.
- Provider.
- Model.
- Batas token.
- Batas biaya.
- Timeout.
- Retry.
- Logging.
- Human approval jika diperlukan.

---

# 10.3 Chatbot Pelanggan

## Kemampuan

- Menjawab jam buka.
- Menjawab lokasi.
- Menjawab harga.
- Menjawab layanan.
- Memberikan panduan upload file.
- Menjelaskan status pesanan.
- Menjelaskan jenis kertas.
- Menjelaskan jasa desain.
- Menjelaskan jasa website.
- Mengarahkan ke operator manusia.
- Membuat draft pesanan.

## Batasan

- Tidak boleh mengarang harga.
- Harus menggunakan knowledge base.
- Tidak boleh membuka data pelanggan lain.
- Status pesanan hanya diberikan setelah verifikasi.
- Jawaban yang tidak pasti harus diarahkan ke pegawai.

---

# 10.4 AI Order Intake

AI menerima input seperti:

> Print file ini dua rangkap, warna, A4, bolak-balik, dijilid dan diambil sore.

Output terstruktur:

```json
{
  "service": "print",
  "paper_size": "A4",
  "color_mode": "color",
  "sides": "duplex",
  "copies": 2,
  "finishing": "binding",
  "pickup_time": "afternoon"
}
```

## Persyaratan

- Output harus divalidasi server.
- Field tidak dikenal harus diabaikan.
- Harga dihitung oleh sistem.
- Operator memeriksa hasil.
- Input yang ambigu menghasilkan pertanyaan lanjutan.
- Data yang belum pasti diberi flag.

---

# 10.5 AI Knowledge Base

## Sumber Data

- Daftar harga.
- SOP.
- FAQ.
- Kebijakan revisi.
- Informasi layanan.
- Informasi produk.
- Panduan pegawai.
- Template penawaran.
- Dokumentasi proyek.
- Panduan pembayaran.
- Kebijakan privasi.

## Pipeline

1. Dokumen diunggah.
2. Konten diekstrak.
3. Konten dibersihkan.
4. Dokumen dipecah menjadi chunk.
5. Embedding dibuat.
6. Vector disimpan.
7. Query dicari berdasarkan kemiripan.
8. Hasil dapat direrank.
9. Jawaban dibuat dengan citation internal.
10. Feedback disimpan.

## Persyaratan

- Dokumen memiliki status draft, aktif, atau arsip.
- Hanya dokumen aktif yang digunakan.
- Index dapat dibangun ulang.
- Perubahan dokumen membuat embedding diperbarui.
- Akses dokumen mengikuti izin.

---

# 10.6 AI Stock Advisor

## Kemampuan

- Mendeteksi stok hampir habis.
- Menghitung kecepatan penjualan.
- Menyarankan reorder.
- Mendeteksi slow-moving item.
- Mendeteksi stock movement tidak biasa.
- Membuat draft daftar belanja.

## Batasan

- Tidak membuat purchase order final.
- Tidak mengubah stok.
- Data minimal harus cukup.
- Rekomendasi menjelaskan alasannya.

---

# 10.7 AI Sales Analyst

## Kemampuan

- Membandingkan penjualan antarperiode.
- Menemukan kategori terlaris.
- Menemukan penurunan penjualan.
- Menyarankan bundling.
- Menyarankan promosi.
- Membuat ringkasan harian.
- Membuat ringkasan bulanan.

## Batasan

- Tidak mengubah harga.
- Tidak mengirim promosi.
- Tidak menggunakan data pelanggan untuk promosi tanpa izin.

---

# 10.8 AI Financial Summary

## Kemampuan

- Menjelaskan perubahan pendapatan.
- Menjelaskan pengeluaran terbesar.
- Menunjukkan layanan paling menguntungkan.
- Mendeteksi transaksi tidak biasa.
- Membuat ringkasan arus kas.
- Membandingkan periode.

## Batasan

- Bukan pengganti akuntan.
- Tidak mengubah transaksi.
- Tidak menghapus transaksi.
- Tidak menyetujui refund.
- Harus menampilkan periode data.

---

# 10.9 AI Design Brief Assistant

## Kemampuan

- Mengubah chat menjadi brief.
- Menemukan informasi yang kurang.
- Menyusun pilihan konsep.
- Membuat slogan.
- Membuat copy.
- Membuat caption.
- Membuat prompt gambar.
- Merangkum revisi.

## Batasan

- Tidak mengirim hasil final tanpa desainer.
- Tidak menjanjikan hak cipta tanpa verifikasi.
- File pelanggan harus tetap private.

---

# 10.10 AI Project Planner

## Kemampuan

- Mengubah ide menjadi requirement.
- Membuat draft PRD.
- Membuat user story.
- Membuat acceptance criteria.
- Menyusun milestone.
- Menyusun pertanyaan discovery.
- Merangkum rapat.
- Membuat laporan progres.
- Membuat dokumentasi pengguna.

## Batasan

- Estimasi harga dan waktu harus disetujui manusia.
- AI tidak boleh menerbitkan perubahan scope tanpa approval.

---

# 11. Human Approval

Tindakan berikut wajib mendapat persetujuan manusia:

- Mengubah harga.
- Mengubah stok manual.
- Menghapus atau membatalkan transaksi.
- Refund.
- Pembelian supplier.
- Mengirim promosi massal.
- Memproses PPOB.
- Mengubah data keuangan.
- Mengubah hak akses.
- Mengirim file final.
- Menyetujui estimasi proyek.
- Menyetujui perubahan scope.

Status approval:

- Draft.
- Menunggu persetujuan.
- Disetujui.
- Ditolak.
- Kedaluwarsa.
- Dibatalkan.

---

# 12. Laravel MCP

MCP digunakan untuk menyediakan tools yang dapat dipakai AI client berizin.

## Read-only Tools

- Cari produk.
- Cek stok.
- Cari pesanan.
- Cek antrean.
- Baca laporan.
- Cari pelanggan.
- Cari SOP.
- Cari knowledge base.

## Draft Tools

- Membuat draft pesanan.
- Membuat draft penawaran.
- Membuat draft promosi.
- Membuat draft purchase order.
- Membuat draft laporan.

## Approval Tools

- Mengajukan perubahan harga.
- Mengajukan stock adjustment.
- Mengajukan refund.
- Mengajukan pembelian.
- Mengajukan promosi.

## Aturan

- Semua tool harus menggunakan policy.
- Tool wajib memiliki audit log.
- Tool hanya menerima input terstruktur.
- Tool tidak boleh melewati validasi.
- Tool write harus menggunakan approval bila berisiko.

---

# 13. Arsitektur Teknis

## 13.1 Stack

- Laravel 13.
- PHP 8.3 atau lebih baru.
- PostgreSQL.
- `pgvector`.
- Redis.
- Livewire.
- Blade.
- Tailwind CSS.
- Alpine.js jika diperlukan.
- Vite.
- Laravel Queue.
- Laravel Horizon.
- Laravel Scheduler.
- Laravel Reverb.
- Laravel Sanctum.
- Laravel AI SDK.
- Laravel MCP.
- Object storage.
- Pest.

## 13.2 Struktur Aplikasi

```text
app/
├── Actions/
├── Ai/
│   ├── Agents/
│   ├── Tools/
│   ├── Prompts/
│   ├── Schemas/
│   ├── Policies/
│   └── Knowledge/
├── Domain/
│   ├── Catalog/
│   ├── Inventory/
│   ├── Sales/
│   ├── Services/
│   ├── Projects/
│   ├── Finance/
│   └── Customers/
├── Events/
├── Jobs/
├── Livewire/
├── Models/
├── Notifications/
├── Policies/
├── Services/
└── Support/
```

## 13.3 Prinsip Arsitektur

- Controller tipis.
- Business logic berada di Action atau Service.
- Policy digunakan untuk otorisasi.
- Form Request atau validasi Livewire digunakan untuk input.
- Semua nilai keuangan menggunakan integer minor unit atau decimal terkontrol.
- Job berat dijalankan di queue.
- Integrasi eksternal menggunakan adapter.
- AI dipisahkan dari controller.
- Semua transaksi kritis menggunakan database transaction.
- Event digunakan untuk side effect.
- Model tidak memuat terlalu banyak business logic.

---

# 14. Struktur Database

## 14.1 Tabel Pengguna

```text
users
roles
permissions
role_user
permission_role
user_sessions
user_devices
```

## 14.2 Tabel Pelanggan

```text
customers
customer_addresses
customer_notes
customer_consents
```

## 14.3 Tabel Katalog dan Stok

```text
categories
products
product_units
product_prices
product_images
suppliers
supplier_products
purchases
purchase_items
purchase_payments
stock_movements
stock_opnames
stock_opname_items
```

## 14.4 Tabel Penjualan

```text
cash_sessions
sales
sale_items
payments
refunds
refund_items
receipts
```

## 14.5 Tabel Layanan

```text
services
service_prices
service_orders
service_order_items
service_files
service_assignments
service_order_status_histories
service_revisions
```

## 14.6 Tabel PPOB

```text
digital_products
digital_providers
digital_transactions
digital_transaction_attempts
digital_callbacks
provider_balances
```

## 14.7 Tabel Proyek

```text
projects
project_requirements
project_milestones
project_tasks
project_files
project_payments
project_revisions
project_credentials
```

## 14.8 Tabel Keuangan

```text
expenses
expense_categories
income_entries
cash_movements
receivables
payables
```

## 14.9 Tabel AI

```text
agent_conversations
agent_conversation_messages
ai_requests
ai_responses
ai_usage_logs
ai_feedback
ai_prompt_versions
ai_tool_executions
ai_approvals
knowledge_documents
knowledge_document_chunks
knowledge_sources
generated_contents
```

## 14.10 Tabel Sistem

```text
notifications
activity_logs
settings
integrations
webhooks
failed_jobs
jobs
job_batches
```

---

# 15. Penomoran Dokumen

Format nomor:

```text
INV-20260720-0001
ORD-20260720-0001
DIG-20260720-0001
PRJ-20260720-0001
PUR-20260720-0001
EXP-20260720-0001
RFD-20260720-0001
```

Persyaratan:

- Unik.
- Tidak berubah.
- Dihasilkan server.
- Aman dari race condition.
- Dapat dicari.
- Memiliki prefix sesuai jenis.

---

# 16. File dan Penyimpanan

## Jenis File

- Dokumen pelanggan.
- Hasil scan.
- File desain.
- Preview.
- File final.
- File proyek.
- Invoice.
- Struk.
- Knowledge base.

## Aturan

- File pelanggan private.
- Nama file diacak.
- Metadata asli dapat disimpan.
- MIME type divalidasi.
- Ukuran file dibatasi.
- File berbahaya ditolak.
- Gunakan signed URL sementara.
- File sensitif tidak ditempatkan di folder public.
- Akses file dicatat.
- File memiliki retention policy.
- File yang tidak diperlukan dapat dihapus sesuai kebijakan.

---

# 17. Notifikasi

## Kanal

- In-app.
- Email.
- WhatsApp tahap integrasi.
- Push notification tahap aplikasi Android.

## Event

- Pesanan diterima.
- Estimasi tersedia.
- Estimasi disetujui.
- Pembayaran diterima.
- Pesanan dikerjakan.
- Pesanan siap diambil.
- Pesanan selesai.
- Revisi diminta.
- Deadline mendekat.
- Stok hampir habis.
- Transaksi PPOB gagal.
- Approval menunggu.
- Proyek memiliki progres baru.

## Persyaratan

- Template dapat diubah.
- Notifikasi tidak boleh mengandung data sensitif berlebihan.
- Status pengiriman dicatat.
- Pengiriman gagal dapat dicoba ulang.

---

# 18. Audit Log

Aktivitas yang dicatat:

- Login.
- Logout.
- Login gagal.
- Perubahan role.
- Perubahan harga.
- Perubahan stok.
- Pembatalan transaksi.
- Refund.
- Pembacaan credential sensitif.
- Penghapusan file.
- Perubahan status pesanan.
- Penggunaan AI tool.
- Approval.
- Perubahan pengaturan.
- Integrasi webhook.

Data audit:

- User.
- Action.
- Entity.
- Entity ID.
- Nilai sebelum.
- Nilai sesudah.
- Waktu.
- IP.
- User agent.
- Metadata.

Audit log tidak boleh diedit dari aplikasi.

---

# 19. Keamanan

## Persyaratan Umum

- HTTPS.
- Secure cookie.
- CSRF protection.
- XSS protection.
- SQL injection prevention.
- Rate limiting.
- Password hashing.
- 2FA owner.
- Session timeout.
- Policy-based access.
- Input validation.
- Output escaping.
- Secret di environment.
- Backup terenkripsi.
- Security header.
- CORS terkontrol.
- File upload validation.
- Signed URL.
- Webhook signature.
- Idempotency.

## Keamanan AI

- API key tidak dikirim ke browser.
- Data sensitif disaring.
- PIN, OTP, dan password tidak dikirim ke AI.
- Prompt injection diperlakukan sebagai input tidak tepercaya.
- Tool calling tetap melalui policy.
- AI tidak mendapat akses database langsung.
- Output AI divalidasi.
- Batas biaya harian.
- Batas token.
- Batas request.
- Log AI disanitasi.
- Data pelanggan tidak digunakan untuk training tanpa izin.
- Riwayat AI dapat dihapus sesuai kebijakan.

---

# 20. Non-Functional Requirements

## 20.1 Performa

- Halaman utama dashboard dimuat cepat pada koneksi normal.
- POS dapat digunakan tanpa penundaan yang mengganggu.
- Pencarian produk responsif.
- Job AI tidak memblokir request utama.
- Export laporan dijalankan melalui queue jika besar.
- Query memiliki index yang sesuai.
- Dashboard menggunakan cache untuk agregasi berat.

## 20.2 Ketersediaan

- Sistem memiliki backup.
- Job gagal dapat diulang.
- Integrasi eksternal memiliki retry.
- Failure provider AI tidak menghentikan POS.
- Failure PPOB tidak merusak transaksi kasir.
- Website publik memiliki halaman error yang baik.

## 20.3 Skalabilitas

- Queue dapat menambah worker.
- Storage dapat dipindahkan ke object storage.
- Database siap read replica tahap lanjutan.
- Modul dapat dipisahkan bila beban bertambah.
- API versioning disiapkan.

## 20.4 Maintainability

- Coding standard.
- Test otomatis.
- Dokumentasi.
- Modular domain.
- Migration rapi.
- Seeders.
- Factories.
- CI.
- Static analysis.
- Logging terstruktur.

## 20.5 Accessibility

- Navigasi keyboard.
- Label form.
- Kontras memadai.
- Pesan error jelas.
- Mobile responsive.
- Ukuran target sentuh memadai.

---

# 21. API Requirements

## Format

- JSON.
- Versioning `/api/v1`.
- Pagination.
- Filtering.
- Sorting.
- Validation error terstruktur.
- Idempotency untuk transaksi.
- Rate limiting.
- Authentication Sanctum.
- Policy server-side.

## Endpoint Awal

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/me

GET    /api/v1/services
GET    /api/v1/products
POST   /api/v1/orders
GET    /api/v1/orders/{order}
POST   /api/v1/orders/{order}/files
POST   /api/v1/orders/{order}/approve-estimate

GET    /api/v1/projects
GET    /api/v1/projects/{project}

POST   /api/v1/ai/chat
POST   /api/v1/ai/order-intake
```

---

# 22. Integrasi Eksternal

## Tahap Awal

- WhatsApp manual melalui link.
- Printer thermal.
- Email.
- Object storage.
- AI provider.

## Tahap Lanjutan

- WhatsApp gateway.
- PPOB provider.
- Payment gateway.
- Maps.
- Push notification.
- Analytics.
- Error monitoring.
- Cloud backup.

## Persyaratan Adapter

Setiap integrasi harus memiliki:

- Interface.
- Implementasi provider.
- Timeout.
- Retry.
- Logging.
- Error mapping.
- Sandbox mode.
- Health check.
- Secret management.

---

# 23. UI/UX Requirements

## Identitas

Nama:

**Younz Digital Center**

Tagline:

**Print, Fotokopi, ATK, Pulsa, Pembayaran, Desain, Website & Aplikasi**

## Prinsip Desain

- Bersih.
- Modern.
- Mudah dipahami masyarakat umum.
- Mobile-first.
- Tombol utama jelas.
- Status menggunakan badge.
- Risiko menggunakan peringatan visual.
- Form tidak terlalu panjang dalam satu layar.
- POS dioptimalkan untuk keyboard dan sentuhan.
- File upload memiliki progress.
- Harga ditampilkan transparan.
- AI diberi label.

## Navigasi Dashboard

- Ringkasan.
- Kasir.
- Pesanan.
- Produk.
- Stok.
- Pembelian.
- Transaksi Digital.
- Proyek.
- Pelanggan.
- Keuangan.
- Laporan.
- AI Assistant.
- Pegawai.
- Pengaturan.

## Mobile

- Sidebar berubah menjadi drawer atau bottom navigation sesuai kebutuhan.
- Tombol aksi utama mudah dijangkau.
- Tabel berubah menjadi card atau responsive table.
- POS tetap dapat digunakan di tablet.

---

# 24. KPI Produk

## Bisnis

- Jumlah transaksi harian.
- Pendapatan per kategori.
- Laba kotor.
- Nilai rata-rata transaksi.
- Produk terlaris.
- Pelanggan kembali.
- Piutang.
- Selisih stok.
- Pesanan terlambat.
- Waktu penyelesaian pesanan.

## Operasional

- Waktu transaksi POS.
- Waktu respons pesanan.
- Rasio pesanan selesai tepat waktu.
- Jumlah refund.
- Jumlah transaksi gagal.
- Akurasi stok.
- Lama antrean.

## AI

- Jumlah percakapan.
- Rasio jawaban membantu.
- Rasio eskalasi ke manusia.
- Akurasi ekstraksi order.
- Biaya AI per transaksi.
- Jumlah rekomendasi diterima.
- Jumlah output ditolak.
- Jumlah tool call gagal.

---

# 25. Roadmap

## Fase 1 — Fondasi

- Setup Laravel 13.
- PostgreSQL.
- Redis.
- Livewire.
- Auth.
- Role dan permission.
- Dashboard dasar.
- Audit log.
- CI dan testing.
- Pengaturan toko.

## Fase 2 — POS dan Stok

- Produk.
- Kategori.
- Supplier.
- Pembelian.
- Stok.
- POS.
- Pembayaran.
- Sesi kasir.
- Struk.
- Pengeluaran.

## Fase 3 — Pesanan Jasa

- Print.
- Fotokopi.
- Scan.
- Ketik.
- Upload file.
- Antrean.
- Status.
- Invoice.
- Desain.
- Revisi.

## Fase 4 — Website Publik

- Landing page.
- Harga.
- Layanan.
- Portofolio.
- Pesan layanan.
- Cek status.
- Portal pelanggan.

## Fase 5 — AI Dasar

- AI SDK.
- Knowledge base.
- Customer service agent.
- Order intake.
- Structured output.
- Usage log.
- Feedback.
- Cost limit.

## Fase 6 — Proyek dan AI Internal

- Proyek website.
- Proyek aplikasi.
- Milestone.
- Pembayaran termin.
- Design brief agent.
- Project planner.
- Stock advisor.
- Sales analyst.
- Financial summary.

## Fase 7 — PPOB

- Transaksi manual.
- Produk digital.
- Laporan.
- API provider.
- Callback.
- Rekonsiliasi.
- Saldo provider.

## Fase 8 — Aplikasi Android

- API.
- Sanctum.
- Login.
- Pesanan.
- Upload.
- Status.
- Notifikasi.
- Chat AI.
- Riwayat.

## Fase 9 — MCP

- MCP server.
- Read tools.
- Draft tools.
- Approval tools.
- Integrasi asisten operasional.

---

# 26. MVP

MVP wajib mencakup:

1. Login owner dan kasir.
2. Role dan permission.
3. Produk.
4. Kategori.
5. Supplier.
6. Stok.
7. POS.
8. Penjualan.
9. Pembayaran.
10. Sesi kasir.
11. Pesanan jasa.
12. Pelanggan.
13. Pengeluaran.
14. Transaksi digital manual.
15. Laporan harian.
16. Website publik sederhana.
17. Upload file.
18. Cek status pesanan.
19. Chatbot FAQ.
20. AI Order Intake.
21. Knowledge base harga dan layanan.
22. Audit log.

MVP belum wajib mencakup:

- Aplikasi Android.
- Multi-outlet.
- AI voice.
- Image generation.
- Semua integrasi PPOB.
- Promosi otomatis.
- Akuntansi penuh.
- MCP write access penuh.
- Prediksi kompleks.

---

# 27. Acceptance Criteria MVP

MVP dianggap siap diuji apabila:

- Owner dapat login.
- Kasir dapat login.
- Hak akses bekerja.
- Produk dapat ditambah dan dijual.
- Stok berkurang setelah penjualan.
- Transaksi gagal tidak mengurangi stok.
- Struk dapat dicetak.
- Kasir dapat membuka dan menutup sesi.
- Pesanan jasa dapat dibuat.
- File pelanggan dapat diunggah secara private.
- Status pesanan dapat diperbarui.
- Pelanggan dapat mengecek status.
- Transaksi digital manual dapat dicatat.
- Pengeluaran dapat dicatat.
- Laporan harian dapat dilihat.
- Chatbot menjawab dari knowledge base.
- AI dapat mengubah pesan pesanan menjadi data terstruktur.
- Output AI harus diperiksa server.
- Audit log mencatat tindakan penting.
- Backup dapat dijalankan.
- Test kritis lulus.

---

# 28. Testing Requirements

## Unit Test

- Perhitungan harga.
- Diskon.
- Laba.
- Stok.
- Status.
- Approval.
- Nomor dokumen.
- AI schema validation.

## Feature Test

- Login.
- Permission.
- POS.
- Stok.
- Pesanan.
- File.
- Refund.
- Laporan.
- API.
- AI tool.

## Integration Test

- Redis.
- Queue.
- Storage.
- AI provider.
- Email.
- Webhook.
- PPOB sandbox.

## Security Test

- IDOR.
- Privilege escalation.
- File access.
- Rate limiting.
- CSRF.
- XSS.
- Injection.
- Webhook spoofing.
- Prompt injection terhadap AI tool.

## User Acceptance Test

Skenario:

- Penjualan ATK.
- Jasa print.
- Pesanan desain.
- Transaksi pulsa.
- Tutup kasir.
- Refund.
- Upload file.
- Cek status.
- Chat AI.
- Approval perubahan stok.

---

# 29. Risiko dan Mitigasi

## Risiko: Scope terlalu besar

Mitigasi:

- Bangun per fase.
- Prioritaskan MVP.
- Hindari integrasi kompleks di awal.

## Risiko: AI menghasilkan informasi salah

Mitigasi:

- Knowledge base.
- Structured output.
- Human review.
- Citation internal.
- Batas tool.

## Risiko: Biaya AI membengkak

Mitigasi:

- Token limit.
- Cache.
- Model routing.
- Daily budget.
- Queue.
- Monitoring.

## Risiko: Data pelanggan bocor

Mitigasi:

- Policy.
- Signed URL.
- Private storage.
- Encryption.
- Sanitasi prompt.
- Audit log.

## Risiko: Transaksi PPOB ganda

Mitigasi:

- Idempotency.
- Callback verification.
- Lock.
- Rekonsiliasi.
- State machine.

## Risiko: Stok tidak akurat

Mitigasi:

- Stock movement.
- Database transaction.
- Stock opname.
- Audit.

## Risiko: Gangguan provider AI

Mitigasi:

- Fallback provider.
- Timeout.
- Retry.
- AI tidak memblokir POS.
- Mode manual.

---

# 30. Definition of Done

Sebuah fitur dianggap selesai apabila:

- Requirement terpenuhi.
- UI dapat digunakan.
- Validasi tersedia.
- Authorization tersedia.
- Test kritis tersedia.
- Audit log tersedia bila diperlukan.
- Error handling tersedia.
- Dokumentasi tersedia.
- Tidak ada secret di source code.
- Performa dapat diterima.
- Security review dilakukan.
- Product owner menyetujui.

---

# 31. Deployment

## Environment

- Local.
- Testing.
- Staging.
- Production.

## Persyaratan Production

- HTTPS.
- PostgreSQL.
- Redis.
- Queue worker.
- Scheduler.
- Horizon.
- Backup.
- Monitoring.
- Error tracking.
- Log rotation.
- Object storage.
- Secure environment.
- Database migration process.
- Rollback plan.
- Health check.

## Proses Rilis

1. Test otomatis.
2. Build asset.
3. Backup.
4. Migration.
5. Deploy.
6. Cache.
7. Restart worker.
8. Health check.
9. Smoke test.
10. Monitoring.

---

# 32. Pengembangan Lanjutan

Setelah MVP stabil, produk dapat dikembangkan menjadi:

- Aplikasi Android pelanggan.
- Aplikasi kasir tablet.
- Multi-outlet.
- Loyalty program.
- Membership sekolah dan kantor.
- Pemesanan pickup dan delivery.
- Marketplace layanan lokal.
- Integrasi lebih banyak provider PPOB.
- AI voice note.
- AI document formatting.
- Prediksi stok.
- Customer segmentation.
- Otomatisasi follow-up dengan approval.
- Portal supplier.
- White-label.
- Franchise.

---

# 33. Kesimpulan

Younz Digital Center akan dibangun sebagai sistem operasional terpadu, bukan sekadar website profil.

Laravel 13 menjadi fondasi untuk:

- Website publik.
- Kasir.
- Stok.
- Pesanan.
- Transaksi digital.
- Keuangan.
- Proyek.
- Pelanggan.
- API.
- Aplikasi Android.
- AI assistant.
- Knowledge base.
- MCP.

Strategi pengembangan harus dimulai dari proses bisnis inti: kasir, stok, pesanan, pelanggan, dan laporan. AI ditambahkan untuk membantu input, pencarian informasi, analisis, dan pembuatan draft, tetapi tidak menggantikan persetujuan manusia pada transaksi, keuangan, stok, dan perubahan penting.

---

# 34. Persetujuan Dokumen

| Peran | Nama | Status | Tanggal |
|---|---|---|---|
| Product Owner |  | Belum disetujui |  |
| Project Manager |  | Belum disetujui |  |
| Lead Developer |  | Belum disetujui |  |
| UI/UX Designer |  | Belum disetujui |  |
| QA |  | Belum disetujui |  |

