import LegalDocument from '../legal-document';

export default function PrivacyPage() {
  return <LegalDocument title="Kebijakan Privasi" sections={[
    { title: 'Data yang kami kelola', body: 'Kami mengelola identitas akun, email, nomor telepon, rincian pesanan, pembayaran, komunikasi layanan, serta file yang Anda unggah agar pesanan dapat diproses dan dipantau.' },
    { title: 'Pihak pemroses', body: 'Google Firebase memproses autentikasi Google. Gmail SMTP memproses email akun. Pertanyaan serta riwayat relevan pada Younz AI dapat dikirim ke penyedia AI pihak ketiga setelah pola email dan nomor telepon disamarkan.' },
    { title: 'Keamanan dan penyimpanan', body: 'File pesanan disimpan privat, akses pegawai dibatasi berdasarkan peran, dan aktivitas sensitif dicatat. File layanan dijadwalkan untuk dihapus setelah masa retensi yang dikonfigurasi, secara default 90 hari.' },
    { title: 'Cookie, statistik, dan cache perangkat', body: 'Cookie esensial digunakan untuk sesi dan perlindungan formulir. Jika Anda mengizinkan statistik, kami hanya menghitung halaman dan tahapan funnel dengan pengenal acak yang di-hash. Statistik tidak menyimpan nama, nomor WhatsApp, isi chat, atau file. Cache perangkat hanya menyimpan aset statis; halaman akun, transaksi, pembayaran, dan respons API tidak disimpan.' },
    { title: 'Pilihan Anda', body: 'Persetujuan promosi bersifat opsional. Anda dapat meminta koreksi atau penghapusan data yang tidak lagi wajib disimpan melalui WhatsApp Younz Digital Center.' },
    { title: 'Hindari data rahasia pada AI', body: 'Jangan memasukkan password, OTP, nomor kartu, dokumen identitas, data kesehatan, atau rahasia bisnis pada fitur Younz AI.' },
  ]} />;
}
