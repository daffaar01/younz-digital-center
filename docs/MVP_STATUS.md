# Status Implementasi MVP

## Sudah tersedia

- Login/logout pegawai, role server-side, akun aktif/nonaktif, dan pengelolaan pegawai oleh owner.
- Dashboard operasional berdasarkan transaksi valid.
- Produk, kategori awal, supplier, stok minimum, dan immutable stock movement.
- Livewire POS, sesi kasir, harga snapshot, pembayaran, checkout atomik, dan nomor invoice.
- Riwayat penjualan, refund parsial/penuh, pemulihan stok opsional, dan nomor refund.
- Approval engine untuk refund, perubahan harga, stok manual, hak akses, PPOB, dan pengeluaran dengan pemisahan tugas, kedaluwarsa, keputusan atomik, dan event log immutable.
- Pelanggan dan pencarian dasar.
- Pesanan print/fotokopi/scan/ketik/desain/web/aplikasi, file private, tautan unduh sementara, retensi file, dan histori status tervalidasi.
- Pengeluaran dan laporan harian sederhana.
- Transaksi digital/PPOB manual dengan konfirmasi nomor tujuan, laba, status, dan idempotency key.
- Website publik, upload file, dan cek status pesanan.
- FAQ berbasis knowledge base relevan, provider AI kompatibel OpenAI, fallback lokal, history limit, daily budget, penghapusan riwayat, dan feedback.
- Laravel AI SDK, Livewire, Sanctum, REST API v1, audit log, seed data, dan test kritis.
- Owner TOTP 2FA, token API berkemampuan terbatas dan kedaluwarsa, security headers, CORS allowlist, dan akun nonaktif langsung diblokir.
- Konfigurasi production diarahkan ke PostgreSQL/Redis; backup SQLite/PostgreSQL dienkripsi, diverifikasi, dirotasi, dan dijadwalkan.
- Portal pelanggan terautentikasi dengan verifikasi email, login Google/Firebase, login nomor HP via SMS OTP, reset password, profil, riwayat pesanan, approval estimasi, konfirmasi pembayaran, invoice, revisi, dan unduh hasil privat.
- Kebijakan privasi, syarat layanan, sitemap XML, metadata sosial/canonical, consent AI, dan redaksi data pribadi sebelum prompt dikirim ke provider pihak ketiga.
- Analisis statis Larastan/PHPStan, audit dependency, build frontend, dan CI PostgreSQL/Redis.

## Prioritas iterasi berikutnya

- Purchase order, penerimaan barang, hutang supplier, dan retur supplier.
- Pembayaran campuran dari UI, struk thermal/PDF, held sale, dan otorisasi diskon.
- Notifikasi status pesanan melalui email/WhatsApp dan preferensi notifikasi pelanggan.
- Proyek website/aplikasi, milestone, termin, revisi, dan credential vault.
- Embeddings/pgvector dan reranking untuk knowledge base berskala besar.
- Integrasi PPOB/payment gateway memakai adapter, callback signature, retry, dan rekonsiliasi.
- Export PDF/Excel/CSV, stock opname, import/export, Horizon/Reverb, serta observability production.

Fitur dalam daftar kedua belum boleh dianggap siap production sampai implementasi, security review, dan UAT selesai.
