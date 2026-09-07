import SiteHeader from '../../site-header';

type Order = {
  order_number: string;
  type: string;
  service: string | null;
  status: { code: string; label: string };
  estimated_price: number | null;
  final_price: number | null;
  paid_amount: number;
  payment_status: string;
  deadline_at: string | null;
};

const money = new Intl.NumberFormat('id-ID');
const paymentLabels: Record<string, string> = {
  unpaid: 'Belum memerlukan pembayaran',
  pending: 'Menunggu pembayaran',
  paid: 'Dibayar',
  expired: 'Kedaluwarsa',
  cancelled: 'Dibatalkan',
  failed: 'Pembayaran gagal',
  refunded: 'Dikembalikan',
  partial_refunded: 'Dikembalikan sebagian',
};

async function getOrder(token: string): Promise<Order | null> {
  const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
  try {
    const response = await fetch(`${backend}/api/v1/public/orders/${encodeURIComponent(token)}`, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return null;
    return ((await response.json()) as { data: Order }).data;
  } catch {
    return null;
  }
}

export default async function TrackedOrderPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const order = await getOrder(token);
  const payable = order ? (order.final_price ?? order.estimated_price) : null;

  return <main><SiteHeader /><section className="form-page shell narrow-page">
    <p className="eyebrow">Pelacakan</p>
    <h1>{order ? 'Status pesanan' : 'Pesanan tidak ditemukan'}</h1>
    <p className="form-lead">{order ? 'Simpan alamat halaman ini untuk memantau status pesanan dan pembayaran.' : 'Token pelacakan tidak valid atau sudah tidak tersedia.'}</p>
    {order && <section className="order-result">
      <header><div><small>{order.order_number}</small><h2>{order.service || order.type}</h2></div><span>{order.status.label}</span></header>
      <div className="order-facts">
        <div><small>Estimasi / final</small><strong>{payable === null ? 'Diperiksa operator' : `Rp ${money.format(payable)}`}</strong></div>
        <div><small>Status pembayaran</small><strong>{paymentLabels[order.payment_status] || 'Belum diketahui'}</strong></div>
        <div><small>Terbayar</small><strong>Rp {money.format(order.paid_amount || 0)}</strong></div>
        <div><small>Deadline</small><strong>{order.deadline_at ? new Date(order.deadline_at).toLocaleString('id-ID') : 'Belum ditentukan'}</strong></div>
      </div>
    </section>}
    <a className="button button-light back-button" href="/">Kembali ke beranda</a>
  </section></main>;
}
