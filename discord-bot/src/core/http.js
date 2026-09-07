import { childLogger } from './logger.js';

const log = childLogger('http');

export class HttpError extends Error {
  constructor(message, status, body) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
    this.body = body;
  }
}

/**
 * Wrapper fetch dengan timeout, retry terbatas, dan penanganan error seragam.
 *
 * Retry hanya dilakukan untuk kegagalan jaringan dan status 429/5xx agar
 * permintaan yang sudah diterima server tidak dijalankan dua kali.
 */
export async function requestJson(url, {
  method = 'GET',
  headers = {},
  body,
  timeoutMs = 12_000,
  retries = 2,
  expectJson = true,
} = {}) {
  let lastError;

  for (let attempt = 0; attempt <= retries; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
      const response = await fetch(url, {
        method,
        headers,
        body,
        signal: controller.signal,
      });

      const raw = await response.text();

      if (!response.ok) {
        const retriable = response.status === 429 || response.status >= 500;

        if (retriable && attempt < retries) {
          lastError = new HttpError(`HTTP ${response.status}`, response.status, raw);
          await delay(400 * (attempt + 1));
          continue;
        }

        throw new HttpError(`HTTP ${response.status}`, response.status, raw);
      }

      if (!expectJson || raw.trim() === '') {
        return null;
      }

      try {
        return JSON.parse(stripSseTrailer(raw));
      } catch {
        throw new HttpError('Respons bukan JSON yang valid.', response.status, raw.slice(0, 200));
      }
    } catch (error) {
      const networkFailure = error.name === 'AbortError' || error instanceof TypeError;

      if (networkFailure && attempt < retries) {
        lastError = error;
        log.warn({ url, attempt, reason: error.name }, 'Permintaan gagal, mencoba ulang');
        await delay(400 * (attempt + 1));
        continue;
      }

      throw error;
    } finally {
      clearTimeout(timer);
    }
  }

  throw lastError ?? new Error('Permintaan gagal tanpa detail.');
}

export function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Membuang trailer SSE (misal "data: [DONE]") yang kadang ikut menempel pada
 * respons JSON dari gateway OpenAI-compatible tertentu (misal 9router).
 *
 * Trailer bisa langsung menempel setelah penutup JSON tanpa newline, jadi
 * spasi/baris sebelum "data:" dibuat opsional.
 */
function stripSseTrailer(raw) {
  return raw.replace(/[ \t\r\n]*data: \[DONE\]\s*$/i, '');
}
