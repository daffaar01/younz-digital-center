import { readdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

import { childLogger } from './logger.js';

const log = childLogger('events');
const here = dirname(fileURLToPath(import.meta.url));

/**
 * Memuat dan mendaftarkan seluruh event handler dari direktori src/events.
 */
export async function registerEvents(client) {
  const eventsDir = join(here, '..', 'events');

  let entries;

  try {
    entries = await readdir(eventsDir, { withFileTypes: true });
  } catch (error) {
    if (error.code === 'ENOENT') {
      log.warn('Direktori events tidak ditemukan.');
      return 0;
    }

    throw error;
  }

  let registered = 0;

  for (const entry of entries) {
    if (!entry.isFile() || !entry.name.endsWith('.js')) {
      continue;
    }

    const modulePath = pathToFileURL(join(eventsDir, entry.name)).href;

    try {
      const module = await import(modulePath);
      const handler = module.default ?? module.event;

      if (!handler?.name || typeof handler.execute !== 'function') {
        log.warn({ file: entry.name }, 'Event dilewati karena struktur tidak lengkap');
        continue;
      }

      const invoke = (...args) => Promise.resolve(handler.execute(...args, client)).catch((error) => {
        log.error({ event: handler.name, err: error.message, stack: error.stack }, 'Event gagal diproses');
      });

      if (handler.once) {
        client.once(handler.name, invoke);
      } else {
        client.on(handler.name, invoke);
      }

      registered += 1;
    } catch (error) {
      log.error({ file: entry.name, err: error.message }, 'Gagal memuat event');
    }
  }

  log.info({ total: registered }, 'Event terdaftar');

  return registered;
}
