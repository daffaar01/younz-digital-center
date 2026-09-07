'use client';

import { FormEvent, useState } from 'react';
import SiteHeader from '../../site-header';

export default function TopupAccessPage() {
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setMessage('');

    try {
      const body = Object.fromEntries(new FormData(event.currentTarget));
      const response = await fetch('/backend/v1/topup/access', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const payload = await response.json();
      if (!response.ok) {
        setMessage(String(Object.values(payload.errors || {}).flat()[0] || payload.message));
        return;
      }
      window.location.assign(payload.redirect_url);
    } catch {
      setMessage('Koneksi ke Laravel terputus. Silakan coba lagi.');
    } finally {
      setPending(false);
    }
  }

  return (
    <main>
      <SiteHeader />
      <section className="form-page shell narrow-page">
        <p className="eyebrow">Akses transaksi aman</p>
        <h1>Buka kembali transaksi Anda.</h1>
        <p className="form-lead">
          Masukkan data yang sama seperti saat checkout. Laravel akan memverifikasi data dan membuat tautan status baru.
        </p>
        <form className="auth-card topup-access-card" onSubmit={submit}>
          {message && <div className="form-error">{message}</div>}
          <label>Nomor transaksi<input name="order_number" required placeholder="TOP-20260722-0001" autoComplete="off" /></label>
          <label>Email checkout<input name="customer_email" type="email" required autoComplete="email" /></label>
          <label>Nomor WhatsApp checkout<input name="customer_phone" required inputMode="tel" autoComplete="tel" /></label>
          <button className="button button-dark full-button" disabled={pending}>
            {pending ? 'Memverifikasi…' : 'Buat tautan akses baru →'}
          </button>
          <a href="/topup">Kembali ke katalog Top Up</a>
        </form>
      </section>
    </main>
  );
}
