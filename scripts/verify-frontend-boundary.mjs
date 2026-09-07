import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');

async function source(path) {
  return readFile(resolve(root, path), 'utf8');
}

function assert(condition, message) {
  if (!condition) throw new Error(`Frontend boundary failed: ${message}`);
}

const nextConfig = await source('frontend/next.config.mjs');

assert(!/source:\s*['"]\/:path\*['"]/.test(nextConfig), 'Next.js must not contain a global Laravel fallback.');

console.log('Frontend boundary verified: Next.js UI is isolated from Laravel fallback.');
