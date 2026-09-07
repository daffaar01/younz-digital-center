'use client';

import './product-detail.css';

export default function ProductDetailError({ reset }: { error: Error; reset: () => void }) {
  return (
    <main className="product-detail-error" role="alert">
      <div>
        <p>Produk Digital</p>
        <h1>Detail produk belum dapat dimuat</h1>
        <span>Layanan sedang mengalami gangguan sementara. Produk tidak dihapus—silakan coba lagi.</span>
        <button type="button" onClick={reset}>Coba lagi</button>
        <a href="/produk">Kembali ke katalog</a>
      </div>
    </main>
  );
}