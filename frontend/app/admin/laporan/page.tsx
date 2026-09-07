'use client';

import { FormEvent, useEffect, useState } from 'react';
import AdminHeader from '../admin-header';

type Row = {
  invoice_number?: string;
  expense_number?: string;
  order_number?: string;
  refund_number?: string;
  transaction_number?: string;
  cashier?: string | null;
  category?: string | null;
  description?: string;
  product_name?: string;
  destination?: string;
  source?: string;
  provider?: string;
  total?: number;
  amount?: number;
  selling_price?: number;
  total_amount?: number;
  profit?: number;
  approver?: string | null;
};

type Data = {
  date: string;
  summary: {
    gross_revenue: number;
    refunds_total: number;
    net_revenue: number;
    expenses_total: number;
    gross_profit: number;
    net_cash: number;
    completed_orders: number;
  };
  sales: Row[];
  expenses: Row[];
  digital_transactions: Row[];
  topups: Row[];
  refunds: Row[];
};

const money = new Intl.NumberFormat('id-ID');

export default function DailyReport() {
  const [data, setData] = useState<Data | null>(null);
  const [user, setUser] = useState<{ name: string; role: { label: string } } | null>(null);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [message, setMessage] = useState('');

  async function load(value = date) {
    const token = localStorage.getItem('ydc_staff_token');
    if (!token) {
      location.replace('/admin/masuk');
      return;
    }

    const response = await fetch(`/backend/v1/staff/reports/daily?date=${value}`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(String(Object.values(payload.errors || {}).flat()[0] || payload.message));
    }
    setData(payload.data);
  }

  useEffect(() => {
    const stored = localStorage.getItem('ydc_staff_user');
    if (stored) setUser(JSON.parse(stored));
    load().catch((error) => setMessage(error.message));
    // Initial report only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function submit(event: FormEvent) {
    event.preventDefault();
    setMessage('');
    load(date).catch((error) => setMessage(error.message));
  }

  const cards: [string, number][] = data ? [
    ['Penjualan kotor', data.summary.gross_revenue],
    ['Refund', data.summary.refunds_total],
    ['Pendapatan bersih', data.summary.net_revenue],
    ['Laba kotor', data.summary.gross_profit],
    ['Arus kas bersih', data.summary.net_cash],
  ] : [];

  return (
    <main className="admin-page">
      <AdminHeader name={user?.name || 'Pegawai'} role={user?.role.label || 'Staff'} />
      <section className="admin-shell">
        <header className="admin-welcome">
          <div>
            <p className="eyebrow">Laporan</p>
            <h1>Ringkasan harian</h1>
            <p>Penjualan, refund, Top Up, dan pengeluaran dalam satu laporan.</p>
          </div>
          <form className="report-date" onSubmit={submit}>
            <input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
            <button className="button button-dark">Tampilkan</button>
          </form>
        </header>

        {message && <div className="form-error">{message}</div>}
        <section className="report-kpis">
          {cards.map(([label, value]) => (
            <article key={label}><small>{label}</small><strong>Rp {money.format(value)}</strong></article>
          ))}
        </section>

        {data && (
          <>
            <div className="report-grid">
              <ReportTable
                title={`Penjualan (${data.sales.length})`}
                rows={data.sales.map((item) => [item.invoice_number || '-', item.cashier || '-', `Rp ${money.format(item.total || 0)}`])}
              />
              <ReportTable
                title={`Pengeluaran (${data.expenses.length})`}
                rows={data.expenses.map((item) => [item.category || '-', item.description || '-', `Rp ${money.format(item.amount || 0)}`])}
              />
            </div>
            <ReportTable
              title={`Top Up otomatis (${data.topups.length})`}
              rows={data.topups.map((item) => [
                item.order_number || '-',
                `${item.product_name || '-'} · ${item.destination || '-'}`,
                `${item.source || '-'} · Rp ${money.format(item.total_amount || 0)} · Laba Rp ${money.format(item.profit || 0)}`,
              ])}
            />
            <ReportTable
              title={`Refund (${data.refunds.length})`}
              rows={data.refunds.map((item) => [item.refund_number || '-', item.invoice_number || '-', `- Rp ${money.format(item.amount || 0)}`])}
            />
            <ReportTable
              title={`Transaksi digital manual (${data.digital_transactions.length})`}
              rows={data.digital_transactions.map((item) => [item.transaction_number || '-', item.provider || '-', `Rp ${money.format(item.selling_price || 0)}`])}
            />
          </>
        )}
      </section>
    </main>
  );
}

function ReportTable({ title, rows }: { title: string; rows: string[][] }) {
  return (
    <section className="report-table">
      <h2>{title}</h2>
      {rows.length === 0 ? <p>Tidak ada data.</p> : rows.map((row, index) => (
        <div key={index}>{row.map((cell, cellIndex) => <span key={cellIndex}>{cell}</span>)}</div>
      ))}
    </section>
  );
}
