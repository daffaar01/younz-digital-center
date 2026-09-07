import LegalDocument from '../legal-document';

export default function TermsPage() {
  return <LegalDocument title="Syarat Layanan" sections={[
    { title: 'Estimasi dan pesanan', body: 'Harga pada katalog dan jawaban AI adalah estimasi awal. Harga, ruang lingkup, dan tenggat final berlaku setelah dikonfirmasi operator.' },
    { title: 'File dan hak penggunaan', body: 'Anda bertanggung jawab memastikan file yang dikirim tidak melanggar hukum, hak cipta, privasi, atau hak pihak lain. File berbahaya dan format yang tidak didukung dapat ditolak.' },
    { title: 'Pembayaran, pembatalan, dan refund', body: 'Pembayaran serta refund mengikuti nilai transaksi yang tercatat. Refund tertentu memerlukan pemeriksaan dan persetujuan pegawai berwenang.' },
    { title: 'Penggunaan akun', body: 'Jaga kerahasiaan password dan OTP. Segera hubungi operator bila Anda menduga akun digunakan tanpa izin.' },
    { title: 'Bantuan', body: 'Pertanyaan mengenai ketentuan dapat disampaikan melalui WhatsApp atau langsung ke Younz Digital Center pada jam operasional.' },
  ]} />;
}
