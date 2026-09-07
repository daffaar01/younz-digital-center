'use client';

import { useEffect } from 'react';

const LEGACY_CACHE_PREFIX = 'younz-static-';

export default function LegacyCacheCleanup() {
  useEffect(() => {
    const cleanup = async () => {
      if ('serviceWorker' in navigator) {
        const registrations = await navigator.serviceWorker.getRegistrations();
        await Promise.all(
          registrations
            .filter((registration) => new URL(registration.scope).origin === window.location.origin)
            .map((registration) => registration.unregister()),
        );
      }

      if ('caches' in window) {
        const cacheNames = await window.caches.keys();
        await Promise.all(
          cacheNames
            .filter((cacheName) => cacheName.startsWith(LEGACY_CACHE_PREFIX))
            .map((cacheName) => window.caches.delete(cacheName)),
        );
      }
    };

    void cleanup();
  }, []);

  return null;
}
