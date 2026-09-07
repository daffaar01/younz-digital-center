import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  formatBytes,
  formatNumber,
  formatRupiah,
  formatUptime,
  truncate,
} from '../src/core/format.js';

test('formatNumber memakai pemisah ribuan Indonesia', () => {
  assert.equal(formatNumber(1500000), '1.500.000');
  assert.equal(formatNumber(0), '0');
  assert.equal(formatNumber('bukan angka'), '0');
});

test('formatRupiah menambahkan prefiks Rp', () => {
  assert.equal(formatRupiah(25000), 'Rp 25.000');
});

test('formatBytes menaikkan satuan sesuai besaran', () => {
  assert.equal(formatBytes(0), '0 MB');
  assert.equal(formatBytes(512), '512 B');
  assert.equal(formatBytes(1024 * 1024), '1.0 MB');
  assert.equal(formatBytes(1024 * 1024 * 1024 * 2), '2.0 GB');
});

test('formatUptime memberi rincian dalam bahasa Indonesia', () => {
  assert.equal(formatUptime(0), '-');
  assert.equal(formatUptime(45_000), '45 detik');
  assert.equal(formatUptime(3_600_000), '1 jam');
  assert.equal(formatUptime(90_061_000), '1 hari 1 jam 1 menit');
});

test('truncate memotong teks melewati batas', () => {
  assert.equal(truncate('abc', 10), 'abc');
  assert.equal(truncate('abcdefghij', 5), 'ab...');
  assert.equal(truncate(null, 5), '');
});
