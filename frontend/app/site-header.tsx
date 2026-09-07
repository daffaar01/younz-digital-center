export default function SiteHeader() {
  return (
    <header className="site-header">
      <a className="brand" href="/" aria-label="Beranda Younz Digital Center"><img className="site-brand-logo" src="/brand/younz-wordmark-v1.svg" alt="" width="150" height="40" aria-hidden="true" /></a>
      <nav aria-label="Navigasi utama"><a href="/#layanan">Layanan</a><a href="/produk">Produk</a><a href="/topup" data-analytics-event="topup_cta_clicked" data-analytics-source="site_header">Top Up</a><a href="/#cara-kerja">Cara Kerja</a><a href="/#younz-ai">Younz AI</a></nav>
      <div className="header-buttons"><a className="header-account" href="/akun">Akun</a><a className="button button-dark header-action" href="/pesan" data-analytics-event="order_cta_clicked" data-analytics-source="site_header">Mulai pesan</a></div>
    </header>
  );
}
