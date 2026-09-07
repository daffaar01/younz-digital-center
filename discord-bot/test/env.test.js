import assert from 'node:assert/strict';
import { test } from 'node:test';

import { parseEnv } from '../src/core/env.js';

test('parseEnv membaca pasangan kunci dan nilai dasar', () => {
  const result = parseEnv('DISCORD_TOKEN=abc123\nWEBHOOK_PORT=3200');

  assert.equal(result.DISCORD_TOKEN, 'abc123');
  assert.equal(result.WEBHOOK_PORT, '3200');
});

test('parseEnv mengabaikan komentar dan baris kosong', () => {
  const result = parseEnv('# komentar\n\nA=1\n   # lain\nB=2');

  assert.deepEqual(result, { A: '1', B: '2' });
});

test('parseEnv melepas tanda kutip pembungkus', () => {
  const result = parseEnv(`A="nilai ganda"\nB='nilai tunggal'\nC=tanpa kutip`);

  assert.equal(result.A, 'nilai ganda');
  assert.equal(result.B, 'nilai tunggal');
  assert.equal(result.C, 'tanpa kutip');
});

test('parseEnv mempertahankan tanda sama dengan di dalam nilai', () => {
  const result = parseEnv('HASH=$argon2id$v=19$m=65536,t=4,p=1$abc$def');

  assert.equal(result.HASH, '$argon2id$v=19$m=65536,t=4,p=1$abc$def');
});

test('parseEnv menerima prefix export dan menolak kunci tidak valid', () => {
  const result = parseEnv('export A=1\n1B=2\nC-D=3\nE_F=4');

  assert.equal(result.A, '1');
  assert.equal(result['1B'], undefined);
  assert.equal(result['C-D'], undefined);
  assert.equal(result.E_F, '4');
});
