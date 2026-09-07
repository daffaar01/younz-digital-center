import type { Metadata } from 'next';
import SiteHeader from '../site-header';
import ProductCatalog from './product-catalog';
import type { DigitalProduct } from './catalog';
import './produk.css';

type CatalogResult = { status: 'ok' | 'unavailable'; products: DigitalProduct[] };

async function getDigitalProducts(): Promise<CatalogResult> {
  const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
  try {
    const response = await fetch(`${backend}/api/v1/digital-products`, { cache: 'no-store' });
    if (!response.ok) return { status: 'unavailable', products: [] };
    const payload = await response.json() as { data?: DigitalProduct[] };
    return Array.isArray(payload.data)
      ? { status: 'ok', products: payload.data }
      : { status: 'unavailable', products: [] };
  } catch {
    return { status: 'unavailable', products: [] };
  }
}

export const metadata: Metadata = {
  title: 'Produk Digital | Younz Digital Center',
  description: 'Temukan Discord Nitro, YouTube Premium, ChatGPT, Claude AI, Grok, Leonardo AI, dan produk digital lainnya di Younz Digital Center.',
};

export default async function ProductsPage() {
  const catalog = await getDigitalProducts();
  return (
    <main className="product-page">
      <SiteHeader />
      <section id="katalog-produk" className="product-page-catalog" aria-labelledby="product-page-title">
        <div className="product-page-shell">
          <header className="product-page-intro"><div><span className="product-page-eyebrow">Katalog digital</span><h2 id="product-page-title">Pilih produk yang kamu butuhkan.</h2></div><p>Harga yang belum tercantum akan dikonfirmasi operator bersama jenis paket, durasi, dan ketersediaannya sebelum pesanan diproses.</p></header>
          <ProductCatalog products={catalog.products} unavailable={catalog.status === 'unavailable'} />
          <div className="product-page-other"><span>+</span><div><strong>Produk lain belum terlihat?</strong><p>Sampaikan nama aplikasi, platform, atau layanan digital yang dicari. Kami akan mengecek opsi yang tersedia.</p></div><a href="https://wa.me/628219207240?text=Halo%20Younz%2C%20saya%20ingin%20menanyakan%20produk%20digital%20lain%20yang%20belum%20ada%20di%20katalog.">Tanyakan produk lewat WhatsApp →</a></div>
        </div>
      </section>

      <section className="product-page-trust">
        <div className="product-page-shell"><article><span>01</span><div><strong>Ketersediaan diperiksa</strong><p>Tidak ada klaim stok atau paket sebelum operator melakukan konfirmasi.</p></div></article><article><span>02</span><div><strong>Harga dikonfirmasi</strong><p>Biaya, durasi, dan detail produk disepakati sebelum diproses.</p></div></article><article><span>03</span><div><strong>Pesanan dapat dipantau</strong><p>Setelah dikirim, pesanan memperoleh nomor pelacakan dari sistem Younz.</p></div></article></div>
      </section>
    </main>
  );
}
