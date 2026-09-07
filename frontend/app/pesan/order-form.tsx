'use client';

import { FormEvent, useEffect, useRef, useState } from 'react';
import { trackAnalyticsEvent } from '../analytics';

type Service = { id: number; name: string; type: string };

export default function OrderForm({
  services,
  selectedService,
  source,
  initialProduct,
  productAvailable,
  initialProductId,
  initialQuantity,
  initialVariantId,
  initialVariantLabel,
  variantRequired,
  initialUnitPrice,
  initialPriceLabel,
}: {
  services: Service[];
  selectedService: string;
  source: string;
  initialProduct: string;
  productAvailable: boolean;
  initialProductId: string;
  initialQuantity: number;
  initialVariantId: number | null;
  initialVariantLabel: string | null;
  variantRequired: boolean;
  initialUnitPrice: number | null;
  initialPriceLabel: string;
}) {
  const selected = services.find((service) => String(service.id) === selectedService);
  const [serviceId, setServiceId] = useState(selectedService);
  const [type, setType] = useState(selected?.type || (source.startsWith('digital-product') ? 'digital' : ''));
  const isDigital = type === 'digital' && Boolean(initialProductId);
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState('');
  const started = useRef(false);
  const submitting = useRef(false);
  const errorSummary = useRef<HTMLDivElement>(null);
  const idempotencyKey = useRef('');
  const recoveryKey = `ydc-checkout-${initialProductId}-${initialVariantId ?? 'base'}-${initialQuantity}`;
  const missingVariant = isDigital && variantRequired && initialVariantId === null;
  const missingProduct = isDigital && !productAvailable;

  useEffect(() => {
    if (isDigital) idempotencyKey.current = sessionStorage.getItem(recoveryKey) || '';
  }, [isDigital, recoveryKey]);

  function markStarted() {
    if (started.current) return;
    started.current = true;
    void trackAnalyticsEvent('order_form_started', source || 'direct');
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting.current) return;
    submitting.current = true;
    setPending(true);
    setErrors({});
    setGeneralError('');

    const data = new FormData(event.currentTarget);
    if (isDigital) {
      if (!idempotencyKey.current) {
        idempotencyKey.current = crypto.randomUUID();
        sessionStorage.setItem(recoveryKey, idempotencyKey.current);
      }
      data.set('idempotency_key', idempotencyKey.current);
    }
    const file = data.get('file');
    if (!(file instanceof File) || !file.size) data.delete('file');

    try {
      const token = localStorage.getItem('ydc_api_token');
      const endpoint = isDigital ? '/backend/v1/public/orders' : token ? '/backend/v1/orders' : '/backend/v1/public/orders';
      const headers: Record<string, string> = { Accept: 'application/json' };
      if (token && !isDigital) headers.Authorization = `Bearer ${token}`;

      const response = await fetch(endpoint, { method: 'POST', body: data, headers });
      const payload = await response.json();
      if (!response.ok) {
        if (payload.tracking_token) {
          sessionStorage.removeItem(recoveryKey);
          window.location.assign(`/cek-pesanan/${payload.tracking_token}`);
          return;
        }
        setErrors(payload.errors || {});
        setGeneralError(payload.message || 'Pesanan belum dapat dikirim.');
        requestAnimationFrame(() => errorSummary.current?.focus());
        void trackAnalyticsEvent('order_submit_failed', source || 'direct');
        return;
      }

      await trackAnalyticsEvent('order_submitted', source || 'direct');
      sessionStorage.removeItem(recoveryKey);
      if (payload.payment?.redirect_url) {
        window.location.assign(payload.payment.redirect_url);
        return;
      }
      window.location.assign(`/cek-pesanan/${payload.tracking_token}`);
    } catch {
      setGeneralError('Koneksi ke Laravel terputus. Silakan coba lagi.');
      void trackAnalyticsEvent('order_submit_failed', source || 'direct');
    } finally {
      submitting.current = false;
      setPending(false);
    }
  }

  return (
    <form className="next-form" onSubmit={submit} onFocusCapture={markStarted} encType="multipart/form-data">
      <input type="hidden" name="source" value={source} />
      {isDigital && <><input type="hidden" name="product_id" value={initialProductId} />{initialVariantId !== null && <input type="hidden" name="variant_id" value={initialVariantId} />}<input type="hidden" name="quantity" value={initialQuantity} /></>}

      {generalError && <div ref={errorSummary} tabIndex={-1} role="alert" aria-live="assertive" className="form-error form-wide">{generalError}{isDigital && (errors.product_id || errors.variant_id || errors.quantity) && <> <a href={`/produk/${initialProductId}`}>Pilih ulang produk, durasi, atau kuantitas.</a></>}</div>}
      <label>Nama<input name="customer_name" required maxLength={150} aria-invalid={Boolean(errors.customer_name)} aria-describedby={errors.customer_name ? 'customer-name-error' : undefined} />{errors.customer_name && <small id="customer-name-error">{errors.customer_name[0]}</small>}</label>
      <label>Nomor WhatsApp<input name="customer_phone" required maxLength={30} inputMode="tel" aria-invalid={Boolean(errors.customer_phone)} aria-describedby={errors.customer_phone ? 'customer-phone-error' : undefined} />{errors.customer_phone && <small id="customer-phone-error">{errors.customer_phone[0]}</small>}</label>
      {isDigital ? (
        <>
          <input type="hidden" name="type" value="digital" />
          <div className="form-wide digital-order-summary"><strong>{missingProduct ? 'Produk tidak tersedia' : initialProduct}</strong>{initialVariantLabel && !missingProduct && <span>Durasi: <strong>{initialVariantLabel}</strong></span>}<span>Kuantitas: {initialQuantity}</span><span>Harga satuan: {initialPriceLabel}</span>{initialUnitPrice !== null && !missingProduct && <span><strong>Total: Rp {(initialUnitPrice * initialQuantity).toLocaleString('id-ID')}</strong></span>}{missingProduct && <small role="alert">Produk tidak tersedia atau dinonaktifkan. <a href="/produk">Kembali ke katalog.</a></small>}{missingVariant && !missingProduct && <small role="alert">Durasi belum dipilih. <a href={`/produk/${initialProductId}`}>Kembali ke produk dan pilih durasi.</a></small>}{!missingProduct && !missingVariant && <small>{initialUnitPrice !== null ? 'Nominal diverifikasi ulang server. Setelah dikirim, Anda akan diarahkan ke pembayaran Midtrans.' : 'Harga akan dikonfirmasi operator. Tidak ada transaksi Midtrans sampai nominal tersedia.'}</small>}</div>
          <label className="form-wide">Catatan tambahan<textarea name="notes" rows={5} maxLength={3000} placeholder="Paket, durasi, akun tujuan, atau kebutuhan lain yang perlu dikonfirmasi…" /></label>
        </>
      ) : (
        <>
          <label>Jenis layanan<select name="type" required value={type} onChange={(event) => setType(event.target.value)}>
            <option value="">Pilih layanan</option>
            {['print', 'fotokopi', 'scan', 'ketik', 'desain', 'website', 'aplikasi'].map((item) => (
              <option key={item} value={item}>{item[0].toUpperCase() + item.slice(1)}</option>
            ))}
          </select></label>
          <label>Paket layanan<select name="service_id" value={serviceId} onChange={(event) => {
            setServiceId(event.target.value);
            const service = services.find((item) => String(item.id) === event.target.value);
            if (service) setType(service.type);
          }}>
            <option value="">Akan ditentukan operator</option>
            {services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}
          </select></label>
          <label>Ukuran kertas<select name="specifications[paper_size]">
            <option value="">Tidak berlaku / belum tahu</option>
            {['A4', 'F4', 'A3', 'A5'].map((size) => <option key={size}>{size}</option>)}
          </select></label>
          <label>Mode warna<select name="specifications[color_mode]">
            <option value="">Belum ditentukan</option>
            <option value="black_white">Hitam putih</option>
            <option value="color">Warna</option>
          </select></label>
          <label className="form-wide">Catatan dan spesifikasi<textarea name="notes" rows={5} maxLength={3000} placeholder="Jumlah rangkap, finishing, deadline…" /></label>
          <label className="form-wide">File, maksimal 20 MB<input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt" /><span className="input-help">PDF, Office, gambar, atau teks. File disimpan privat.</span>{errors.file && <small>{errors.file[0]}</small>}</label>
        </>
      )}
      <button type="submit" className="button button-dark form-wide submit-button" disabled={pending || missingProduct || missingVariant}>{pending ? 'Menyiapkan pesanan…' : missingProduct ? 'Produk tidak tersedia' : missingVariant ? 'Pilih durasi di halaman produk' : isDigital && initialUnitPrice !== null ? 'Lanjut ke pembayaran Midtrans' : isDigital ? 'Kirim untuk konfirmasi operator' : 'Kirim pesanan untuk diperiksa'}</button>
    </form>
  );
}
