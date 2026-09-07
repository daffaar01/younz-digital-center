'use client';

import { usePathname } from 'next/navigation';
import { useEffect, useState } from 'react';

export type AnalyticsEventName =
  | 'page_view'
  | 'order_cta_clicked'
  | 'order_form_started'
  | 'order_submitted'
  | 'order_submit_failed'
  | 'track_order_started'
  | 'topup_cta_clicked'
  | 'ai_question_sent';

const CONSENT_KEY = 'ydc_analytics_consent';
const VISITOR_KEY = 'ydc_analytics_visitor';
const SAFE_PATHS = new Set(['/', '/pesan', '/cek-pesanan', '/topup']);

function getVisitorId(): string {
  const existing = localStorage.getItem(VISITOR_KEY);
  if (existing) return existing;

  const created = crypto.randomUUID();
  localStorage.setItem(VISITOR_KEY, created);

  return created;
}

export async function trackAnalyticsEvent(
  event: AnalyticsEventName,
  source?: string,
  path = window.location.pathname,
): Promise<void> {
  if (localStorage.getItem(CONSENT_KEY) !== 'granted') return;
  if (!SAFE_PATHS.has(path)) return;

  try {
    await fetch('/backend/v1/analytics/events', {
      method: 'POST',
      keepalive: true,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        event,
        source,
        path,
        visitor_id: getVisitorId(),
        analytics_consent: '1',
      }),
    });
  } catch {
    // Analytics must never block the customer journey.
  }
}

export default function Analytics() {
  const pathname = usePathname();
  const [consent, setConsent] = useState<'loading' | 'unknown' | 'granted' | 'denied'>('loading');

  useEffect(() => {
    const saved = localStorage.getItem(CONSENT_KEY);
    setConsent(saved === 'granted' || saved === 'denied' ? saved : 'unknown');
  }, []);

  useEffect(() => {
    if (consent !== 'granted') return;
    if (!SAFE_PATHS.has(pathname)) return;

    const key = `ydc_page_view:${pathname}`;
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');
    void trackAnalyticsEvent('page_view', undefined, pathname);
  }, [consent, pathname]);

  useEffect(() => {
    function trackClick(event: MouseEvent) {
      const element = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        '[data-analytics-event]',
      );
      if (!element) return;

      const eventName = element.dataset.analyticsEvent as AnalyticsEventName | undefined;
      if (!eventName) return;

      void trackAnalyticsEvent(eventName, element.dataset.analyticsSource);
    }

    document.addEventListener('click', trackClick);

    return () => document.removeEventListener('click', trackClick);
  }, []);

  function choose(value: 'granted' | 'denied') {
    localStorage.setItem(CONSENT_KEY, value);
    setConsent(value);
  }

  if (pathname.startsWith('/admin') || consent !== 'unknown') return null;

  const productDetail = /^\/produk\/\d+$/.test(pathname);

  return (
    <aside className={`analytics-consent${productDetail ? ' analytics-consent--product-detail' : ''}`} aria-label="Pilihan statistik kunjungan">
      <div>
        <strong>Bantu kami memperbaiki alur pemesanan</strong>
        <p>
          Statistik anonim membantu kami memperbaiki alur pemesanan. Tidak menyimpan nama,
          nomor WhatsApp, isi chat, atau file.
        </p>
        <a href="/kebijakan-privasi">Pelajari kebijakan privasi</a>
      </div>
      <div className="analytics-consent-actions">
        <button type="button" onClick={() => choose('denied')}>Hanya esensial</button>
        <button type="button" className="allow" onClick={() => choose('granted')}>
          Izinkan statistik
        </button>
      </div>
    </aside>
  );
}
