import type { TopupStatus } from '../topup-api';
import PrintButton from './print-button';

const money = new Intl.NumberFormat('id-ID');
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'Asia/Jakarta' });
const dateTime = new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'medium',
  timeStyle: 'medium',
  timeZone: 'Asia/Jakarta',
});

function formattedDate(value: string | null | undefined, includeTime = true): string {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return `${(includeTime ? dateTime : date).format(parsed)}${includeTime ? ' WIB' : ''}`;
}

function merchant(order: TopupStatus) {
  return order.merchant || {
    name: 'Younz Digital Center',
    address: 'Jl. Kapten Mulyono No. 60C, Sampit',
    phone: '08219207240',
    hours: '',
  };
}

function paymentMethod(order: TopupStatus): string {
  return order.payment_method
    || (order.midtrans_payment_type || 'Midtrans').replaceAll('_', ' ').toUpperCase();
}

function paymentLabel(order: TopupStatus): string {
  return order.payment_status.code === 'refunded' ? 'Dikembalikan' : order.payment_status.label;
}

function DocumentToolbar({ returnUrl, kind }: { returnUrl: string; kind: 'receipt' | 'invoice' }) {
  return (
    <div className="print-toolbar">
      <a className="button button-light" href={returnUrl}>Kembali ke status</a>
      <PrintButton label={kind === 'receipt' ? 'Cetak struk' : 'Cetak / Simpan PDF'} />
    </div>
  );
}

function ReceiptRow({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return <div className={strong ? 'receipt-row receipt-row-strong' : 'receipt-row'}><span>{label}</span><span>{value}</span></div>;
}

function Receipt({ order }: { order: TopupStatus }) {
  const store = merchant(order);
  const refunded = order.payment_status.code === 'refunded';
  const serviceDone = order.fulfillment_status.code === 'success';

  return (
    <main className="topup-receipt-page">
      <article className="topup-receipt-paper">
        <header className="receipt-store">
          <strong>{store.name.toUpperCase()}</strong>
          <span>{store.address}</span>
          <span>WhatsApp {store.phone}</span>
        </header>

        <div className="receipt-divider" />
        <section className="receipt-heading">
          <strong>STRUK PEMBAYARAN</strong>
          <span className={refunded ? 'receipt-status refunded' : 'receipt-status'}>{paymentLabel(order).toUpperCase()}</span>
        </section>
        <div className="receipt-divider" />

        <section>
          <ReceiptRow label="No. transaksi" value={order.order_number} />
          <ReceiptRow label="Tanggal bayar" value={formattedDate(order.paid_at || order.created_at)} />
          <ReceiptRow label="Kanal" value={order.source?.label || 'Website'} />
          <ReceiptRow label="Pembayaran" value={paymentMethod(order)} />
          {order.payment_reference && <ReceiptRow label="Ref. pembayaran" value={order.payment_reference} />}
        </section>

        <div className="receipt-divider" />
        <section className="receipt-item">
          <strong>{order.product_name}</strong>
          <span>{[order.category, order.brand, order.sku].filter(Boolean).join(' · ')}</span>
          <ReceiptRow label="Tujuan" value={order.destination} />
          <ReceiptRow label="Pemesan" value={order.customer_name || 'Pelanggan'} />
          {order.provider_customer_name && <ReceiptRow label="Nama pelanggan" value={order.provider_customer_name} />}
          <ReceiptRow label="Status layanan" value={order.fulfillment_status.label} />
        </section>

        <div className="receipt-divider" />
        <section>
          <ReceiptRow label="Subtotal" value={`Rp ${money.format(order.selling_price)}`} />
          {order.admin_fee > 0 && <ReceiptRow label="Biaya admin" value={`Rp ${money.format(order.admin_fee)}`} />}
          <ReceiptRow label="TOTAL" value={`Rp ${money.format(order.total_amount)}`} strong />
        </section>

        {order.serial_number && (
          <section className="receipt-token">
            <span>NOMOR SERI / TOKEN</span>
            <strong>{order.serial_number}</strong>
          </section>
        )}

        {(order.provider_reference || order.provider_rc) && (
          <section className="receipt-provider">
            {order.provider_reference && <ReceiptRow label="Ref. provider" value={order.provider_reference} />}
            {order.provider_rc && <ReceiptRow label="Kode provider" value={order.provider_rc} />}
          </section>
        )}

        <div className="receipt-divider" />
        <footer className="receipt-footer">
          <strong>{serviceDone ? 'Transaksi berhasil' : order.fulfillment_status.label}</strong>
          <span>Terima kasih telah bertransaksi.</span>
          <span>Simpan struk ini sebagai bukti pembayaran.</span>
        </footer>
      </article>
      <DocumentToolbar returnUrl={order.refresh_url} kind="receipt" />
    </main>
  );
}

function Invoice({ order }: { order: TopupStatus }) {
  const store = merchant(order);
  const refunded = order.payment_status.code === 'refunded';
  const customerName = order.provider_customer_name || order.customer_name || 'Pelanggan Younz Digital Center';

  return (
    <main className="topup-invoice-page">
      <article className="topup-invoice-paper">
        <header className="invoice-header">
          <div className="invoice-brand">
            <span className="invoice-brand-mark"><img src="/brand/younz-wordmark-v1.svg" alt="Younz Digital Center" /></span>
            <div>
              <strong>{store.name.toUpperCase()}</strong>
              <small>{store.address}</small>
              <small>WhatsApp {store.phone}</small>
            </div>
          </div>
          <div className="invoice-identity">
            <h1>INVOICE</h1>
            <dl>
              <div><dt>No. invoice</dt><dd>{order.order_number}</dd></div>
              <div><dt>Tanggal terbit</dt><dd>{formattedDate(order.created_at, false)}</dd></div>
              <div><dt>Tanggal bayar</dt><dd>{formattedDate(order.paid_at, false)}</dd></div>
            </dl>
            <span className={refunded ? 'invoice-status refunded' : 'invoice-status'}>{paymentLabel(order).toUpperCase()}</span>
          </div>
        </header>

        <section className="invoice-parties">
          <div>
            <small>DITERBITKAN OLEH</small>
            <strong>{store.name}</strong>
            <span>{store.address}</span>
            <span>WhatsApp {store.phone}</span>
            {store.hours && <span>{store.hours}</span>}
          </div>
          <div>
            <small>DITAGIHKAN KEPADA</small>
            <strong>{customerName}</strong>
            {order.customer_phone && <span>Kontak: {order.customer_phone}</span>}
            {order.customer_email && <span>Email: {order.customer_email}</span>}
            <span>Tujuan layanan: {order.destination}</span>
          </div>
        </section>

        <section className="invoice-items">
          <table>
            <thead><tr><th>Deskripsi</th><th>SKU</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr></thead>
            <tbody>
              <tr>
                <td><strong>{order.product_name}</strong><small>{[order.transaction_type.label, order.category, order.brand].filter(Boolean).join(' · ')}</small></td>
                <td>{order.sku || '-'}</td>
                <td>1</td>
                <td>Rp {money.format(order.selling_price)}</td>
                <td>Rp {money.format(order.selling_price)}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section className="invoice-summary-grid">
          <div className="invoice-payment-details">
            <small>INFORMASI PEMBAYARAN</small>
            <dl>
              <div><dt>Metode</dt><dd>{paymentMethod(order)}</dd></div>
              <div><dt>Tanggal bayar</dt><dd>{formattedDate(order.paid_at)}</dd></div>
              <div><dt>Kanal transaksi</dt><dd>{order.source?.label || 'Website'}</dd></div>
              {order.payment_reference && <div><dt>Ref. pembayaran</dt><dd>{order.payment_reference}</dd></div>}
              {order.provider_reference && <div><dt>Ref. provider</dt><dd>{order.provider_reference}</dd></div>}
            </dl>
          </div>
          <dl className="invoice-totals">
            <div><dt>Subtotal</dt><dd>Rp {money.format(order.selling_price)}</dd></div>
            <div><dt>Biaya admin</dt><dd>Rp {money.format(order.admin_fee)}</dd></div>
            <div className="invoice-grand-total"><dt>Total</dt><dd>Rp {money.format(order.total_amount)}</dd></div>
            <div><dt>Telah dibayar</dt><dd>Rp {money.format(order.total_amount)}</dd></div>
            <div><dt>Sisa tagihan</dt><dd>Rp 0</dd></div>
          </dl>
        </section>

        <section className="invoice-service-status">
          <div><small>STATUS PEMBAYARAN</small><strong>{paymentLabel(order)}</strong></div>
          <div><small>STATUS LAYANAN</small><strong>{order.fulfillment_status.label}</strong></div>
          {order.fulfilled_at && <div><small>SELESAI DIPROSES</small><strong>{formattedDate(order.fulfilled_at)}</strong></div>}
        </section>

        {order.serial_number && (
          <section className="invoice-token">
            <small>NOMOR SERI / TOKEN</small>
            <strong>{order.serial_number}</strong>
          </section>
        )}

        <footer className="invoice-footer">
          <p>Invoice elektronik ini diterbitkan otomatis setelah pembayaran terverifikasi. Dokumen ini merupakan bukti transaksi yang sah dari {store.name}.</p>
          <p>Untuk bantuan, sertakan nomor invoice <strong>{order.order_number}</strong> saat menghubungi WhatsApp {store.phone}.</p>
        </footer>
      </article>
      <DocumentToolbar returnUrl={order.refresh_url} kind="invoice" />
    </main>
  );
}

export default function TopupDocument({ kind, order }: { kind: 'receipt' | 'invoice'; order: TopupStatus | null }) {
  if (!order) {
    return <main className="document-unavailable"><h1>Dokumen tidak tersedia</h1><p>Tautan tidak valid atau pembayaran belum terverifikasi.</p></main>;
  }

  return kind === 'receipt' ? <Receipt order={order} /> : <Invoice order={order} />;
}
