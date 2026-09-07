const laravelUrl = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8081';
const isDevelopment = process.env.NODE_ENV !== 'production';
const scriptSources = [
  "'self'",
  "'unsafe-inline'",
  ...(isDevelopment ? ["'unsafe-eval'"] : []),
  "'wasm-unsafe-eval'",
  'https://static.cloudflareinsights.com',
  'https://apis.google.com',
  'https://www.google.com',
  'https://www.gstatic.com',
];

const contentSecurityPolicy = [
  "default-src 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  "frame-ancestors 'none'",
  "form-action 'self' https://app.midtrans.com https://app.sandbox.midtrans.com",
  "img-src 'self' data: blob: https://images.unsplash.com https://lh3.googleusercontent.com https://apis.google.com https://www.google.com https://www.gstatic.com",
  "font-src 'self' data:",
  "style-src 'self' 'unsafe-inline'",
  `script-src ${scriptSources.join(' ')}`,
  "connect-src 'self' https://cloudflareinsights.com https://identitytoolkit.googleapis.com https://securetoken.googleapis.com https://www.googleapis.com https://apis.google.com https://www.google.com https://www.gstatic.com",
  "frame-src 'self' https://www.google.com https://accounts.google.com",
  "media-src 'self' blob:",
  "worker-src 'self' blob:",
  'upgrade-insecure-requests',
].join('; ');

const securityHeaders = [
  { key: 'Content-Security-Policy', value: contentSecurityPolicy },
  { key: 'X-Frame-Options', value: 'DENY' },
  { key: 'Strict-Transport-Security', value: 'max-age=31536000; includeSubDomains' },
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=(), payment=()' },
];

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  outputFileTracingRoot: process.cwd(),
  turbopack: { root: process.cwd() },
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'images.unsplash.com',
      },
    ],
  },
  async headers() {
    return [
      {
        source: '/:path((?!__/auth/).*)',
        headers: securityHeaders,
      },
      {
        source: '/akun/:path*',
        headers: [{ key: 'Cache-Control', value: 'private, no-store, max-age=0, must-revalidate' }],
      },
      {
        source: '/animations/:path*',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
      {
        source: '/fonts/:path*',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
      {
        source: '/brand/:path*',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
    ];
  },
  async redirects() {
    return [
      {
        source: '/',
        has: [{ type: 'host', value: 'admin.younzdigitalcenter.my.id' }],
        destination: '/admin/masuk',
        permanent: false,
      },
      { source: '/login', destination: '/admin/masuk', permanent: true },
      { source: '/akses-pegawai', destination: '/admin/masuk', permanent: true },
      { source: '/pesanan-saya', destination: '/akun', permanent: true },
      { source: '/dashboard', destination: '/admin/dashboard', permanent: true },
      { source: '/orders', destination: '/admin/pesanan', permanent: true },
      { source: '/orders/:id', destination: '/admin/pesanan/:id', permanent: true },
      { source: '/products', destination: '/admin/produk', permanent: true },
      { source: '/suppliers', destination: '/admin/supplier', permanent: true },
      { source: '/expenses', destination: '/admin/pengeluaran', permanent: true },
      { source: '/reports/daily', destination: '/admin/laporan', permanent: true },
      { source: '/approvals', destination: '/admin/persetujuan', permanent: true },
      { source: '/approvals/:id', destination: '/admin/persetujuan/:id', permanent: true },
      { source: '/customers', destination: '/admin/pelanggan', permanent: true },
      { source: '/digital-transactions', destination: '/admin/transaksi-digital', permanent: true },
      { source: '/pos', destination: '/admin/modul/kasir', permanent: true },
      { source: '/sales/:path*', destination: '/admin/modul/kasir', permanent: true },
      { source: '/knowledge', destination: '/admin/modul/knowledge', permanent: true },
      { source: '/testimonials', destination: '/admin/modul/testimoni', permanent: true },
      { source: '/integrations/whatsapp', destination: '/admin/modul/whatsapp', permanent: true },
      { source: '/employees', destination: '/admin/modul/pegawai', permanent: true },
    ];
  },
  async rewrites() {
    return {
      beforeFiles: [
        { source: '/backend/:path*', destination: '/api/laravel/:path*' },
        { source: '/api/v1/:path*', destination: `${laravelUrl}/api/v1/:path*` },
        { source: '/webhooks/:path*', destination: `${laravelUrl}/webhooks/:path*` },
        { source: '/__/auth/:path*', destination: `${laravelUrl}/__/auth/:path*` },
        { source: '/service-worker.js', destination: `${laravelUrl}/service-worker.js` },
        { source: '/email/verify/:path*', destination: `${laravelUrl}/email/verify/:path*` },
      ],
      fallback: [],
    };
  },
};

export default nextConfig;
