/**
 * Penyimpanan JSON sederhana berbasis file.
 *
 * Dipakai untuk data ringan seperti nomor tiket dan catatan peringatan.
 * Penulisan dilakukan secara atomik lewat file sementara agar isi tidak
 * rusak bila proses berhenti di tengah operasi.
 */

import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';

import { config } from '../core/config.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('store');
const dataDir = join(config.projectRoot, 'data');

export class JsonStore {
  constructor(fileName, defaults = {}) {
    this.path = join(dataDir, fileName);
    this.defaults = defaults;
    this.cache = null;
    this.writing = Promise.resolve();
  }

  async read() {
    if (this.cache) {
      return this.cache;
    }

    try {
      const raw = await readFile(this.path, 'utf8');
      this.cache = { ...this.defaults, ...JSON.parse(raw) };
    } catch (error) {
      if (error.code !== 'ENOENT') {
        log.warn({ file: this.path, err: error.message }, 'Gagal membaca store, memakai nilai bawaan');
      }

      this.cache = { ...this.defaults };
    }

    return this.cache;
  }

  async write(mutator) {
    const current = await this.read();
    const next = mutator(current) ?? current;

    this.cache = next;

    this.writing = this.writing.then(async () => {
      await mkdir(dirname(this.path), { recursive: true });

      const temporary = `${this.path}.tmp`;
      await writeFile(temporary, JSON.stringify(next, null, 2), 'utf8');
      await rename(temporary, this.path);
    }).catch((error) => {
      log.error({ file: this.path, err: error.message }, 'Gagal menulis store');
    });

    await this.writing;

    return next;
  }
}
