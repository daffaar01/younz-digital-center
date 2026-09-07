'use client';

import { useEffect, useMemo, useState } from 'react';
import type { DigitalProductVariant } from '../catalog';

type Props = {
  productId: number;
  productName: string;
  stock: number | null;
  priceLabel: string;
  variants: DigitalProductVariant[];
};

const HeartIcon = ({ filled = false }: { filled?: boolean }) => (
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.7-7.5 1.1-1.1a5.5 5.5 0 0 0 0-7.8Z" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" /></svg>
);
const ShareIcon = () => (
  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.7 6.6-3.8M8.7 13.3l6.6 3.8" fill="none" stroke="currentColor" strokeWidth="1.8"/></svg>
);

export default function ProductDetailActions({ productId, productName, stock, priceLabel, variants }: Props) {
  const [quantity, setQuantity] = useState(1);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [favorite, setFavorite] = useState(false);
  const [notice, setNotice] = useState('');
  const favoriteKey = `ydc-favorite-product-${productId}`;
  const selected = variants.find((variant) => variant.id === selectedId) ?? null;
  const effectiveStock = variants.length ? selected?.stock ?? null : stock;
  const outOfStock = effectiveStock === 0;
  const maximum = outOfStock ? 1 : effectiveStock && effectiveStock > 0 ? effectiveStock : 99;

  useEffect(() => setFavorite(localStorage.getItem(favoriteKey) === '1'), [favoriteKey]);
  useEffect(() => setQuantity((value) => Math.min(value, maximum)), [maximum]);

  const orderHref = useMemo(() => {
    const params = new URLSearchParams({ source: 'digital-product-detail', product_id: String(productId), quantity: String(quantity) });
    if (selected) params.set('variant_id', String(selected.id));
    return `/pesan?${params.toString()}`;
  }, [productId, productName, quantity, selected]);

  function toggleFavorite() {
    const next = !favorite;
    setFavorite(next);
    if (next) localStorage.setItem(favoriteKey, '1'); else localStorage.removeItem(favoriteKey);
    setNotice(next ? 'Disimpan ke favorit di perangkat ini.' : 'Dihapus dari favorit.');
  }
  async function shareProduct() {
    const data = { title: productName, text: `Lihat ${productName} di Younz Digital Center`, url: window.location.href };
    try {
      if (navigator.share) await navigator.share(data);
      else { await navigator.clipboard.writeText(window.location.href); setNotice('Tautan produk disalin.'); }
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') return;
      setNotice('Tautan belum dapat dibagikan.');
    }
  }

  return <div className="product-detail-actions">
    <div className="product-detail-price"><small>{variants.length > 0 ? 'Harga sesuai durasi' : 'Harga produk'}</small><strong>{selected?.price_label ?? (variants.length > 0 ? 'Pilih durasi' : priceLabel)}</strong>{variants.length > 0 && <p>{selected ? `${selected.label} · ${selected.stock_label}` : 'Pilih durasi untuk melihat harga dan stok faktual.'}</p>}</div>
    <div className="product-detail-social-actions">
      <button type="button" onClick={shareProduct}><ShareIcon /> Bagikan</button>
      <button type="button" className={favorite ? 'active' : ''} aria-pressed={favorite} onClick={toggleFavorite}><HeartIcon filled={favorite} /> {favorite ? 'Tersimpan' : 'Favorit'}</button>
    </div>
    {variants.length > 0 && <fieldset className="product-detail-variants">
      <legend>Pilih durasi</legend>
      <div>{variants.map((variant) => <button key={variant.id} type="button" className={selectedId === variant.id ? 'active' : ''} aria-pressed={selectedId === variant.id} onClick={() => setSelectedId(variant.id)}><strong>{variant.label}</strong><span>{variant.price_label}</span>{variant.stock === 0 && <small>Habis · dapat ditanyakan</small>}</button>)}</div>
      <p aria-live="polite">{selected ? `${selected.price_label} · ${selected.stock_label}` : 'Pilih durasi untuk melihat harga dan stok.'}</p>
    </fieldset>}
    <div className="product-detail-quantity">
      <span id="quantity-label">Kuantitas</span>
      <div role="group" aria-labelledby="quantity-label"><button type="button" aria-label="Kurangi kuantitas" disabled={quantity <= 1} onClick={() => setQuantity((value) => Math.max(1, value - 1))}>−</button><output aria-live="polite">{quantity}</output><button type="button" aria-label="Tambah kuantitas" disabled={quantity >= maximum} onClick={() => setQuantity((value) => Math.min(maximum, value + 1))}>+</button></div>
      <small>{effectiveStock === null ? 'Ketersediaan dikonfirmasi operator' : `${effectiveStock} tersedia`}</small>
    </div>
    {variants.length > 0 && !selected ? <button className="product-detail-buy" type="button" disabled>Pilih durasi dahulu <span aria-hidden="true">→</span></button> : outOfStock ? <a className="product-detail-buy" href={`https://wa.me/628219207240?text=${encodeURIComponent(`Halo Younz, saya ingin menanyakan ketersediaan ${productName}${selected ? ` durasi ${selected.label}` : ''}.`)}`}>Tanyakan lewat WhatsApp <span aria-hidden="true">→</span></a> : <a className="product-detail-buy" href={orderHref}>Pesan sekarang <span aria-hidden="true">→</span></a>}
    <p className="product-detail-notice" role="status" aria-live="polite">{notice}</p>
  </div>;
}
