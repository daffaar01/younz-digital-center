import type { Metadata } from 'next';
import 'lenis/dist/lenis.css';
import './globals.css';
import Analytics from './analytics';
import AuthBootstrap from './auth-bootstrap';
import IntroSplash from './intro-splash';
import LegacyCacheCleanup from './legacy-cache-cleanup';
import SmoothScroll from './smooth-scroll';
import YounzCursor from './younz-cursor';

const siteUrl = process.env.SITE_URL || 'https://younzdigitalcenter.my.id';

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: 'Younz Digital Center | Solusi Digital Terpadu',
  description: 'Print, desain, website, top up, dan layanan digital dalam satu tempat.',
  openGraph: {
    title: 'Younz Digital Center | Satu tempat. Semua beres.',
    description: 'Print, desain, website, top up, dan layanan digital dalam satu alur yang jelas.',
    url: '/',
    siteName: 'Younz Digital Center',
    locale: 'id_ID',
    type: 'website',
    images: [
      {
        url: '/og.png',
        width: 1536,
        height: 1024,
        alt: 'Younz Digital Center — Satu tempat. Semua beres.',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Younz Digital Center | Satu tempat. Semua beres.',
    description: 'Print, desain, website, top up, dan layanan digital dalam satu alur yang jelas.',
    images: ['/og.png'],
  },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id">
      <body>
        <IntroSplash />
        <LegacyCacheCleanup />
        <SmoothScroll />
        <AuthBootstrap />
        <YounzCursor />
        {children}
        <Analytics />
      </body>
    </html>
  );
}
