'use client';

import { useState } from 'react';

export type ProductVariant = {
  id?: number;
  label: string;
  price: number | null;
  stock: number | null;
  is_active: boolean;
  sort_order: number;
};

export default function DigitalProductVariants({ initial = [] }: { initial?: ProductVariant[] }) {
  const [variants, setVariants] = useState<ProductVariant[]>(initial);

  function update(index: number, patch: Partial<ProductVariant>) {
    setVariants((current) => current.map((variant, itemIndex) => itemIndex === index ? { ...variant, ...patch } : variant));
  }

  return <fieldset className="digital-variant-editor">
    <legend>Pilihan durasi</legend><input type="hidden" name="variants_present" value="1" />
    <div className="digital-variant-heading"><small>Contoh label: 1 Hari, 7 Hari, 1 Bulan, atau 1 Tahun. Harga dan stok harus sesuai data nyata.</small><button type="button" onClick={() => setVariants((current) => [...current, { label: '', price: null, stock: null, is_active: true, sort_order: current.length + 1 }])}>+ Tambah durasi</button></div>
    {variants.length === 0 ? <p>Belum ada pilihan durasi. Produk akan memakai harga dan stok utama.</p> : <div className="digital-variant-list">{variants.map((variant, index) => <section key={variant.id ?? `new-${index}`}>
      {variant.id && <input type="hidden" name={`variants[${index}][id]`} value={variant.id} />}
      <label>Nama durasi<input name={`variants[${index}][label]`} required maxLength={80} value={variant.label} placeholder="Contoh: 1 Bulan" onChange={(event) => update(index, { label: event.target.value })} /></label>
      <div className="digital-product-row"><label>Harga (Rp)<input name={`variants[${index}][price]`} type="number" min="0" step="1" value={variant.price ?? ''} placeholder="Kosong = konfirmasi" onChange={(event) => update(index, { price: event.target.value === '' ? null : Number(event.target.value) })} /></label><label>Stok<input name={`variants[${index}][stock]`} type="number" min="0" value={variant.stock ?? ''} placeholder="Kosong = konfirmasi" onChange={(event) => update(index, { stock: event.target.value === '' ? null : Number(event.target.value) })} /></label></div>
      <div className="digital-variant-footer"><label>Urutan<input name={`variants[${index}][sort_order]`} type="number" min="0" value={variant.sort_order} onChange={(event) => update(index, { sort_order: Number(event.target.value) })} /></label><label className="digital-product-check"><input type="hidden" name={`variants[${index}][is_active]`} value="0"/><input name={`variants[${index}][is_active]`} type="checkbox" value="1" checked={variant.is_active} onChange={(event) => update(index, { is_active: event.target.checked })}/> Aktif</label><button type="button" className="danger" aria-label={`${variant.id ? 'Nonaktifkan' : 'Hapus'} durasi ${variant.label || index + 1}`} onClick={() => setVariants((current) => variant.id ? current.map((item, itemIndex) => itemIndex === index ? { ...item, is_active: false } : item) : current.filter((_, itemIndex) => itemIndex !== index))}>{variant.id ? 'Nonaktifkan' : 'Hapus'}</button></div>
    </section>)}</div>}
  </fieldset>;
}
