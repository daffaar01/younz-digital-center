/**
 * Pembatas laju sederhana berbasis jendela geser di memori.
 *
 * Dipakai oleh anti-spam dan pembatasan pembuatan tiket. Entri lama
 * dibersihkan otomatis agar penggunaan memori tetap stabil.
 */

export class SlidingWindowLimiter {
  constructor({ max, windowMs }) {
    this.max = max;
    this.windowMs = windowMs;
    this.buckets = new Map();
    this.lastCleanup = Date.now();
  }

  /**
   * Mencatat satu kejadian dan mengembalikan status pelanggaran.
   */
  hit(key) {
    const now = Date.now();

    this.cleanup(now);

    const timestamps = this.buckets.get(key) ?? [];
    const recent = timestamps.filter((time) => now - time < this.windowMs);

    recent.push(now);
    this.buckets.set(key, recent);

    return {
      count: recent.length,
      exceeded: recent.length > this.max,
      retryAfterMs: recent.length > this.max ? this.windowMs - (now - recent[0]) : 0,
    };
  }

  reset(key) {
    this.buckets.delete(key);
  }

  cleanup(now = Date.now()) {
    if (now - this.lastCleanup < this.windowMs) {
      return;
    }

    this.lastCleanup = now;

    for (const [key, timestamps] of this.buckets) {
      const recent = timestamps.filter((time) => now - time < this.windowMs);

      if (recent.length === 0) {
        this.buckets.delete(key);
        continue;
      }

      this.buckets.set(key, recent);
    }
  }
}
