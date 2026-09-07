'use client';

import { useEffect, useState } from 'react';
import AdminHeader from '../admin-header';
import ProjectReminders from './project-reminders';
import './project-reminders.css';

type Dashboard = {
  user: { name: string; role: { label: string } };
  permissions: { manage: boolean; operate_sales: boolean };
  summary: {
    today_revenue: number;
    today_expenses: number;
    today_transactions: number;
    active_orders: number;
    late_orders: number;
    failed_digital_transactions: number;
    pending_approvals: number;
  };
  low_stock_products: {
    id: number;
    name: string;
    sku: string;
    stock: number;
    minimum_stock: number;
  }[];
  recent_sales: {
    id: number;
    invoice_number: string;
    cashier: string | null;
    status: string;
    total: number;
    completed_at: string | null;
  }[];
};

const money = new Intl.NumberFormat('id-ID');

export default function AdminDashboard() {
  const [data, setData] = useState<Dashboard | null>(null);
  const [staffToken, setStaffToken] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const token = localStorage.getItem('ydc_staff_token');
    if (!token) {
      location.replace('/admin/masuk');
      return;
    }
    setStaffToken(token);

    fetch('/backend/v1/staff/dashboard', {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
      .then(async (response) => {
        if (response.status === 401) {
          localStorage.removeItem('ydc_staff_token');
          throw new Error('Sesi pegawai berakhir.');
        }
        if (response.status === 403) throw new Error('Akses dashboard tidak lagi diizinkan.');
        if (response.status === 429) throw new Error('Terlalu banyak permintaan. Coba lagi sebentar.');
        if (!response.ok) throw new Error('Dashboard belum dapat dimuat. Coba lagi.');
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
          throw new Error('Respons dashboard tidak valid. Coba lagi.');
        }
        try { return await response.json(); }
        catch { throw new Error('Respons dashboard tidak dapat dibaca. Coba lagi.'); }
      })
      .then((payload) => {
        if (!payload.data?.user || !payload.data?.summary || !payload.data?.permissions) {
          throw new Error('Format dashboard tidak valid. Coba lagi.');
        }
        setData(payload.data);
      })
      .catch((error) => setMessage(error.message));
  }, []);

  if (!data) {
    return (
      <main className="staff-loading">
        {message ? (
          <>
            <p>{message}</p>
            <a className="button button-dark" href="/admin/masuk">Masuk kembali</a>
          </>
        ) : 'Memuat dashboard…'}
      </main>
    );
  }

  const summary = data.summary;

  return (
    <main className="admin-page">
      <AdminHeader name={data.user.name} role={data.user.role.label} />
      <section className="admin-shell">
        <header className="admin-welcome">
          <div>
            <p className="eyebrow">Ringkasan hari ini</p>
            <h1>Selamat datang, {data.user.name.split(' ')[0]}.</h1>
            <p>{new Date().toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' })} WIB</p>
          </div>
          <a
            className="button button-dark"
            href={data.permissions.operate_sales ? '/admin/modul/kasir' : '/admin/pesanan'}
          >
            {data.permissions.operate_sales ? 'Buka Kasir' : 'Lihat Pesanan'}
          </a>
        </header>

        {summary.pending_approvals > 0 && (
          <a className="approval-notice" href="/admin/persetujuan?status=pending">
            <strong>{summary.pending_approvals} permintaan menunggu persetujuan</strong>
            <span>Tinjau sekarang →</span>
          </a>
        )}

        <section className="admin-kpis">
          <article><small>Pendapatan</small><strong>Rp {money.format(summary.today_revenue)}</strong><span>Setelah refund selesai</span></article>
          <article><small>Pengeluaran</small><strong>Rp {money.format(summary.today_expenses)}</strong><span>Tercatat hari ini</span></article>
          <article><small>Transaksi</small><strong>{money.format(summary.today_transactions)}</strong><span>Penjualan selesai</span></article>
          <article><small>Pesanan aktif</small><strong>{money.format(summary.active_orders)}</strong><span>Belum selesai</span></article>
        </section>

        <div className="admin-grid">
          <section className="admin-surface">
            <header>
              <h2>Transaksi terbaru</h2>
              <a href="/admin/modul/penjualan">Lihat semua →</a>
            </header>
            {data.recent_sales.length === 0 ? (
              <div className="admin-empty">Belum ada transaksi.</div>
            ) : (
              <div className="admin-table">
                <div className="admin-table-head" aria-hidden="true">
                  <span>Invoice</span><span>Kasir</span><span>Total</span><span>Status</span>
                </div>
                {data.recent_sales.map((sale) => (
                  <a href="/admin/modul/penjualan" key={sale.id}>
                    <strong>{sale.invoice_number}</strong>
                    <span>{sale.cashier || '-'}</span>
                    <b>Rp {money.format(sale.total)}</b>
                    <small>{sale.status === 'completed' ? 'Selesai' : sale.status.replaceAll('_', ' ')}</small>
                  </a>
                ))}
              </div>
            )}
          </section>

          <aside className="admin-monitor">
            <p className="eyebrow eyebrow-light">Monitoring</p>
            <h2>Panel perhatian</h2>
            <div>
              <article><strong>{summary.late_orders}</strong><span>Pesanan lewat deadline</span></article>
              <article><strong>{summary.failed_digital_transactions}</strong><span>Transaksi digital gagal</span></article>
            </div>
            <h3>Stok menipis</h3>
            {data.low_stock_products.length === 0 ? (
              <p>Semua stok dalam batas aman.</p>
            ) : data.low_stock_products.slice(0, 5).map((product) => (
              <a href="/admin/produk" key={product.id}>
                <span><strong>{product.name}</strong><small>{product.sku}</small></span>
                <b>{product.stock} / {product.minimum_stock}</b>
              </a>
            ))}
          </aside>
        </div>

        <section className="admin-actions">
          <a href="/admin/modul/kasir"><strong>Kasir / POS</strong><span>Mulai transaksi →</span></a>
          <a href="/admin/pesanan"><strong>Pesanan Jasa</strong><span>Pantau progres →</span></a>
          <a href="/admin/produk"><strong>Produk & Stok</strong><span>Kelola persediaan →</span></a>
          {data.permissions.manage && (
            <a href="/admin/konten-ai"><strong>Konten & Younz AI</strong><span>Kelola kualitas →</span></a>
          )}
          <a href="/admin/laporan"><strong>Laporan Harian</strong><span>Lihat performa →</span></a>
        </section>
        {data.permissions.manage && staffToken && <ProjectReminders token={staffToken} />}
      </section>
    </main>
  );
}
