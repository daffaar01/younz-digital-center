'use client';

import { FormEvent, useEffect, useState } from 'react';
import AdminHeader from '../admin-header';

type Ref = { id: number; name: string };
type Manual = {
  id: number;
  transaction_number: string;
  type: string;
  provider: string | null;
  destination: string;
  selling_price: number;
  admin_fee: number;
  profit: number;
  status: { label: string };
};
type Topup = {
  order_number: string;
  product_name: string;
  category: string;
  brand: string;
  destination: string;
  total_amount: number;
  cost_price: number;
  profit: number;
  source: string;
  source_code: 'offline' | 'whatsapp' | 'agent' | 'legacy_sampitmart' | 'web';
  transaction_type: 'prepaid' | 'postpaid';
  provider_customer_name: string | null;
  has_payment_url: boolean;
  recovery_url: string;
  receipt_url: string | null;
  invoice_url: string | null;
  payment_status: { code: string; label: string };
  fulfillment_status: { code: string; label: string };
  provider_rc: string | null;
  created_at: string | null;
};
type TopupProduct = {
  id: number;
  transaction_type: 'prepaid' | 'postpaid';
  product_name: string;
  category: string;
  brand: string;
  selling_price: number;
};
type Data = {
  transactions: Manual[];
  topups: Topup[];
  topup_products: TopupProduct[];
  customers: Ref[];
  types: { code: string; label: string }[];
  pagination: { manual: { total: number }; topup: { total: number } };
};

const money = new Intl.NumberFormat('id-ID');

function firstErrorText(value: unknown): string {
  if (typeof value === 'string') return value.trim();
  if (Array.isArray(value)) {
    for (const item of value) {
      const text = firstErrorText(item);
      if (text) return text;
    }
  }
  if (value && typeof value === 'object') {
    for (const item of Object.values(value as Record<string, unknown>)) {
      const text = firstErrorText(item);
      if (text) return text;
    }
  }
  return '';
}

function apiErrorMessage(payload: unknown, status: number): string {
  if (payload && typeof payload === 'object') {
    const response = payload as Record<string, unknown>;
    const text = firstErrorText(response.errors)
      || firstErrorText(response.message)
      || firstErrorText(response.error)
      || firstErrorText(response.detail);
    if (text) return text;
  }

  if (status === 401) return 'Sesi admin berakhir. Silakan masuk kembali.';
  if (status === 403) return 'Akun Anda tidak memiliki izin untuk memverifikasi pembayaran.';
  if (status === 404) return 'Transaksi Top Up tidak ditemukan.';
  if (status === 502) return 'Midtrans belum memberikan status transaksi yang valid. Silakan coba lagi.';
  if (status === 503) return 'Layanan pembayaran sedang tidak tersedia.';
  return `Permintaan gagal diproses (HTTP ${status}).`;
}

export default function DigitalTransactions() {
  const [data, setData] = useState<Data | null>(null);
  const [user, setUser] = useState<{ name: string; role: { code: string; label: string } } | null>(null);
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState<'success' | 'error'>('success');
  const [verifying, setVerifying] = useState('');
  const [confirmation, setConfirmation] = useState<Topup | null>(null);
  const [offlineSubmitting, setOfflineSubmitting] = useState(false);
  const [offlineIdempotencyKey, setOfflineIdempotencyKey] = useState('');
  const [offlineMode, setOfflineMode] = useState<'prepaid' | 'postpaid'>('prepaid');

  async function api<T extends Record<string, unknown>>(path: string, init: RequestInit = {}): Promise<T> {
    const token = localStorage.getItem('ydc_staff_token');
    if (!token) {
      location.replace('/admin/masuk');
      throw new Error('Sesi berakhir.');
    }
    const response = await fetch('/backend/v1/staff' + path, {
      ...init,
      headers: { Accept: 'application/json', Authorization: 'Bearer ' + token, ...init.headers },
    });
    const payload: unknown = await response.json().catch(() => null);
    if (!response.ok) {
      if (response.status === 401) {
        localStorage.removeItem('ydc_staff_token');
        localStorage.removeItem('ydc_staff_user');
      }
      throw new Error(apiErrorMessage(payload, response.status));
    }

    if (!payload || typeof payload !== 'object') {
      throw new Error('Respons Laravel tidak valid. Silakan muat ulang halaman.');
    }

    return payload as T;
  }

  async function load() {
    setData((await api<{ data: Data }>('/digital-transactions')).data);
  }

  useEffect(() => {
    const saved = localStorage.getItem('ydc_staff_user');
    if (saved) setUser(JSON.parse(saved));
    load().catch((error) => {
      setMessageType('error');
      setMessage(error.message);
    });
  }, []);

  async function createOfflineTopup(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (offlineSubmitting) return;
    const form = event.currentTarget;
    const key = offlineIdempotencyKey || crypto.randomUUID();
    setOfflineIdempotencyKey(key);
    setOfflineSubmitting(true);
    try {
      const body = Object.fromEntries(new FormData(form));
      body.idempotency_key = key;
      const payload = await api<{ message: string }>('/digital-transactions/offline-topups', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      setMessageType('success');
      setMessage(payload.message || 'Top Up toko berhasil dibuat.');
      form.reset();
      setOfflineIdempotencyKey('');
      await load();
    } catch (error) {
      setMessageType('error');
      setMessage(error instanceof Error ? error.message : 'Top Up toko gagal dibuat.');
    } finally {
      setOfflineSubmitting(false);
    }
  }

  async function verifyTopup(manualOverride = false) {
    if (!confirmation) return;
    setVerifying(confirmation.order_number);
    setMessage('');
    try {
      const payload = await api<{ message: string }>(`/digital-transactions/${confirmation.order_number}/verify-payment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirmed: true, manual_override: manualOverride }),
      });
      setMessageType('success');
      setMessage(payload.message || 'Verifikasi pembayaran selesai.');
      setConfirmation(null);
      await load();
    } catch (error) {
      setMessageType('error');
      setMessage(error instanceof Error ? error.message : 'Verifikasi pembayaran gagal.');
      setConfirmation(null);
    } finally {
      setVerifying('');
    }
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const body = Object.fromEntries(new FormData(form));
      body.idempotency_key = crypto.randomUUID();
      const payload = await api<{ message: string }>('/digital-transactions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      setMessageType('success');
      setMessage(payload.message || 'Transaksi berhasil diajukan.');
      form.reset();
      await load();
    } catch (error) {
      setMessageType('error');
      setMessage(error instanceof Error ? error.message : 'Gagal mengajukan transaksi.');
    }
  }

  const canVerify = user?.role.code === 'owner' || user?.role.code === 'admin';

  return (
    <main className="admin-page">
      <AdminHeader name={user?.name || 'Pegawai'} role={user?.role.label || 'Staff'} />
      <section className="admin-shell">
        <header className="admin-welcome">
          <div>
            <p className="eyebrow">PPOB & Top Up</p>
            <h1>Transaksi digital</h1>
            <p>Top Up pelanggan toko dapat diverifikasi tunai tanpa QRIS atau virtual account.</p>
          </div>
        </header>

        {message && <div className={messageType === 'success' ? 'form-success' : 'form-error'}>{message}</div>}

        <details className="digital-create" open>
          <summary>+ Buat Top Up pelanggan toko</summary>
          <form onSubmit={createOfflineTopup}>
            <label>
              Jenis transaksi
              <select
                value={offlineMode}
                onChange={(event) => setOfflineMode(event.target.value as 'prepaid' | 'postpaid')}
              >
                <option value="prepaid">Prabayar</option>
                <option value="postpaid">Pascabayar</option>
              </select>
            </label>
            <label className="digital-product-field">
              {offlineMode === 'postpaid' ? 'Jenis tagihan' : 'Produk'}
              <select key={offlineMode} name="product_id" required defaultValue="">
                <option value="" disabled>{offlineMode === 'postpaid' ? 'Pilih jenis tagihan' : 'Pilih produk'}</option>
                {data?.topup_products.filter((product) => product.transaction_type === offlineMode).map((product) => (
                  <option key={product.id} value={product.id}>
                    {product.product_name} · {product.transaction_type === 'postpaid' ? 'Cek tagihan' : `Rp ${money.format(product.selling_price)}`}
                  </option>
                ))}
              </select>
            </label>
            <label>{offlineMode === 'postpaid' ? 'ID pelanggan' : 'Nomor tujuan'}<input name="destination" required /></label>
            <label>Konfirmasi {offlineMode === 'postpaid' ? 'ID pelanggan' : 'nomor'}<input name="destination_confirmation" required /></label>
            <label>Nama pelanggan<input name="customer_name" required /></label>
            <label>WhatsApp pelanggan<input name="customer_phone" required /></label>
            <button className="button button-dark" disabled={offlineSubmitting}>{offlineSubmitting ? 'Menyimpan…' : 'Simpan transaksi offline'}</button>
          </form>
          <p className="digital-form-help">
            {offlineMode === 'postpaid'
              ? 'Sistem mengecek nama dan nominal tagihan terlebih dahulu. Terima uang sesuai total yang tampil, lalu verifikasi pembayaran.'
              : 'Belum ada QRIS/VA yang dibuat. Setelah uang diterima, verifikasi transaksi pada daftar di bawah.'}
          </p>
        </details>

        <section className="digital-section">
          <h2>Top Up otomatis <small>{data?.pagination.topup.total || 0} transaksi</small></h2>
          <div className="digital-table">
            {!data ? (
              <div className="admin-empty">Memuat data…</div>
            ) : data.topups.length === 0 ? (
              <div className="admin-empty">Belum ada Top Up otomatis.</div>
            ) : data.topups.map((topup) => {
              const needsPayment = (topup.source_code === 'web' || topup.source_code === 'whatsapp')
                && topup.payment_status.code === 'pending'
                && !topup.has_payment_url;

              return <article key={topup.order_number}>
                <span>
                  <strong>{topup.order_number}</strong>
                  <small>{topup.created_at ? new Date(topup.created_at).toLocaleString('id-ID') : '-'} · {topup.source}</small>
                </span>
                <span>
                  <strong>{topup.product_name}</strong>
                  <small>{topup.category} · {topup.brand} · {topup.destination}{topup.provider_customer_name ? ` · ${topup.provider_customer_name}` : ''}</small>
                </span>
                <span>
                  <strong>Rp {money.format(topup.total_amount)}</strong>
                  <small>Modal Rp {money.format(topup.cost_price)} · Laba Rp {money.format(topup.profit)}</small>
                </span>
                <em>
                  {topup.payment_status.label}<br />{topup.fulfillment_status.label}
                  {needsPayment && <small>Belum ada transaksi Midtrans</small>}
                </em>
                <div className="digital-row-actions">
                  {canVerify && needsPayment && (
                    <a
                      className="button button-light"
                      href={topup.recovery_url}
                      target="_blank"
                      rel="noreferrer"
                    >
                      Buat pembayaran
                    </a>
                  )}
                  {canVerify && (topup.payment_status.code === 'pending' || topup.payment_status.code === 'expired') && (
                    <button
                      type="button"
                      className="button button-dark"
                      onClick={() => setConfirmation(topup)}
                      disabled={verifying !== ''}
                    >
                      {verifying === topup.order_number ? 'Memproses…' : 'Verifikasi'}
                    </button>
                  )}
                  {canVerify && topup.payment_status.code === 'paid' && topup.fulfillment_status.code === 'provider_pending' && (
                    <button
                      type="button"
                      className="button button-dark"
                      onClick={() => setConfirmation(topup)}
                      disabled={verifying !== ''}
                    >
                      {verifying === topup.order_number ? 'Memproses…' : 'Cek provider'}
                    </button>
                  )}
                  {topup.receipt_url && (
                    <a className="button button-light" href={topup.receipt_url} target="_blank" rel="noreferrer">Struk</a>
                  )}
                  {topup.invoice_url && (
                    <a className="button button-dark" href={topup.invoice_url} target="_blank" rel="noreferrer">Invoice / PDF</a>
                  )}
                </div>
              </article>
            })}
          </div>
        </section>

        <details className="digital-create">
          <summary>+ Ajukan transaksi digital manual</summary>
          <form onSubmit={submit}>
            <label>Jenis<select name="type">{data?.types.map((type) => <option key={type.code} value={type.code}>{type.label}</option>)}</select></label>
            <label>Provider<input name="provider" /></label>
            <label>Nomor tujuan<input name="destination" required /></label>
            <label>Konfirmasi nomor<input name="destination_confirmation" required /></label>
            <label>Nominal<input name="nominal" type="number" min="0" defaultValue="0" required /></label>
            <label>Harga modal<input name="cost_price" type="number" min="0" required /></label>
            <label>Harga jual<input name="selling_price" type="number" min="0" required /></label>
            <label>Biaya admin<input name="admin_fee" type="number" min="0" defaultValue="0" /></label>
            <label>Status target<select name="status"><option value="diproses">Diproses</option><option value="berhasil">Berhasil</option><option value="gagal">Gagal</option></select></label>
            <label>Pelanggan<select name="customer_id"><option value="">Tanpa pelanggan</option>{data?.customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}</select></label>
            <label>Referensi provider<input name="provider_reference" /></label>
            <button className="button button-dark">Ajukan transaksi</button>
          </form>
        </details>

        <section className="digital-section">
          <h2>PPOB manual <small>{data?.pagination.manual.total || 0} transaksi</small></h2>
          <div className="digital-table">
            {data?.transactions.length === 0 ? (
              <div className="admin-empty">Belum ada transaksi manual.</div>
            ) : data?.transactions.map((transaction) => (
              <article key={transaction.id}>
                <span><strong>{transaction.transaction_number}</strong><small>{transaction.type.replaceAll('_', ' ')} · {transaction.provider || '-'}</small></span>
                <span><strong>{transaction.destination}</strong><small>Tujuan disamarkan</small></span>
                <span><strong>Rp {money.format(transaction.selling_price + transaction.admin_fee)}</strong><small>Laba Rp {money.format(transaction.profit)}</small></span>
                <em>{transaction.status.label}</em>
              </article>
            ))}
          </div>
        </section>
      </section>

      {confirmation && (
        <div className="payment-confirmation-backdrop" role="presentation" onClick={() => setConfirmation(null)}>
          <section className="payment-confirmation" role="dialog" aria-modal="true" aria-labelledby="payment-confirmation-title" onClick={(event) => event.stopPropagation()}>
            <p className="eyebrow">Konfirmasi pembayaran</p>
            <h2 id="payment-confirmation-title">Pembayaran sudah diterima?</h2>
            <p>
              {confirmation.payment_status.code === 'paid' && confirmation.fulfillment_status.code === 'provider_pending'
                ? 'Sistem akan mengecek ulang status transaksi dengan referensi yang sama. Pengecekan ini tidak membuat transaksi baru.'
                : confirmation.transaction_type === 'postpaid'
                  ? `Jika pembayaran tagihan ${confirmation.product_name} senilai Rp ${money.format(confirmation.total_amount)} sudah Anda terima, klik Ya. Jika pembayaran Midtrans belum ada, sistem akan memaksa verifikasi manual.`
                  : `Jika pembayaran ${confirmation.product_name} senilai Rp ${money.format(confirmation.total_amount)} sudah Anda terima, klik Ya. Jika pembayaran Midtrans belum ada, sistem akan memaksa verifikasi manual.`}
            </p>
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
              <button type="button" className="button button-light" onClick={() => setConfirmation(null)} disabled={verifying !== ''}>Batal</button>
              {confirmation.payment_status.code === 'paid' ? (
                <button type="button" className="button button-dark" onClick={() => verifyTopup(false)} disabled={verifying !== ''}>
                  {verifying ? 'Memproses…' : 'Cek provider'}
                </button>
              ) : (
                <>
                  <button type="button" className="button button-dark" onClick={() => verifyTopup(true)} disabled={verifying !== ''}>
                    {verifying ? 'Memproses…' : 'Verifikasi Manual Paksa'}
                  </button>
                  {confirmation.has_payment_url && (
                    <button type="button" className="button button-light" onClick={() => verifyTopup(false)} disabled={verifying !== ''}>
                      {verifying ? 'Memproses…' : 'Cek Midtrans'}
                    </button>
                  )}
                </>
              )}
            </div>
          </section>
        </div>
      )}
    </main>
  );
}
