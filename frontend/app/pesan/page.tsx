import type { Metadata } from 'next';
import OrderForm from './order-form';
import SiteHeader from '../site-header';

type Service = { id: number; name: string; type: string };
type Variant = { id: number; label: string; price: number | null; price_label: string; stock: number | null; stock_label: string };
type Product = { id: number; name: string; price: number | null; price_label: string; variants: Variant[] };
export const metadata: Metadata = { title: 'Pesan Layanan | Younz Digital Center', description: 'Kirim kebutuhan dan file Anda. Operator memeriksa spesifikasi sebelum memberikan estimasi.' };

const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
async function getServices(): Promise<Service[]> {
  try { const response = await fetch(`${backend}/api/v1/services`, { cache: 'no-store', headers: { Accept: 'application/json' } }); if (!response.ok) return []; return ((await response.json()) as { data: Service[] }).data; } catch { return []; }
}
async function getProduct(id: string): Promise<Product | null> {
  if (!id) return null;
  try { const response = await fetch(`${backend}/api/v1/digital-products/${id}`, { cache: 'no-store', headers: { Accept: 'application/json' } }); if (!response.ok) return null; return ((await response.json()) as { data: Product }).data; } catch { return null; }
}

export default async function OrderPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const params = await searchParams;
  const selectedService = typeof params.service_id === 'string' ? params.service_id : '';
  const source = typeof params.source === 'string' ? params.source : 'direct';

  const initialQuantity = typeof params.quantity === 'string' && /^\d{1,2}$/.test(params.quantity) ? Math.max(1, Number(params.quantity)) : 1;
  const initialProductId = typeof params.product_id === 'string' && /^\d+$/.test(params.product_id) ? params.product_id : '';
  const requestedVariantId = typeof params.variant_id === 'string' && /^\d+$/.test(params.variant_id) ? Number(params.variant_id) : null;
  const [services, product] = await Promise.all([getServices(), getProduct(initialProductId)]);
  const variant = requestedVariantId === null ? null : product?.variants.find((item) => item.id === requestedVariantId) ?? null;
  const requiresVariant = Boolean(product?.variants.length);
  const isDigital = source.startsWith('digital-product') && Boolean(initialProductId);
  const unitPrice = requiresVariant ? variant?.price ?? null : product?.price ?? null;
  const priceLabel = requiresVariant ? variant?.price_label ?? 'Pilih ulang durasi' : product?.price_label ?? 'Konfirmasi harga';
  return <main><SiteHeader /><section className="form-page shell"><p className="eyebrow">Pesan layanan</p><h1>{isDigital ? 'Lanjutkan pesanan produk digital' : 'Kirim kebutuhan dan file Anda'}</h1><p className="form-lead">{isDigital ? 'Server memeriksa status produk, varian durasi, stok, kuantitas, dan harga. Produk berharga dilanjutkan ke Midtrans; produk tanpa harga diperiksa operator lebih dulu.' : 'Isi detail singkat. Operator memeriksa file dan spesifikasi sebelum memberikan estimasi melalui WhatsApp.'}</p><ol className="step-grid"><li><span>1</span><strong>Kirim kebutuhan</strong></li><li><span>2</span><strong>{isDigital ? 'Verifikasi produk' : 'Diperiksa operator'}</strong></li><li><span>3</span><strong>{isDigital ? 'Bayar atau konfirmasi' : 'Konfirmasi & proses'}</strong></li></ol><div className="form-layout"><OrderForm services={services} selectedService={selectedService} source={source} initialProduct={product?.name ?? ''} productAvailable={!isDigital || product !== null} initialProductId={initialProductId} initialQuantity={initialQuantity} initialVariantId={variant?.id ?? null} initialVariantLabel={variant?.label ?? null} variantRequired={requiresVariant} initialUnitPrice={unitPrice} initialPriceLabel={priceLabel} /><aside className="form-aside"><h2>Sebelum pesanan diproses</h2><ul><li><strong>Pembayaran sesuai harga database</strong><span>{isDigital ? 'Jika harga tersedia, Anda diarahkan ke Midtrans. Jika belum, operator akan mengonfirmasi harga melalui WhatsApp.' : 'Operator mengonfirmasi estimasi sebelum pekerjaan dimulai.'}</span></li><li><strong>Data diproses aman</strong><span>Detail pesanan hanya digunakan untuk verifikasi dan pemenuhan layanan.</span></li><li><strong>Status dapat dipantau</strong><span>Nomor pelacakan diberikan setelah formulir berhasil dikirim.</span></li></ul><a className="button button-light full-button" href="https://wa.me/628219207240">Tanya lewat WhatsApp</a></aside></div></section></main>;
}
