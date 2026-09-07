@php
    $isPrivacy = $document === 'privacy';
    $title = ($isPrivacy ? 'Kebijakan Privasi' : 'Syarat Layanan').' · Younz Digital Center';
    $description = $isPrivacy ? 'Cara Younz Digital Center mengelola dan melindungi data pelanggan.' : 'Ketentuan penggunaan layanan Younz Digital Center.';
@endphp
@extends('layouts.public')
@section('content')
<main class="bg-[#eef8f3] px-4 py-14 sm:px-6 sm:py-20">
    <article class="mx-auto max-w-4xl rounded-[2rem] border border-emerald-900/10 bg-white p-6 shadow-[0_30px_80px_-45px_rgba(0,82,54,.35)] sm:p-10">
        <p class="public-label">Dokumen resmi</p>
        <h1 class="mt-3 font-display text-4xl font-extrabold text-[#132016]">{{ $isPrivacy ? 'Kebijakan Privasi' : 'Syarat Layanan' }}</h1>
        <p class="mt-3 text-sm text-slate-500">Terakhir diperbarui: 23 Juli 2026</p>

        @if($isPrivacy)
            <div class="legal-copy mt-9 space-y-7 text-sm leading-7 text-slate-600">
                <section><h2>Data yang kami kelola</h2><p>Kami mengelola identitas akun, email, nomor telepon, rincian pesanan, pembayaran, komunikasi layanan, serta file yang Anda unggah agar pesanan dapat diproses dan dipantau.</p></section>
                <section><h2>Pihak pemroses</h2><p>Google Firebase memproses autentikasi Google. Gmail SMTP memproses email akun. Pertanyaan serta riwayat relevan pada fitur Younz AI dapat dikirim ke penyedia AI pihak ketiga; pola email dan nomor telepon disamarkan sebelum pengiriman.</p></section>
                <section><h2>Keamanan dan penyimpanan</h2><p>File pesanan disimpan secara privat, akses pegawai dibatasi berdasarkan role, dan aktivitas sensitif dicatat. File layanan dijadwalkan untuk dihapus setelah masa retensi yang dikonfigurasi, secara default 90 hari.</p></section>
                <section><h2>Cookie dan cache perangkat</h2><p>Cookie esensial digunakan untuk sesi login, perlindungan formulir, serta penyimpanan pilihan cookie. Cookie opsional hanya digunakan setelah persetujuan. Cache perangkat hanya menyimpan aset statis seperti WebP, CSS, JavaScript, dan font; halaman akun, transaksi, pembayaran, serta respons API tidak disimpan oleh cache tersebut.</p></section>
                <section><h2>Pilihan Anda</h2><p>Persetujuan promosi bersifat opsional. Anda dapat meminta koreksi atau penghapusan data yang tidak lagi wajib disimpan dengan menghubungi WhatsApp {{ config('services.whatsapp.display_number') }}.</p></section>
                <section><h2>Hindari data rahasia pada AI</h2><p>Jangan memasukkan password, OTP, nomor kartu, dokumen identitas, data kesehatan, atau rahasia bisnis pada fitur Younz AI.</p></section>
            </div>
        @else
            <div class="legal-copy mt-9 space-y-7 text-sm leading-7 text-slate-600">
                <section><h2>Estimasi dan pesanan</h2><p>Harga pada katalog dan jawaban AI adalah estimasi awal. Harga, ruang lingkup, dan tenggat final berlaku setelah dikonfirmasi operator.</p></section>
                <section><h2>File dan hak penggunaan</h2><p>Anda bertanggung jawab memastikan file yang dikirim tidak melanggar hukum, hak cipta, privasi, atau hak pihak lain. File berbahaya dan format yang tidak didukung dapat ditolak.</p></section>
                <section><h2>Pembayaran, pembatalan, dan refund</h2><p>Pembayaran serta refund mengikuti nilai transaksi yang tercatat. Refund tertentu memerlukan pemeriksaan dan persetujuan pegawai berwenang.</p></section>
                <section><h2>Penggunaan akun</h2><p>Jaga kerahasiaan password dan OTP. Segera hubungi operator bila Anda menduga akun digunakan tanpa izin.</p></section>
                <section><h2>Bantuan</h2><p>Pertanyaan mengenai ketentuan dapat disampaikan melalui WhatsApp {{ config('services.whatsapp.display_number') }} atau datang ke {{ config('services.store.address') }} pada {{ config('services.store.open_hours') }}.</p></section>
            </div>
        @endif
    </article>
</main>
@endsection
