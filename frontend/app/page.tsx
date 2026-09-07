import type { Metadata } from 'next';
import AiChat from './ai-chat';
import { Marquee } from '@/components/ui/marquee';
import LandingShowcase from './landing-showcase';
import './landing.css';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Younz Digital Center | Print, Desain, Website & Layanan Digital',
  description: 'Pesan layanan print, desain, website, aplikasi, ATK, dan top up dalam satu alur yang jelas.',
};

type Service = {
  id: number;
  name: string;
  type: string;
  unit: string;
  base_price: number;
  description: string | null;
};

type Product = {
  id: number;
  name: string;
  unit: string;
  selling_price: number;
  stock: number;
  category?: { id: number; name: string } | null;
};

type Testimonial = {
  id: number;
  customer_name: string;
  customer_role: string | null;
  quote: string;
  rating: number;
  source_label: string;
};

type TransactionProof = {
  reference: string;
  product: string;
  productNominal: string;
  value: string;
  testimonialName: string;
};

type SiteData = {
  services: Service[];
  featured_products: Product[];
  testimonials: Testimonial[];
  store: {
    address: string;
    open_hours: string;
    maps_url: string;
    whatsapp: string;
  };
};

const currency = new Intl.NumberFormat('id-ID');

const transactionProofs: TransactionProof[] = [
  { reference: 'TOP-20260807-0002', product: 'Pulsa Telkomsel', productNominal: '75.000', value: 'Rp 77.000', testimonialName: 'Pelanggan Younz (Anonim)' },
  { reference: 'TOP-20260807-0001', product: 'Pulsa Telkomsel', productNominal: '35.000', value: 'Rp 36.000', testimonialName: 'Pelanggan Younz (Anonim)' },
  { reference: 'TOP-20260806-0001', product: 'Pulsa Telkomsel', productNominal: '35.000', value: 'Rp 36.000', testimonialName: 'Pelanggan Younz (Anonim)' },
  { reference: 'TOP-20260805-0001', product: 'PDAM Sampit', productNominal: 'Tagihan PDAM', value: 'Rp 59.000', testimonialName: 'Pelanggan PPOB (Anonim)' },
  { reference: 'TOP-20260804-0004', product: 'Pulsa Telkomsel', productNominal: '100.000', value: 'Rp 102.000', testimonialName: 'Pelanggan Younz (Anonim)' },
  { reference: 'TOP-20260804-0003', product: 'Pulsa Telkomsel', productNominal: '35.000', value: 'Rp 36.000', testimonialName: 'Pelanggan Younz (Anonim)' },
  { reference: 'TOP-20260803-0001', product: 'Pulsa Telkomsel', productNominal: '100.000', value: 'Rp 102.000', testimonialName: 'Pelanggan Younz (Anonim)' },
];

const transactionProofRows = [transactionProofs.slice(0, 4), transactionProofs.slice(4)];


async function getSiteData(): Promise<{ data: SiteData | null; error: boolean }> {
  const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';

  try {
    const response = await fetch(`${backend}/api/v1/site`, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) return { data: null, error: true };
    return { data: ((await response.json()) as { data: SiteData }).data, error: false };
  } catch {
    return { data: null, error: true };
  }
}

function serviceLabel(type: string): string {
  const labels: Record<string, string> = {
    print: 'Print & dokumen',
    fotokopi: 'Fotokopi',
    scan: 'Scan dokumen',
    ketik: 'Pengetikan',
    desain: 'Desain kreatif',
    website: 'Website',
    aplikasi: 'Aplikasi',
  };

  return labels[type.toLowerCase()] || type.replaceAll('_', ' ');
}

function TransactionProofCard({ item }: { item: TransactionProof }) {
  return (
    <article className="blue-proof-card">
      <header className="blue-testimonial-topline">
        <span className="blue-proof-mark" aria-hidden="true">✓</span>
        <span className="blue-verified-badge"><i /> Terverifikasi</span>
      </header>
      <dl className="blue-proof-summary">
        <div><dt>Produk</dt><dd>{item.product}</dd></div>
        <div><dt>Nominal produk</dt><dd>{item.productNominal}</dd></div>
        <div className="is-value"><dt>Nilai transaksi</dt><dd>{item.value}</dd></div>
      </dl>
      <footer className="blue-proof-testimonial">
        <span aria-hidden="true">YD</span>
        <div><small>Nama testimoni</small><strong>{item.testimonialName}</strong></div>
      </footer>
    </article>
  );
}

export default async function Home() {
  const result = await getSiteData();
  const data = result.data;
  const services = data?.services ?? [];
  const products = data?.featured_products ?? [];
  const displayedServices = services.slice(0, 6);
  const displayedProducts = products.slice(0, 4);

  return (
    <main className="blue-page" id="halaman-utama">
      <a className="blue-skip-link" href="#konten-utama">Lewati ke konten utama</a>
      <LandingShowcase openHours={data?.store.open_hours || 'Senin - Sabtu, 09.00 - 17.00'} />

      <div id="konten-utama" tabIndex={-1}>
        {result.error && (
          <div className="blue-api-notice" role="status">
            Katalog sedang disinkronkan. Formulir pesanan tetap dapat digunakan.
          </div>
        )}

        <section className="blue-quick-section" aria-labelledby="quick-title">
          <div className="blue-shell">
            <header className="blue-section-intro blue-section-intro-row">
              <div><span className="blue-eyebrow">Mulai dari sini</span><h2 id="quick-title">Mau melakukan apa?</h2></div>
              <p>Print, desain, website, aplikasi, sampai kebutuhan digital harian — semua dikerjakan dalam satu alur yang jelas dan mudah dipantau.</p>
            </header>
            <div className="blue-quick-grid">
              <a href="/pesan" data-analytics-event="order_cta_clicked" data-analytics-source="landing_quick_order">
                <span className="blue-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><div><small>Pesanan baru</small><h3>Kirim kebutuhan</h3><p>Tulis brief dan unggah file dalam satu formulir.</p></div><b aria-hidden="true">↗</b>
              </a>
              <a href="/topup" data-analytics-event="topup_cta_clicked" data-analytics-source="landing_quick_topup">
                <span className="blue-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z" /></svg></span><div><small>Produk digital</small><h3>Isi ulang cepat</h3><p>Pulsa, paket data, token, dan pembayaran digital.</p></div><b aria-hidden="true">↗</b>
              </a>
              <a href="/cek-pesanan">
                <span className="blue-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" /><path d="m9 12 2 2 4-5" /></svg></span><div><small>Sudah memesan</small><h3>Cek progres</h3><p>Lihat status pekerjaan dengan nomor pesanan.</p></div><b aria-hidden="true">↗</b>
              </a>
            </div>
          </div>
        </section>

        <section id="layanan" className="blue-services-section">
          <div className="blue-shell">
            <header className="blue-section-intro blue-section-intro-row">
              <div><span className="blue-eyebrow">Layanan utama</span><h2>Satu partner untuk pekerjaan<br />fisik dan digital.</h2></div>
              <p>Pilih layanan awal. Harga final dikonfirmasi setelah detail pekerjaan diperiksa.</p>
            </header>

            {displayedServices.length > 0 ? (
              <div className="blue-service-grid">
                {displayedServices.map((service, index) => (
                  <article key={service.id}>
                    <header><span>{String(index + 1).padStart(2, '0')}</span><small>{serviceLabel(service.type)}</small></header>
                    <h3>{service.name}</h3>
                    <p>{service.description || 'Layanan profesional yang dapat disesuaikan dengan kebutuhan Anda.'}</p>
                    <footer>
                      <div><small>Mulai dari</small><strong>Rp {currency.format(service.base_price)} <i>/{service.unit}</i></strong></div>
                      <a href={`/pesan?service_id=${service.id}&source=next-catalog`} aria-label={`Pilih layanan ${service.name}`} data-analytics-event="order_cta_clicked" data-analytics-source={`catalog_${service.id}`}>↗</a>
                    </footer>
                  </article>
                ))}
              </div>
            ) : (
              <div className="blue-empty-state"><span><img src="/brand/younz-wordmark-v1.svg" alt="" aria-hidden="true" /></span><div><small>Katalog sedang disiapkan</small><h3>Ceritakan hasil yang Anda butuhkan.</h3><p>Operator akan membantu menentukan layanan yang tepat.</p></div><a className="blue-button blue-button-primary" href="/pesan">Kirim kebutuhan</a></div>
            )}

            <div className="blue-catalog-link"><span>{services.length > 0 ? `${services.length} layanan aktif` : 'Layanan fleksibel'}</span><a href="/pesan">Lihat semua layanan <b>→</b></a></div>
          </div>
        </section>


        <section id="cara-kerja" className="blue-process-section">
          <div className="blue-shell blue-process-layout">
            <header className="blue-section-intro">
              <span className="blue-eyebrow">Alur yang jelas</span>
              <h2>Tiga langkah.<br /><em>Tanpa menebak.</em></h2>
              <p>Anda selalu tahu apa yang sedang terjadi, berapa biayanya, dan kapan pekerjaan selesai.</p>
              <a href="/pesan">Mulai sekarang <span>↗</span></a>
            </header>
            <ol className="blue-process-list">
              <li><span>01</span><div><small>Brief</small><h3>Kirim kebutuhan dan file.</h3><p>Berikan hasil akhir yang diinginkan. Form kami membantu merapikan detail sejak awal.</p></div></li>
              <li><span>02</span><div><small>Konfirmasi</small><h3>Terima harga dan estimasi.</h3><p>Operator memeriksa spesifikasi sebelum pekerjaan berjalan. Tidak ada biaya yang samar.</p></div></li>
              <li><span>03</span><div><small>Selesai</small><h3>Pantau sampai siap.</h3><p>Status dapat dicek kapan saja. Anda akan mendapat kabar saat hasil sudah siap.</p></div></li>
            </ol>
          </div>
        </section>

        {displayedProducts.length > 0 && (
          <section className="blue-products-section">
            <div className="blue-shell">
              <header className="blue-section-intro blue-section-intro-row"><div><span className="blue-eyebrow">ATK & perlengkapan</span><h2>Kebutuhan meja kerja.</h2></div><p>Stok terbaru dari toko.</p></header>
              <div className="blue-product-grid">
                {displayedProducts.map((product) => (
                  <article key={product.id}><small>{product.category?.name || 'Perlengkapan'}</small><h3>{product.name}</h3><footer><strong>Rp {currency.format(product.selling_price)}</strong><span>/{product.unit} · stok {product.stock}</span></footer></article>
                ))}
              </div>
            </div>
          </section>
        )}

        <section id="younz-ai" className="blue-ai-section">
          <div className="blue-shell">
            <header className="blue-ai-heading">
              <div><span className="blue-eyebrow blue-eyebrow-light">Younz AI</span><h2>Tanya dulu.<br /><em>Putuskan dengan yakin.</em></h2></div>
              <p>Asisten yang memahami layanan, harga awal, jam operasional, dan informasi Younz yang sudah diverifikasi.</p>
            </header>
            <div className="blue-ai-layout">
              <aside><span className="blue-ai-mark"><img src="/brand/younz-wordmark-inverse.svg" alt="" aria-hidden="true" /></span><h3>Pertanyaan kecil pun boleh.</h3><p>Mulai dari harga print hingga pilihan layanan yang paling cocok untuk kebutuhan Anda.</p><ul><li>Berapa harga print A4?</li><li>Apakah bisa membuat website?</li><li>Younz buka jam berapa?</li></ul><small>Jangan masukkan data pribadi atau rahasia.</small></aside>
              <AiChat />
            </div>
          </div>
        </section>

        <section className="blue-testimonials-section">
          <div className="blue-shell">
            <header className="blue-section-intro blue-section-intro-row"><div><span className="blue-eyebrow">Bukti transaksi</span><h2>Dipercaya untuk<br />kebutuhan sehari-hari.</h2></div><p>Ringkasan transaksi terverifikasi dengan identitas testimoni yang aman untuk ditampilkan.</p></header>
            <div className="blue-proof-marquee-shell">
              {transactionProofRows.map((row, rowIndex) => (
                <Marquee
                  key={`proof-row-${rowIndex}`}
                  className="blue-proof-grid"
                  pauseOnHover
                  reverse={rowIndex === 1}
                  repeat={2}
                  aria-label={`Bukti transaksi berhasil Younz Digital Center baris ${rowIndex + 1}`}
                >
                  {row.map((item) => <TransactionProofCard key={item.reference} item={item} />)}
                </Marquee>
              ))}
            </div>
          </div>
        </section>

        <section className="blue-final-section">
          <div className="blue-shell blue-final-card">
            <span className="blue-eyebrow blue-eyebrow-light">Siap dimulai?</span>
            <h2>Satu brief singkat.<br />Sisanya kami bantu bereskan.</h2>
            <p>Detail dan harga selalu dikonfirmasi sebelum pekerjaan berjalan.</p>
            <div><a className="blue-button blue-button-white" href="/pesan" data-analytics-event="order_cta_clicked" data-analytics-source="landing_final">Buat pesanan <span>↗</span></a><a className="blue-button blue-button-outline" href="/topup" data-analytics-event="topup_cta_clicked" data-analytics-source="landing_final">Beli top up</a></div>
          </div>
        </section>
      </div>

      <footer className="blue-footer">
        <div className="blue-shell blue-footer-grid">
          <div className="blue-footer-brand"><img src="/brand/younz-wordmark-v1.svg" alt="Younz Digital Center" width="200" height="47" /><p>Partner untuk kebutuhan cetak, kreatif, teknologi, dan layanan digital harian.</p></div>
          <div><small>Mulai</small><a href="/pesan">Buat pesanan</a><a href="/cek-pesanan">Cek progres</a><a href="/topup">Top Up</a></div>
          <div><small>Informasi</small><a href="/akun">Akun pelanggan</a><a href="/kebijakan-privasi">Privasi</a><a href="/syarat-layanan">Syarat layanan</a></div>
          <div><small>Kunjungi</small><span>{data?.store.open_hours || '-'}</span><a href={data?.store.maps_url || '#'} target="_blank" rel="noreferrer">{data?.store.address || 'Younz Digital Center'} ↗</a></div>
        </div>
        <div className="blue-shell blue-footer-bottom"><span>© {new Date().getFullYear()} Younz Digital Center</span><span>Made for everyday progress.</span></div>
      </footer>
    </main>
  );
}
