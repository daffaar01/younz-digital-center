# Katalog dan vision WhatsApp

Pertanyaan `pembayaran apa yang tersedia?` dan `bisa bayar apa?` membaca katalog lokal Digiflazz, bukan metode bayar. Coba `pulsa Telkomsel tersedia?` atau `tagihan PLN apa yang tersedia?`. Katalog tidak membuat transaksi dan tidak menyinkronisasi provider otomatis. Harga pascabayar baru diketahui setelah inquiry.

Daftar WhatsApp ditampilkan per halaman: maksimum 20 kategori/brand atau 10 produk. Balas `LANJUT KATALOG`, `Masih ada layanan lainnya?`, atau `produk lainnya?` untuk halaman berikutnya. Konteks katalog tersimpan per chat selama 15 menit; pertanyaan katalog baru mengulang dari halaman pertama. Tanpa konteks, bot meminta pertanyaan katalog baru. Query ketersediaan diulang setiap halaman; perubahan katalog selama percakapan dapat menggeser urutan hasil.

Chat biasa memakai provider AI yang sudah dikonfigurasi. Perintah BELI/CEK TAGIHAN dan sandi operator tetap digunakan untuk transaksi, bukan hasil AI atau isi gambar.

## Cek status transaksi

Kirim `Cek transaksi TOP-20260906-0002` (ganti nomor sesuai order). Alias `Cek order` dan `Cek status` juga diterima. Bot membaca status pembayaran dan pemenuhan terakhir dari database, menampilkan tujuan tersamarkan, dan tidak memanggil AI maupun provider. Ini bukan polling status langsung ke Digiflazz dan tidak membuat pembayaran baru.

Hanya nomor WhatsApp pemesan (`customer_phone`), bukan nomor tujuan pengisian, yang berhak melihat status. Format nomor lokal 08… dan internasional 628… disetarakan. Order tidak ditemukan dan nomor tidak cocok menerima pesan penolakan yang sama. Batasnya 10 pengecekan per menit per nomor melalui cache rate limiter. Sesi pemilihan paket/konfirmasi yang aktif tidak diubah. Perintah ini terpisah dari `CEK TAGIHAN` dan `/cek` pelacakan pesanan jasa.

## Memilih layanan dengan nomor

Setelah daftar menampilkan misalnya `1. Data — AXIS`, balas `1` untuk membuka produk yang tersedia pada kategori Data dan brand AXIS tersebut. Nama, harga jual, dan deskripsi paket ditampilkan dari katalog tersimpan; kuota/masa aktif tidak ditebak jika sumber tidak mencantumkannya. `LANJUT KATALOG` melanjutkan produk pada kelompok yang sama.

Pilihan angka berlaku untuk halaman layanan terakhir selama 15 menit. Membuka `/menu` mengganti konteks angka ke menu; membuka katalog menggantinya kembali ke katalog. Untuk operator terdaftar, angka pada daftar produk Data/Pulsa dan GoPay prabayar membuka permintaan nomor HP tujuan. Pemilihan belum membuat order. Kirim nomor dalam format 08…, 628…, atau +628… tanpa spasi dalam 15 menit; sistem membuat draft dan menampilkan ringkasan harga terbaru serta tujuan tersamarkan. Balas `KONFIRMASI <sandi>` dalam 10 menit untuk memproses. Angka sandi saja tidak diterima pada alur terpandu, agar nomor HP yang terkirim ulang tidak dianggap konfirmasi. `BATAL` menghapus sesi dan membuat draft pending kedaluwarsa; transaksi yang sudah diproses tidak dibatalkan. Produk selain Data/Pulsa dan GoPay prabayar masih memakai perintah existing. Perintah BELI lama tetap tersedia.

GoPay dibatasi pada kategori `E-Money`, brand `GO PAY`/`GOPAY`, dan tipe prepaid. Alias `Beli gopay 25000 ke nomor 081234567890` dan `Beli Go Pay 25.000 ke nomor 081234567890` mencocokkan nominal utuh dari nama produk, bukan harga jual setelah markup. Jika beberapa SKU cocok, bot meminta nama lebih spesifik, tidak memilih otomatis yang termurah. Nomor HP GoPay divalidasi dan dinormalisasi menjadi 628…; baik pilihan angka maupun BELI GoPay meminta `KONFIRMASI <sandi>`. Footer katalog hanya menawarkan pilihan pembelian pada nomor produk yang didukung. Tidak ada dukungan otomatis untuk seluruh e-wallet.

Pengujian otomatis mencakup alur sukses, sandi salah/bare, kirim ulang nomor, konfirmasi berulang, pembatalan dan kedaluwarsa. Pengujian konkurensi lintas jalur BELI/terpandu, seluruh kegagalan transport, dan uji WhatsApp hidup belum dilakukan.

## Pengamanan transaksi dan deployment worker

Konfirmasi aktif disimpan dalam tabel `whatsapp_pending_confirmations`, sehingga cache hilang tidak mengganti draft yang masih menunggu konfirmasi. Transisi model order menjadi Paid/Queued menyimpan intent pada `topup_fulfillment_outbox` dalam transaksi database yang sama. Kegagalan penulisan intent menggagalkan transisi pembayaran; intent yang belum dipublikasikan dicoba ulang oleh `topup:recover-fulfillment` setiap lima menit melalui scheduler. Perintah ini tidak membutuhkan Midtrans aktif. Recovery tetap memerlukan scheduler dan worker yang benar-benar berjalan.

`KEMBALI`, `GANTI NOMOR`, dan `GANTI PAKET` menggunakan sesi katalog. Mengganti nomor/paket membuat draft unpaid lama kedaluwarsa; order yang sudah dibayar/diproses tidak dibatalkan. Halaman katalog, bookmark, menu dan pilihan paket tersimpan di `whatsapp_navigation_states` dengan expiry 15 menit (menu 10 menit), sehingga cache hilang tidak menghapus navigasi. Seluruh routing inbound memakai lease operator database yang sama; token lama tidak berhak mengubah state setelah kepemilikan berpindah. Navigasi transaksi direct BELI/CEK TAGIHAN meminta BATAL dan perintah baru, bukan mengalihkannya ke input Data/Pulsa.

Urutan deployment yang harus ditinjau administrator:

1. Hentikan penerimaan transaksi baru dan tunggu pekerjaan aktif selesai. Jangan membersihkan cache atau mematikan paksa worker finansial.
2. Cadangkan database sesuai prosedur operasional. Jalankan semua migrasi `2026_09_06_010000` sampai `2026_09_06_060000` sebelum kode baru menerima trafik: outbox, pending, execution lease, operator lease, generation outbox, dan navigation state.
3. Tinjau konfigurasi efektif antrean: database/Redis `retry_after` minimal 300 detik, lebih panjang daripada eksekusi worker. Override `.env` lama tidak berubah hanya karena default source diperbarui.
4. Tinjau template `deploy/YounzQueue.xml.example`, lokasi PHP, direktori kerja, log privat, dan scheduler. Template tidak otomatis memasang atau mengubah service.
5. `scripts/repair-younz-queue.ps1` kini default pemeriksaan saja. `-Apply` eksplisit hanya diterima ketika service berhenti, tidak ada worker PHP lain, dan antrean kosong. Skrip tidak menghentikan worker maupun melakukan restart percobaan; jika start telah dicoba, kegagalan memerlukan inspeksi manual, bukan rollback konfigurasi saat worker mungkin aktif.
6. Setelah aktivasi yang disetujui, bandingkan `queue:health` beberapa kali dengan log service: backlog age, reserved, failed, dan intent belum terbit. Status Running atau satu pembacaan counts bukan bukti progress.

Supervisor `scripts/queue-supervisor.php` menjalankan ulang child setelah exit 0 maupun error. State directory harus sudah ada, privat, dan hanya writable oleh akun service/admin. Lock file mencegah supervisor ganda pada state directory yang sama. Stop menulis marker dan menunggu child selesai; Laravel `--max-time=200` berhenti di antara jobs, bukan memotong request aktif. Template memakai stop budget 600 detik. Tes fake-child membuktikan restart normal/error dan stop marker; pemasangan WinSW sesungguhnya tetap perlu verifikasi operasional.

**Batas Windows:** PHP native Windows tidak menyediakan jaminan hard timeout berbasis pcntl. Nilai `--timeout` dan `--max-time` bukan hard watchdog untuk request yang hang. Jangan melakukan force-stop untuk uji. Provider execution memakai lease database 300 detik dan token pada setiap hasil, referensi Digiflazz tetap sama; sesudah crash pembayaran postpaid yang ambigu hanya diperiksa statusnya. Publisher outbox mengizinkan delivery berulang dan acknowledge hanya generation yang dikirim, sehingga rearm tidak tertimpa acknowledgment lama. Pengujian interleaving deterministik tidak membuktikan semua race database produksi. Tidak ada klaim exactly-once pembayaran eksternal.

## Mengaktifkan gambar

Atur konfigurasi deployment (jangan commit secret):

```dotenv
AI_VISION_ENABLED=true
AI_VISION_MODEL=model-vision-yang-didukung-provider-anda
AI_VISION_ESTIMATED_TOKENS=8000
```

Tanpa URL/key vision khusus, gambar memakai provider default berdriver `openai` atau `openai-compatible`. Endpoint harus mendukung content gambar data URI. Nama model di atas adalah placeholder, bukan model nyata. Dukungan model perlu diverifikasi administrator. Vision mati secara default; kredensial provider tidak diubah oleh implementasi ini.

### Chat 9router dan gambar B.ai bersamaan

Tambahkan ke `.env` backend Laravel (bukan `.env` gateway); ganti baris existing, jangan duplikat:

```dotenv
AI_PROVIDER=openai-compatible
OPENAI_COMPATIBLE_URL=http://localhost:20128/v1
OPENAI_COMPATIBLE_MODEL=cx/gpt-6-astra
OPENAI_COMPATIBLE_API_KEY=isi-key-9router

AI_VISION_ENABLED=true
AI_VISION_URL=https://api.b.ai/v1
AI_VISION_API_KEY=isi-key-b-ai
AI_VISION_MODEL=deepseek-v4-flash-vision-exp
AI_VISION_ESTIMATED_TOKENS=8000
```

Nama model mengikuti konfigurasi pengguna; ketersediaan/kemampuan provider hidup belum diuji. URL dan key vision harus diisi berpasangan: konfigurasi setengah terisi ditolak dan tidak meminjam key 9router. Chat teks tetap memakai 9router, gambar beserta caption memakai B.ai. Jika Laravel berada di Docker sedangkan 9router berada di host Windows, gunakan `http://host.docker.internal:20128/v1` untuk URL 9router. Setelah perubahan, jalankan `php artisan config:clear` dan `php artisan queue:restart` di lingkungan backend yang menjalankan bot.

Server PHP memerlukan GD untuk decoding gambar. Batas upload PHP/reverse proxy harus mencukupi multipart 5 MiB (termasuk overhead); gateway dan backend membatasi gambar menjadi 5 MiB dan 20 megapiksel. JPEG, PNG, WebP didukung. View-once tidak diteruskan untuk vision.

Restart queue worker dan gateway setelah deploy; reload konfigurasi Laravel sesuai workflow deployment. Scheduler Laravel harus berjalan agar cleanup file yatim dilakukan setiap jam.

## Persetujuan dan privasi

1. Kirim gambar; bot meminta persetujuan sebelum mengirim gambar ke provider AI.
2. Balas `SETUJU FOTO`, lalu kirim ulang gambar tanpa data sensitif.
3. Persetujuan berumur 30 hari di cache. `BATAL FOTO` mencabutnya; cache yang dibersihkan juga menghapus persetujuan.

Gambar tidak disamarkan otomatis. Caption/gambar dikirim ke provider eksternal setelah persetujuan. Jawaban gambar tidak dimasukkan ke riwayat chat. File privat dihapus setelah pemrosesan atau kegagalan; file kedaluwarsa setelah satu jam tidak diproses, dan cleanup menghapus file yatim lebih tua dua jam. Job gambar memakai satu percobaan untuk menghindari kirim ulang gambar otomatis setelah kegagalan ambigu; pengguna diminta mengirim ulang.

## Verifikasi deployment manual

Gunakan gambar non-sensitif. Uji caption kosong, pertanyaan gambar, consent/revoke, provider vision dimatikan, serta pertanyaan katalog. Uji ini belum dilakukan terhadap WhatsApp/provider hidup oleh implementasi lokal. Jangan memakai pembayaran sungguhan untuk pengujian otomatis.

## Batas implementasi saat ini

Pencarian katalog memakai kata kunci/brand terbatas; filter nominal dan konteks follow-up lintas pesan belum lengkap. Pengujian transport Baileys multipart end-to-end dan seluruh failure/retry/consent paths masih perlu diperluas. Foto bukan bukti pembayaran terverifikasi.
