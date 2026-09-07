/**
 * Memuat variabel lingkungan dari file .env tanpa dependensi eksternal.
 *
 * Nilai yang sudah ada di process.env tidak ditimpa, sehingga panel hosting
 * yang menyuntikkan environment variable tetap menang.
 */

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export const projectRoot = join(here, '..', '..');

function stripQuotes(value) {
  const trimmed = value.trim();

  if (trimmed.length < 2) {
    return trimmed;
  }

  const first = trimmed[0];
  const last = trimmed[trimmed.length - 1];

  if ((first === '"' && last === '"') || (first === "'" && last === "'")) {
    return trimmed.slice(1, -1);
  }

  return trimmed;
}

export function parseEnv(contents) {
  const result = {};

  for (const rawLine of contents.split(/\r?\n/)) {
    const line = rawLine.trim();

    if (line === '' || line.startsWith('#')) {
      continue;
    }

    const withoutExport = line.startsWith('export ') ? line.slice(7) : line;
    const separator = withoutExport.indexOf('=');

    if (separator <= 0) {
      continue;
    }

    const key = withoutExport.slice(0, separator).trim();

    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(key)) {
      continue;
    }

    result[key] = stripQuotes(withoutExport.slice(separator + 1));
  }

  return result;
}

export function loadEnv(path = join(projectRoot, '.env')) {
  let contents;

  try {
    contents = readFileSync(path, 'utf8');
  } catch (error) {
    if (error.code === 'ENOENT') {
      return {};
    }

    throw error;
  }

  const parsed = parseEnv(contents);

  for (const [key, value] of Object.entries(parsed)) {
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }

  return parsed;
}
