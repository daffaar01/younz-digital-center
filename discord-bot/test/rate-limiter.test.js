import assert from 'node:assert/strict';
import { test } from 'node:test';

import { SlidingWindowLimiter } from '../src/services/rate-limiter.js';

test('limiter mengizinkan kejadian sampai batas maksimum', () => {
  const limiter = new SlidingWindowLimiter({ max: 3, windowMs: 1000 });

  assert.equal(limiter.hit('a').exceeded, false);
  assert.equal(limiter.hit('a').exceeded, false);
  assert.equal(limiter.hit('a').exceeded, false);
  assert.equal(limiter.hit('a').exceeded, true);
});

test('limiter memisahkan hitungan antar kunci', () => {
  const limiter = new SlidingWindowLimiter({ max: 1, windowMs: 1000 });

  assert.equal(limiter.hit('a').exceeded, false);
  assert.equal(limiter.hit('b').exceeded, false);
  assert.equal(limiter.hit('a').exceeded, true);
});

test('limiter melupakan kejadian di luar jendela waktu', async () => {
  const limiter = new SlidingWindowLimiter({ max: 1, windowMs: 60 });

  assert.equal(limiter.hit('a').exceeded, false);
  assert.equal(limiter.hit('a').exceeded, true);

  await new Promise((resolve) => setTimeout(resolve, 90));

  assert.equal(limiter.hit('a').exceeded, false);
});

test('reset menghapus riwayat sebuah kunci', () => {
  const limiter = new SlidingWindowLimiter({ max: 1, windowMs: 1000 });

  limiter.hit('a');
  assert.equal(limiter.hit('a').exceeded, true);

  limiter.reset('a');
  assert.equal(limiter.hit('a').exceeded, false);
});
