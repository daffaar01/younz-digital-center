'use client';

import { useEffect, useState } from 'react';

type Job = () => Promise<void>;
const queue: Job[] = [];
let active = 0;
const MAX_CONCURRENT = 1;

function drain() {
  while (active < MAX_CONCURRENT && queue.length > 0) {
    const job = queue.shift();
    if (!job) return;
    active += 1;
    job().finally(() => {
      active -= 1;
      drain();
    });
  }
}

function enqueue(job: Job, priority = false) {
  if (priority) queue.unshift(job);
  else queue.push(job);
  drain();
}

async function fetchImage(src: string) {
  let response: Response | null = null;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    response = await fetch(src, { headers: { Accept: 'image/*' }, cache: 'force-cache' });
    if (response.ok || response.status < 500) break;
    await new Promise((resolve) => setTimeout(resolve, 350 * (attempt + 1)));
  }
  if (!response?.ok) throw new Error('Foto produk tidak dapat dimuat.');
  return response.blob();
}

export default function ProductImage({ src, name, priority = false }: { src: string; name: string; priority?: boolean }) {
  const [display, setDisplay] = useState('');
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    let objectUrl = '';
    enqueue(async () => {
      try {
        const blob = await fetchImage(src);
        if (cancelled) return;
        objectUrl = URL.createObjectURL(blob);
        setDisplay(objectUrl);
      } catch {
        if (!cancelled) setFailed(true);
      }
    }, priority);
    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [src]);

  if (failed) return <span className="product-page-image-placeholder product-page-image-error" role="img" aria-label={`Foto produk ${name} tidak tersedia`}>Foto tidak tersedia</span>;
  return display
    ? <img src={display} alt={`Foto produk ${name}`} width="720" height="405" />
    : <span className="product-page-image-placeholder" role="img" aria-label={`Foto produk ${name} sedang dimuat`}>Memuat foto…</span>;
}
