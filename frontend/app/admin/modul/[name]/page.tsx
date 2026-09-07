import AdminHeader from '../../admin-header';

const modules: Record<string, { title: string; description: string }> = {
  kasir: {
    title: 'Kasir / POS',
    description: 'Antarmuka kasir sedang diselesaikan. Pesanan dan laporan tetap dapat dikelola dari menu yang tersedia.',
  },
  penjualan: {
    title: 'Riwayat penjualan',
    description: 'Riwayat lengkap penjualan sedang disiapkan. Ringkasan transaksi tersedia pada dashboard dan laporan harian.',
  },
  whatsapp: {
    title: 'WhatsApp Gateway',
    description: 'Status gateway dikelola oleh layanan operasional dan tidak dibuka pada halaman publik.',
  },
  pegawai: {
    title: 'Pegawai',
    description: 'Manajemen pegawai sedang disiapkan untuk owner.',
  },
};

export default async function PendingAdminModule({ params }: { params: Promise<{ name: string }> }) {
  const { name } = await params;
  const module = modules[name] ?? {
    title: 'Modul admin',
    description: 'Modul ini belum tersedia pada dashboard.',
  };

  return (
    <main className="admin-page">
      <AdminHeader name="Pegawai" role="Staff" />
      <section className="admin-shell">
        <a className="back-link" href="/admin/dashboard">← Kembali ke dashboard</a>
        <section className="migration-card">
          <p className="eyebrow">Status modul</p>
          <h1>{module.title}</h1>
          <p>{module.description}</p>
          <div>
            <a className="button button-dark" href="/admin/dashboard">Buka dashboard</a>
            <a className="button button-light" href="/admin/pesanan">Lihat pesanan</a>
          </div>
        </section>
      </section>
    </main>
  );
}
