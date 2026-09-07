import crypto from 'node:crypto';

export const SIGNATURE_HEADER = 'x-younz-signature';
export const TIMESTAMP_HEADER = 'x-younz-timestamp';

export function createSignature(secret, timestamp, body) {
  return crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${body}`)
    .digest('hex');
}

export function verifySignature({ secret, timestamp, body, signature, maxSkewSeconds = 300, now = Date.now() }) {
  if (typeof timestamp !== 'string' || !/^\d{10}$/.test(timestamp)) {
    return { ok: false, reason: 'invalid-timestamp' };
  }

  if (typeof signature !== 'string' || !/^[a-f0-9]{64}$/i.test(signature)) {
    return { ok: false, reason: 'invalid-signature' };
  }

  const sentAt = Number(timestamp) * 1000;
  const maxSkewMs = Math.max(30, maxSkewSeconds) * 1000;

  if (!Number.isFinite(sentAt) || Math.abs(now - sentAt) > maxSkewMs) {
    return { ok: false, reason: 'expired' };
  }

  const expected = createSignature(secret, timestamp, body);

  if (expected.length !== signature.length) {
    return { ok: false, reason: 'mismatch' };
  }

  const matched = crypto.timingSafeEqual(
    Buffer.from(expected, 'hex'),
    Buffer.from(signature, 'hex'),
  );

  return { ok: matched, reason: matched ? null : 'mismatch' };
}
