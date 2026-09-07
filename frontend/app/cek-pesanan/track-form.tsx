'use client';

import { FormEvent, useState } from 'react';
import { trackAnalyticsEvent } from '../analytics';

type Order = {
  order_number: string;
  service: string | null;
  type: string;
  status: { label: string };
  estimated_price: number | null;
  final_price: number | null;
  paid_amount: number;
  deadline_at: string | null;
};

const money = new Intl.NumberFormat('id-ID');

function OrderCard({ order }: { order: Order }) {
  return (
    <article className="order-result">
      <header>
        <div><small>Nomor pesanan</small><h2>{order.order_number}</h2></div>
        <span>{order.status.label}</span>
      </header>
      <div className="order-facts">
        <div><small>Layanan</small><strong>{order.service || order.type}</strong></div>
        <div><small>Estimasi</small><strong>{order.estimated_price === null ? 'Belum tersedia' : `Rp ${money.format(order.estimated_price)}`}</strong></div>
        <div><small>Harga akhir</small><strong>{order.final_price === null ? '-' : `Rp ${money.format(order.final_price)}`}</strong></div>
        <div><small>Terbayar</small><strong>Rp {money.format(order.paid_amount)}</strong></div>
        <div><small>Deadline</small><strong>{order.deadline_at ? new Date(order.deadline_at).toLocaleString('id-ID') : 'Belum ditentukan'}</strong></div>
      </div>
    </article>
  );
}

export default function TrackForm() {
  const [order, setOrder] = useState<Order | null>(null);
  const [message, setMessage] = useState('');
  const [pending, setPending] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setMessage('');
    setOrder(null);
    void trackAnalyticsEvent('track_order_started', 'track_page');

    const data = Object.fromEntries(new FormData(event.currentTarget));
    try {
      const response = await fetch('/backend/v1/public/orders/track', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const payload = await response.json();
      if (!response.ok) {
        setMessage(payload.message || 'Pesanan tidak ditemukan.');
        return;
      }
      setOrder(payload.data);
    } catch {
      setMessage('Koneksi ke Laravel terputus. Silakan coba lagi.');
    } finally {
      setPending(false);
    }
  }

  return (
    <>
      <form className="track-form" onSubmit={submit}>
        <label>Nomor pesanan<input name="order_number" placeholder="ORD-20260720-0001" required /></label>
        <label>Nomor WhatsApp<input name="phone" required inputMode="tel" /></label>
        <button className="button button-dark" disabled={pending}>{pending ? 'Memeriksa…' : 'Periksa'}</button>
      </form>
      {message && <div className="form-error result-box">{message}</div>}
      {order && <OrderCard order={order} />}
    </>
  );
}
