'use client';

import { useState } from 'react';
import type { DigitalProduct } from './catalog';
import ProductImage from './product-image';

const categories = ['Semua', 'AI Assistant', 'AI Kreatif', 'Streaming', 'Komunitas'] as const;

export default function ProductCatalog({ products, unavailable = false }: { products: DigitalProduct[]; unavailable?: boolean }) {
  const [category, setCategory] = useState<(typeof categories)[number]>('Semua');
  const visible = category === 'Semua' ? products : products.filter((product) => product.category === category);

  return (
    <>
      <div className="product-page-filters" aria-label="Filter kategori produk">
        {categories.map((item) => <button key={item} type="button" className={category === item ? 'active' : ''} aria-pressed={category === item} onClick={() => setCategory(item)}>{item}</button>)}
      </div>
      <div className="product-page-grid" aria-live="polite">
        {visible.length === 0 && <p className="product-page-empty" role="status">{unavailable ? 'Katalog sedang tidak tersedia. Silakan coba lagi atau tanyakan produk kepada operator.' : category === 'Semua' ? 'Belum ada produk aktif pada katalog.' : 'Tidak ada produk pada kategori ini.'}</p>}
        {visible.map((product) => (
          <article key={product.id}>
            <a className="product-page-card-link" href={`/produk/${product.id}`} aria-label={`Lihat detail ${product.name}`}>
            <figure className="product-page-image">
              <ProductImage src={product.image_url} name={product.name} />
              <figcaption>{product.category}</figcaption>
            </figure>
            <div className="product-page-details">
              <h2>{product.name}</h2>
              <strong className="product-page-price">{product.price_label}</strong>
              <span className="product-page-stock"><i aria-hidden="true" /> Stok: {product.stock_label}</span>
              <p>{product.description}</p>
            </div>
            <footer><span>Lihat detail produk <b>→</b></span></footer>
            </a>
          </article>
        ))}
      </div>
    </>
  );
}
