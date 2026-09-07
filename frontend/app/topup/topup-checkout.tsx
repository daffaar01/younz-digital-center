'use client';

import { FormEvent, useMemo, useState } from 'react';

type ProductFormType = 'mobile' | 'pln_token' | 'postpaid' | 'general';

type Product = {
  id: number;
  transaction_type: 'prepaid' | 'postpaid';
  product_name: string;
  category: string;
  brand: string;
  description: string | null;
  selling_price: number;
  form_type: ProductFormType;
};

type Catalog = {
  products: Product[];
  categories: string[];
  brands: string[];
  mode: { value: 'prepaid' | 'postpaid'; label: string };
  integrations_ready: boolean;
  postpaid_admin_fee: number;
};

type FormProfile = {
  destinationLabel: string;
  confirmationLabel: string;
  placeholder: string;
  help: string;
  inputMode: 'numeric' | 'tel' | 'text';
  pattern?: string;
  terms: string;
};

const money = new Intl.NumberFormat('id-ID');

function formProfile(product: Product | null, isPostpaid: boolean): FormProfile {
  if (product?.form_type === 'pln_token') {
    return {
      destinationLabel: 'Nomor meter / ID pelanggan PLN',
      confirmationLabel: 'Ulangi nomor meter / ID pelanggan',
      placeholder: 'Contoh: 12345678901',
      help: 'Gunakan nomor meter atau ID pelanggan PLN yang terdaftar.',
      inputMode: 'numeric',
      pattern: '[0-9]*',
      terms: 'Saya sudah memeriksa nomor meter atau ID pelanggan. Token yang berhasil dibeli tidak dapat dibatalkan.',
    };
  }

  if (product?.form_type === 'postpaid' || isPostpaid) {
    return {
      destinationLabel: 'Nomor pelanggan / ID tagihan',
      confirmationLabel: 'Ulangi nomor pelanggan / ID tagihan',
      placeholder: 'Contoh: 12345678901',
      help: 'Nama pelanggan dan nominal tagihan akan dicek sebelum Anda membayar.',
      inputMode: 'numeric',
      pattern: '[0-9]*',
      terms: 'Saya sudah memeriksa nomor pelanggan atau ID tagihan sebelum pengecekan tagihan dilakukan.',
    };
  }

  if (product?.form_type === 'mobile') {
    const isData = /data|internet/i.test(`${product.category} ${product.product_name}`);

    return {
      destinationLabel: isData ? 'Nomor HP penerima paket data' : 'Nomor HP penerima pulsa',
      confirmationLabel: 'Ulangi nomor HP',
      placeholder: 'Contoh: 08219207240',
      help: isData
        ? 'Pastikan nomor aktif dan sesuai operator paket data yang dipilih.'
        : 'Pastikan nomor aktif dan sesuai operator pulsa yang dipilih.',
      inputMode: 'tel',
      pattern: '[0-9+ -]*',
      terms: 'Saya sudah memeriksa nomor HP penerima. Pengisian yang berhasil tidak dapat dibatalkan.',
    };
  }

  return {
    destinationLabel: 'Nomor / ID tujuan',
    confirmationLabel: 'Ulangi nomor / ID tujuan',
    placeholder: 'Masukkan nomor atau ID tujuan',
    help: 'Pastikan nomor atau ID tujuan sudah benar sebelum melanjutkan.',
    inputMode: 'text',
    terms: 'Saya sudah memeriksa nomor atau ID tujuan. Transaksi yang berhasil tidak dapat dibatalkan.',
  };
}

export default function TopupCheckout({ catalog }: { catalog: Catalog }) {
  const [selected, setSelected] = useState<Product | null>(null);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');
  const [idempotency, setIdempotency] = useState(() => crypto.randomUUID());
  const isPostpaid = catalog.mode.value === 'postpaid';
  const profile = useMemo(() => formProfile(selected, isPostpaid), [isPostpaid, selected]);
  const checkoutDisabled = !catalog.integrations_ready || !selected || pending;
  const summary = selected?.product_name || 'Pilih produk dahulu';

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!selected) {
      setMessage('Pilih produk terlebih dahulu.');

      return;
    }

    setPending(true);
    setMessage('');
    const data = Object.fromEntries(new FormData(event.currentTarget));

    try {
      const response = await fetch('/backend/v1/topup/checkout', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, product_id: selected.id, idempotency_key: idempotency }),
      });
      const payload = await response.json() as {
        errors?: Record<string, string[]>;
        message?: string;
        redirect_url?: string;
      };

      if (!response.ok || !payload.redirect_url) {
        const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        setMessage(first || payload.message || 'Checkout belum dapat diproses.');

        return;
      }

      window.location.assign(payload.redirect_url);
    } catch {
      setMessage('Koneksi ke Laravel terputus. Silakan coba lagi.');
    } finally {
      setPending(false);
    }
  }

  return (
    <>
      <div className="topup-tabs">
        <a className={!isPostpaid ? 'active' : ''} href="/topup?mode=prepaid">Prabayar</a>
        <a className={isPostpaid ? 'active' : ''} href="/topup?mode=postpaid">Pascabayar</a>
      </div>

      {!catalog.integrations_ready && (
        <div className="topup-warning">
          <strong>Pembelian belum diaktifkan</strong>
          <span>Katalog dapat dilihat, tetapi checkout menunggu integrasi Digiflazz dan Midtrans.</span>
        </div>
      )}

      {isPostpaid && (
        <div className="topup-info">
          Masukkan ID pelanggan, lalu kami cek nama dan nominal tagihan sebelum pembayaran. Biaya admin Rp {money.format(catalog.postpaid_admin_fee)}.
        </div>
      )}

      <div className="topup-layout">
        <div>
          <form className="topup-filters" method="get">
            <input type="hidden" name="mode" value={catalog.mode.value} />
            <input name="q" placeholder="Cari produk atau brand" aria-label="Cari produk atau brand" />
            <select name="category" aria-label="Filter kategori" defaultValue="">
              <option value="">Semua kategori</option>
              {catalog.categories.map((category) => <option key={category} value={category}>{category}</option>)}
            </select>
            <select name="brand" aria-label="Filter brand" defaultValue="">
              <option value="">Semua brand</option>
              {catalog.brands.map((brand) => <option key={brand} value={brand}>{brand}</option>)}
            </select>
            <button className="button button-dark" type="submit">Tampilkan</button>
          </form>

          <div className="topup-catalog-heading">
            <div>
              <p className="eyebrow">Katalog {catalog.mode.label}</p>
              <h2>{isPostpaid ? 'Pilih jenis tagihan' : 'Pilih produk digital'}</h2>
            </div>
            <span>{catalog.products.length} produk</span>
          </div>

          {catalog.products.length === 0 ? (
            <div className="topup-empty-state">
              <strong>Katalog {catalog.mode.label.toLowerCase()} belum tersedia.</strong>
              <p>Layanan akan muncul setelah katalog provider diperbarui. Silakan coba kembali beberapa saat lagi atau hubungi operator.</p>
            </div>
          ) : (
            <div className="topup-product-grid">
              {catalog.products.map((product) => {
                const active = selected?.id === product.id;
                const productIsPostpaid = product.transaction_type === 'postpaid';

                return (
                  <button
                    aria-pressed={active}
                    className={`topup-product-card ${active ? 'selected' : ''}`}
                    key={product.id}
                    onClick={() => {
                      setSelected(product);
                      setIdempotency(crypto.randomUUID());
                      setMessage('');
                    }}
                    type="button"
                  >
                    <span className="topup-product-category">{product.category}</span>
                    <strong>{product.product_name}</strong>
                    <small>{product.brand}</small>
                    {product.description && <p>{product.description}</p>}
                    <span className="topup-product-price">
                      <small>{productIsPostpaid ? 'Nominal setelah cek' : 'Harga total'}</small>
                      <b>{productIsPostpaid ? 'Cek tagihan' : `Rp ${money.format(product.selling_price)}`}</b>
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <aside className="topup-checkout-card">
          <header>
            <p>Ringkasan pesanan</p>
            <h2>{summary}</h2>
            <span>{selected?.brand || 'Belum dipilih'}</span>
            <strong>{selected ? (isPostpaid ? 'Cek tagihan dahulu' : `Rp ${money.format(selected.selling_price)}`) : 'Rp 0'}</strong>
          </header>

          <form className="topup-checkout-form" onSubmit={submit}>
            <label>
              {profile.destinationLabel}
              <input
                autoComplete="off"
                inputMode={profile.inputMode}
                maxLength={40}
                name="destination"
                pattern={profile.pattern}
                placeholder={profile.placeholder}
                required
              />
              <small>{profile.help}</small>
            </label>
            <label>
              {profile.confirmationLabel}
              <input
                autoComplete="off"
                inputMode={profile.inputMode}
                maxLength={40}
                name="destination_confirmation"
                pattern={profile.pattern}
                placeholder="Ketik ulang untuk memastikan"
                required
              />
            </label>
            <label>
              Nama pemesan
              <input autoComplete="name" maxLength={100} name="customer_name" required />
            </label>
            <label>
              Nomor WhatsApp
              <input autoComplete="tel" inputMode="tel" maxLength={20} name="customer_phone" placeholder="Contoh: 08219207240" required />
            </label>
            <label>
              Email bukti transaksi
              <input autoComplete="email" maxLength={150} name="customer_email" type="email" required />
            </label>
            <label className="topup-terms">
              <input name="terms" required type="checkbox" value="1" />
              <span>{profile.terms} Saya menyetujui <a href="/syarat-layanan" target="_blank" rel="noreferrer">syarat layanan</a>.</span>
            </label>
            {message && <p className="form-error" role="alert">{message}</p>}
            <button className="button button-lime full-button" disabled={checkoutDisabled} type="submit">
              {pending ? 'Memproses…' : isPostpaid ? 'Cek tagihan & lanjut →' : 'Lanjut ke pembayaran →'}
            </button>
            <small className="topup-payment-note">Pembayaran diproses di Midtrans.</small>
          </form>
        </aside>
      </div>
    </>
  );
}
