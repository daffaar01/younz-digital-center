'use client';

import { usePathname } from 'next/navigation';
import { useEffect, useState } from 'react';

const SPLASH_DURATION_MS = 1200;
const FADE_DURATION_MS = 450;

export default function IntroSplash() {
  const pathname = usePathname();
  const [visible, setVisible] = useState(true);
  const [leaving, setLeaving] = useState(false);

  useEffect(() => {
    if (pathname !== '/') {
      setVisible(false);
      return;
    }

    const fadeTimer = window.setTimeout(
      () => setLeaving(true),
      SPLASH_DURATION_MS - FADE_DURATION_MS,
    );
    const closeTimer = window.setTimeout(() => setVisible(false), SPLASH_DURATION_MS);

    return () => {
      window.clearTimeout(fadeTimer);
      window.clearTimeout(closeTimer);
    };
  }, [pathname]);

  if (!visible || pathname !== '/') return null;

  return (
    <div
      className={`intro-splash${leaving ? ' is-leaving' : ''}`}
      role="status"
      aria-label="Memuat Younz Digital Center"
      aria-live="polite"
    >
      <img src="/younz-loading.svg" alt="" width="520" height="293" aria-hidden="true" />
    </div>
  );
}
