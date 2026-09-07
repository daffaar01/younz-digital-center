import type { MetadataRoute } from 'next';

const siteUrl = (process.env.NEXT_PUBLIC_SITE_URL || 'https://younzdigitalcenter.my.id').replace(/\/$/, '');
const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';

type SitemapProduct = { id: number };

async function productEntries(): Promise<MetadataRoute.Sitemap> {
  try {
    const response = await fetch(`${backend}/api/v1/digital-products`, { cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) return [];
    const payload = await response.json() as { data?: SitemapProduct[] };
    if (!Array.isArray(payload.data)) return [];
    return payload.data
      .filter((product) => Number.isInteger(product.id) && product.id > 0)
      .map((product) => ({ url: `${siteUrl}/produk/${product.id}`, changeFrequency: 'weekly' as const, priority: 0.8 }));
  } catch { return []; }
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  return [
    { url: `${siteUrl}/`, changeFrequency: 'weekly', priority: 1 },
    { url: `${siteUrl}/produk`, changeFrequency: 'weekly', priority: 0.9 },
    ...(await productEntries()),
    { url: `${siteUrl}/pesan`, changeFrequency: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/topup`, changeFrequency: 'daily', priority: 0.8 },
    { url: `${siteUrl}/cek-pesanan`, changeFrequency: 'monthly', priority: 0.6 },
    { url: `${siteUrl}/kebijakan-privasi`, changeFrequency: 'yearly', priority: 0.3 },
    { url: `${siteUrl}/syarat-layanan`, changeFrequency: 'yearly', priority: 0.3 },
  ];
}
