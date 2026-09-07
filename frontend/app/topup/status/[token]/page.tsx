import SiteHeader from '../../../site-header';
import { getTopupStatus, type PageSearchParams } from '../topup-api';

const money = new Intl.NumberFormat('id-ID');

export default async function TopupStatusPage({
  params,
  searchParams,
}: {
  params: Promise<{ token: string }>;
  searchParams: Promise<PageSearchParams>;
}) {
  const { token } = await params;
  const query = await searchParams;
  const result = await getTopupStatus(`/topup/status/${encodeURIComponent(token)}`, query);
  const order = result.data;

  if (!order) {
    return (
      <main>
        <SiteHeader />
        <section className="form-page shell narrow-page">
          <p className="eyebrow">Status Top Up</p>
          <h1>{result.status === 403 ? 'Tautan tidak valid.' : 'Transaksi belum dapat dibuka.'}</h1>
          <p className="form-lead">Buat ulang tautan aman menggunakan nomor transaksi, email, dan WhatsApp checkout.</p>
          <a className="button button-dark" href="/topup/akses">Buat tautan baru</a>
        </section>
      </main>
    );
  }

  const success = order.fulfillment_status.code === 'success';
  const failed = order.fulfillment_status.code === 'failed';
  const paid = order.payment_status.code === 'paid';
  const pending = order.payment_status.code === 'pending';
  const isPostpaid = order.transaction_type.code === 'postpaid';
  const billDetails = order.bill_details
    ? Object.entries(order.bill_details).filter(([, value]) => value !== null && value !== '')
    : [];
  const detailLabel = (key: string) => key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  const isDetailRecord = (value: unknown): value is Record<string, unknown> => Boolean(value && typeof value === 'object' && !Array.isArray(value));
  const detailValue = (key: string, value: unknown) => {
    if (key === 'periode' && typeof value === 'string' && /^\d{6}$/.test(value)) {
      return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value.slice(0, 4)}-${value.slice(4, 6)}-01T00:00:00Z`));
    }

    if (typeof value === 'number' || (typeof value === 'string' && /^\d+$/.test(value) && /admin|denda|nilai|tagihan|harga|amount|price/i.test(key))) {
      return `Rp ${money.format(Number(value))}`;
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
  };
  const nestedBillDetails = billDetails.flatMap(([key, value]) => {
    if (!Array.isArray(value)) return [];

    return value.flatMap((item, index) => {
      if (!isDetailRecord(item)) return [];

      return [{ key: `${key}-${index}`, label: `${detailLabel(key)} ${index + 1}`, values: Object.entries(item) }];
    });
  });
  const flatBillDetails = billDetails.filter(([, value]) => !Array.isArray(value));

  return (
    <main>
      <SiteHeader />
      <section className="topup-status-page shell">
        <header className="topup-status-heading">
          <div><p className="eyebrow">Status Top Up</p><h1>{order.order_number}</h1></div>
          <a className="button button-light" href={order.refresh_url}>Perbarui status</a>
        </header>
        <div className="topup-status-layout">
          <section className="topup-status-card">
            <header className={success ? 'success' : failed ? 'failed' : ''}>
              <span>{success ? '✓' : failed ? '!' : '↻'}</span>
              <div>
                <small>{order.payment_status.label}</small>
                <h2>{order.fulfillment_status.label}</h2>
                <p>{success ? 'Produk digital telah dikirim ke tujuan Anda.' : failed ? 'Transaksi membutuhkan pemeriksaan operator.' : paid ? 'Pembayaran diterima dan sedang diteruskan ke provider.' : 'Selesaikan pembayaran sebelum batas waktunya.'}</p>
              </div>
            </header>
            <div className="topup-status-body">
              <dl>
                <div><dt>Produk</dt><dd>{order.product_name}</dd></div>
                <div><dt>Jenis transaksi</dt><dd>{order.transaction_type.label}</dd></div>
                <div><dt>Tujuan</dt><dd>{order.destination}</dd></div>
                {order.provider_customer_name && <div><dt>Nama pelanggan</dt><dd>{order.provider_customer_name}</dd></div>}
                {isPostpaid && order.selling_price > 0 && <div><dt>Nominal tagihan provider</dt><dd>Rp {money.format(order.selling_price)}</dd></div>}
                {isPostpaid && order.admin_fee > 0 && <div><dt>Biaya admin</dt><dd>Rp {money.format(order.admin_fee)}</dd></div>}
                <div><dt>Total pembayaran</dt><dd>{order.total_amount > 0 ? `Rp ${money.format(order.total_amount)}` : 'Menunggu cek tagihan'}</dd></div>
                <div><dt>Dibuat</dt><dd>{order.created_at ? new Date(order.created_at).toLocaleString('id-ID') : '-'}</dd></div>
              </dl>
              {billDetails.length > 0 && <div className="topup-bill-details"><small>Rincian tagihan</small>{flatBillDetails.length > 0 && <dl>{flatBillDetails.map(([key, value]) => <div key={key}><dt>{detailLabel(key)}</dt><dd>{detailValue(key, value)}</dd></div>)}</dl>}{nestedBillDetails.map((detail) => <section className="topup-bill-period" key={detail.key}><strong>{detail.label}</strong><dl>{detail.values.map(([key, value]) => <div key={key}><dt>{detailLabel(key)}</dt><dd>{detailValue(key, value)}</dd></div>)}</dl></section>)}</div>}
              {order.serial_number && <div className="topup-serial"><small>Nomor seri / token</small><strong>{order.serial_number}</strong></div>}
              {order.receipt_url && order.invoice_url && <div className="topup-document-actions">
                <a className="button button-light" href={order.receipt_url} target="_blank" rel="noreferrer">Cetak struk</a>
                <a className="button button-dark" href={order.invoice_url} target="_blank" rel="noreferrer">Cetak invoice</a>
              </div>}
              {pending && order.midtrans_redirect_url && <a className="button button-lime full-button" href={order.midtrans_redirect_url}>Buka pembayaran Midtrans →</a>}
              {pending && !order.midtrans_redirect_url && order.payment_url && <form action={order.payment_url} method="post"><button className="button button-lime full-button">Buat pembayaran Midtrans →</button></form>}
              {failed && <a className="button button-dark full-button" href="https://wa.me/628219207240" target="_blank" rel="noreferrer">Hubungi operator</a>}
            </div>
          </section>
          <aside className="topup-flow-card">
            <p className="eyebrow">Alur transaksi</p>
            <ol>
              <li className={paid || success ? 'done' : ''}><span>{paid || success ? '✓' : '1'}</span><div><strong>Pembayaran</strong><small>{order.payment_status.label}</small></div></li>
              <li className={['processing', 'provider_pending', 'success'].includes(order.fulfillment_status.code) ? 'done' : ''}><span>2</span><div><strong>Pemrosesan provider</strong><small>{order.fulfillment_status.label}</small></div></li>
              <li className={success ? 'done' : ''}><span>{success ? '✓' : '3'}</span><div><strong>Selesai</strong><small>{success ? 'Berhasil dikirim' : 'Menunggu'}</small></div></li>
            </ol>
            <p>Simpan tautan ini. Nomor tujuan ditampilkan dalam bentuk tersamarkan.</p>
            <a href="/topup">← Kembali ke katalog</a>
          </aside>
        </div>
      </section>
    </main>
  );
}
