'use client';

import { useState } from 'react';

type ShowcaseProps = {
  openHours: string;
};

export default function LandingShowcase({ openHours }: ShowcaseProps) {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <section id="beranda" className="blue-hero">
      <video
        className="blue-hero-live-wallpaper"
        autoPlay
        muted
        loop
        playsInline
        preload="metadata"
        poster="/animations/HSRLiveWallpaper-poster.webp?v=511eaa03"
        aria-hidden="true"
        tabIndex={-1}
      >
        <source src="/animations/HSRLiveWallpaper.mp4?v=511eaa03" type="video/mp4" />
      </video>
      <div className="blue-hero-live-overlay" aria-hidden="true" />
      <header className="blue-nav">
        <div className="blue-nav-identity">
          <a className="blue-brand" href="#beranda" aria-label="Beranda Younz Digital Center">
            <img src="/brand/younz-wordmark-inverse.svg" alt="Younz Digital Center" width="188" height="44" />
          </a>
          <div className="blue-nav-status"><i aria-hidden="true" /><span>Buka hari ini</span><small>{openHours}</small></div>
        </div>

        <button
          className="blue-menu-button"
          type="button"
          aria-label={menuOpen ? 'Tutup navigasi' : 'Buka navigasi'}
          aria-expanded={menuOpen}
          aria-controls="navigasi-utama"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span aria-hidden="true" />
          <span aria-hidden="true" />
        </button>

        <nav id="navigasi-utama" className={menuOpen ? 'is-open' : ''} aria-label="Navigasi utama">
          <a href="#layanan" onClick={() => setMenuOpen(false)}>Kategori</a>
          <a href="/produk" onClick={() => setMenuOpen(false)}>Produk</a>
          <a href="#cara-kerja" onClick={() => setMenuOpen(false)}>Cara kerja</a>
          <a href="#younz-ai" onClick={() => setMenuOpen(false)}>Younz AI</a>
          <a href="/topup" onClick={() => setMenuOpen(false)}>Top Up</a>
        </nav>

        <div className="blue-nav-actions">
          <a href="/akun">Masuk</a>
          <a className="blue-button blue-button-lime blue-button-small" href="/pesan" data-analytics-event="order_cta_clicked" data-analytics-source="landing_header">
            Mulai pesan <span aria-hidden="true">↗</span>
          </a>
        </div>
      </header>

      <div className="blue-hero-grid">
        <div className="blue-hero-copy">
          <p className="blue-hero-kicker">Creative & digital service center</p>
          <h1>
            Ide kamu.<br />
            <span>Kami bikin nyata.</span>
          </h1>
          <div className="blue-hero-actions">
            <a className="blue-button blue-button-lime" href="/pesan" data-analytics-event="order_cta_clicked" data-analytics-source="landing_hero">
              Ceritakan kebutuhanmu <span aria-hidden="true">↗</span>
            </a>
          </div>
        </div>
      </div>
      <a className="blue-hero-scroll-cue" href="#konten-utama"><span>Jelajahi kategori</span><i aria-hidden="true">↓</i></a>
    </section>
  );
}
