import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import SiteHeader from '../../site-header';
import ProductImage from '../product-image';
import type { DigitalProduct } from '../catalog';
import ProductDetailActions from './product-detail-actions';
import './product-detail.css';

const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';

const getProduct = cache(async (id: string): Promise<DigitalProduct | null> => {
  if (!/^\d+$/.test(id)) return null;
  const response = await fetch(`${backend}/api/v1/digital-products/${id}`, { cache: 'no-store', headers: { Accept: 'application/json' } });
  if (response.status === 404) return null;
  if (!response.ok) throw new Error(`Backend produk gagal dengan status ${response.status}.`);
  const payload = await response.json() as { data?: DigitalProduct };
  if (!payload.data || typeof payload.data.id !== 'number') throw new Error('Respons detail produk tidak valid.');
  return payload.data;
});

async function getRelated(product: DigitalProduct): Promise<DigitalProduct[]> {
  try {
    const response = await fetch(`${backend}/api/v1/digital-products`, { cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) return [];
    const payload = await response.json() as { data?: DigitalProduct[] };
    if (!Array.isArray(payload.data)) return [];
    return payload.data.filter((item) => item.id !== product.id).sort((a, b) => Number(b.category === product.category) - Number(a.category === product.category)).slice(0, 4);
  } catch { return []; }
}

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const product = await getProduct((await params).id);
  if (!product) return { title: 'Produk tidak ditemukan | Younz Digital Center' };
  return {
    title: `${product.name} | Younz Digital Center`,
    description: product.description.slice(0, 155),
    openGraph: { title: product.name, description: product.description.slice(0, 155), images: [product.image_url] },
  };
}

export default async function ProductDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const product = await getProduct((await params).id);
  if (!product) notFound();
  const related = await getRelated(product);
  const siteUrl = (process.env.NEXT_PUBLIC_SITE_URL || 'https://younzdigitalcenter.my.id').replace(/\/$/, '');
  const imageUrl = product.image_url.startsWith('http') ? product.image_url : `${siteUrl}${product.image_url}`;
  const variantOffers = product.variants.filter((variant) => variant.price !== null).map((variant) => ({
    '@type': 'Offer',
    priceCurrency: 'IDR',
    price: variant.price,
    name: variant.label,
    url: `${siteUrl}/produk/${product.id}`,
    ...(variant.stock === null ? {} : { availability: `https://schema.org/${variant.stock > 0 ? 'InStock' : 'OutOfStock'}` }),
  }));
  const offer = product.variants.length > 0 ? (variantOffers.length > 0 ? variantOffers : undefined) : product.price === null ? undefined : {
    '@type': 'Offer',
    priceCurrency: 'IDR',
    price: product.price,
    url: `${siteUrl}/produk/${product.id}`,
    ...(product.stock === null ? {} : { availability: `https://schema.org/${product.stock > 0 ? 'InStock' : 'OutOfStock'}` }),
  };
  const productJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description,
    image: [imageUrl],
    url: `${siteUrl}/produk/${product.id}`,
    brand: { '@type': 'Brand', name: 'Younz Digital Center' },
    ...(offer ? { offers: offer } : {}),
  };

  return (
    <main className="product-detail-page">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(productJsonLd).replace(/</g, '\\u003c') }} />
      <SiteHeader />
      <div className="product-detail-shell">
        <nav className="product-detail-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/produk">Produk</a></li><li><span aria-hidden="true">/</span><span>{product.category}</span></li><li><span aria-hidden="true">/</span><strong aria-current="page">{product.name}</strong></li></ol></nav>

        <section className="product-detail-hero" aria-labelledby="product-detail-title">
          <div className="product-detail-gallery"><div className="product-detail-image"><ProductImage src={product.image_url} name={product.name} priority /></div><p>Foto produk dikelola langsung oleh operator Younz Digital Center.</p></div>
          <div className="product-detail-summary">
            <span className="product-detail-category">{product.category}</span>
            <h1 id="product-detail-title">{product.name}</h1>
            <div className="product-detail-availability"><span aria-hidden="true" /><div><strong>{product.variants.length > 0 ? 'Stok sesuai durasi' : product.stock_label}</strong><p>{product.variants.length > 0 ? 'Pilih durasi untuk melihat ketersediaan faktual.' : 'Operator memastikan paket, masa aktif, dan ketersediaan sebelum memproses pesanan.'}</p></div></div>
            <ProductDetailActions productId={product.id} productName={product.name} stock={product.stock} priceLabel={product.price_label} variants={product.variants} />
          </div>
        </section>

        <section className="product-detail-assurance" aria-label="Jaminan layanan"><article><span>01</span><div><strong>Konfirmasi sebelum proses</strong><p>Harga, paket, durasi, dan ketersediaan disepakati lebih dulu.</p></div></article><article><span>02</span><div><strong>Ditangani operator</strong><p>Pesanan diperiksa oleh tim Younz sebelum layanan diberikan.</p></div></article><article><span>03</span><div><strong>Status dapat dipantau</strong><p>Pesanan yang terkirim mendapatkan nomor pelacakan dari sistem.</p></div></article></section>

        <div className="product-detail-content">
          <section aria-labelledby="description-title"><header><span>Informasi produk</span><h2 id="description-title">Deskripsi produk</h2></header><div className="product-detail-description">{product.description.split(/\r?\n/).map((line, index) => line ? <p key={index}>{line}</p> : <br key={index} />)}</div></section>
          <aside aria-labelledby="specification-title"><header><span>Ringkasan</span><h2 id="specification-title">Spesifikasi</h2></header><dl><div><dt>Kategori</dt><dd>{product.category}</dd></div><div><dt>Harga</dt><dd>{product.variants.length > 0 ? 'Sesuai durasi' : product.price_label}</dd></div><div><dt>Stok</dt><dd>{product.variants.length > 0 ? 'Sesuai durasi' : product.stock_label}</dd></div><div><dt>Pemrosesan</dt><dd>Konfirmasi operator</dd></div><div><dt>Pengiriman</dt><dd>Digital / sesuai produk</dd></div></dl></aside>
        </div>

        {related.length > 0 && <section className="product-detail-related" aria-labelledby="related-title"><header><span>Produk lainnya</span><h2 id="related-title">Mungkin kamu juga mencari</h2></header><div>{related.map((item) => <a key={item.id} href={`/produk/${item.id}`}><span>{item.category}</span><strong>{item.name}</strong><small>{item.price_label}</small><b aria-hidden="true">→</b></a>)}</div></section>}
      </div>
    </main>
  );
}
