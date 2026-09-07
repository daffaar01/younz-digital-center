/**
 * Penerima webhook internal dari Laravel.
 *
 * Server mendengarkan pada allocation publik hosting. Setiap permintaan
 * harus menyertakan bearer token dan tanda tangan HMAC bertimestamp agar
 * payload tidak dapat dipalsukan atau diputar ulang. Payload dibatasi
 * ukurannya untuk mencegah penyalahgunaan memori.
 */

import crypto from 'node:crypto';
import http from 'node:http';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, formatRupiah, truncate } from '../core/format.js';
import { childLogger, logger } from '../core/logger.js';
import { SIGNATURE_HEADER, TIMESTAMP_HEADER, verifySignature } from './webhook-signature.js';

const log = childLogger('webhook');

const MAX_BODY_BYTES = 32 * 1024;

const EVENT_STYLES = {
  'order.created': { title: 'Pesanan Baru', color: BRAND.info, emoji: '🧾' },
  'order.status_changed': { title: 'Status Pesanan Berubah', color: BRAND.primary, emoji: '🔄' },
  'topup.paid': { title: 'Top Up Dibayar', color: BRAND.success, emoji: '💰' },
  'topup.failed': { title: 'Top Up Gagal', color: BRAND.danger, emoji: '⚠️' },
  'approval.pending': { title: 'Approval Menunggu', color: BRAND.warning, emoji: '🔔' },
  'stock.low': { title: 'Stok Menipis', color: BRAND.warning, emoji: '📦' },
  'system.alert': { title: 'Peringatan Sistem', color: BRAND.danger, emoji: '🚨' },
  'testimonial.approved': { title: 'Testimoni Baru', color: BRAND.primary, emoji: '⭐' },
};

function sendJson(response, status, body) {
  const payload = JSON.stringify(body);

  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(payload),
    'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff',
  });

  response.end(payload);
}

function authorizedBearer(request) {
  const header = request.headers.authorization ?? '';
  const supplied = header.startsWith('Bearer ') ? header.slice(7) : '';
  const expected = config.webhook.token;

  if (supplied.length !== expected.length) {
    return false;
  }

  return crypto.timingSafeEqual(Buffer.from(supplied), Buffer.from(expected));
}

async function readBody(request) {
  const chunks = [];
  let length = 0;

  for await (const chunk of request) {
    length += chunk.length;

    if (length > MAX_BODY_BYTES) {
      throw new Error('Payload terlalu besar.');
    }

    chunks.push(chunk);
  }

  return Buffer.concat(chunks).toString('utf8');
}

function buildEmbed(payload) {
  const style = EVENT_STYLES[payload.event] ?? {
    title: 'Notifikasi',
    color: BRAND.neutral,
    emoji: 'ℹ️',
  };

  const fields = [];

  if (Array.isArray(payload.fields)) {
    for (const field of payload.fields.slice(0, 20)) {
      if (!field?.name || field?.value === undefined) {
        continue;
      }

      fields.push({
        name: truncate(String(field.name), 250),
        value: truncate(String(field.value), 1000),
        inline: Boolean(field.inline),
      });
    }
  }

  if (payload.amount !== undefined) {
    fields.push({ name: 'Nilai', value: formatRupiah(payload.amount), inline: true });
  }

  if (payload.reference) {
    fields.push({ name: 'Referensi', value: `\`${truncate(String(payload.reference), 100)}\``, inline: true });
  }

  return brandEmbed({
    title: `${style.emoji} ${payload.title ?? style.title}`,
    description: payload.message ? truncate(String(payload.message), 3000) : undefined,
    color: style.color,
    fields,
  });
}

async function resolveChannel(client, channelId, event) {
  if (channelId) {
    return fetchChannel(client, channelId);
  }

  const target = channelForEvent(event);

  if (target === '') {
    return null;
  }

  return fetchChannel(client, target);
}

function fetchChannel(client, channelId) {
  if (channelId === '') {
    return null;
  }

  const cached = client.channels.cache.get(channelId);

  if (cached?.isTextBased()) {
    return cached;
  }

  return client.channels.fetch(channelId).then(
    (fetched) => (fetched?.isTextBased() ? fetched : null),
    () => null,
  );
}

function channelForEvent(event) {
  const routes = {
    'order.created': [config.channels.transactionSuccess, config.channels.younzInformation],
    'order.status_changed': [config.channels.younzInformation],
    'topup.paid': [config.channels.transactionSuccess],
    'topup.failed': [config.channels.transactionFailed],
    'approval.pending': [config.channels.younzInformation],
    'stock.low': [config.channels.botErrors, config.channels.younzInformation],
    'system.alert': [config.channels.botErrors],
    'testimonial.approved': [config.channels.testimoni, config.channels.younzInformation],
  };

  const candidates = [...(routes[event] ?? []), config.channels.notification];

  return candidates.find((candidate) => candidate !== '' && candidate !== undefined) ?? '';
}

/**
 * Menangani satu permintaan webhook. Dipisah agar mudah diuji tanpa jaringan.
 */
export async function handleRequest(client, request, response) {
  if (request.method === 'GET' && request.url === '/health') {
    const ready = client.isReady();

    sendJson(response, ready ? 200 : 503, {
      status: ready ? 'ok' : 'starting',
      service: 'younz-discord-bot',
      ready,
    });
    return;
  }

  if (request.method !== 'POST' || request.url !== '/notify') {
    sendJson(response, 404, { message: 'Endpoint tidak ditemukan.' });
    return;
  }

  if (!authorizedBearer(request)) {
    log.warn({ ip: request.socket?.remoteAddress }, 'Webhook ditolak: token tidak sesuai');
    sendJson(response, 401, { message: 'Token tidak sesuai.' });
    return;
  }

  let rawBody;

  try {
    rawBody = await readBody(request);
  } catch (error) {
    sendJson(response, 400, { message: error.message });
    return;
  }

  const signature = verifySignature({
    secret: config.webhook.token,
    timestamp: request.headers[TIMESTAMP_HEADER],
    signature: request.headers[SIGNATURE_HEADER],
    body: rawBody,
    maxSkewSeconds: config.webhook.maxSkewSeconds,
  });

  if (!signature.ok) {
    log.warn({ ip: request.socket?.remoteAddress, reason: signature.reason }, 'Webhook ditolak: tanda tangan tidak valid');
    sendJson(response, 401, { message: 'Tanda tangan webhook tidak valid.' });
    return;
  }

  let payload;

  try {
    payload = rawBody.trim() === '' ? {} : JSON.parse(rawBody);
  } catch {
    sendJson(response, 400, { message: 'Payload bukan JSON yang valid.' });
    return;
  }

  if (!payload.event && !payload.title && !payload.message) {
    sendJson(response, 422, { message: 'Payload wajib memuat event, title, atau message.' });
    return;
  }

  if (!client.isReady()) {
    sendJson(response, 503, { message: 'Bot belum siap.' });
    return;
  }

  const channel = await resolveChannel(client, payload.channel_id, payload.event);

  if (!channel) {
    log.warn(
      {
        channel: payload.channel_id ?? channelForEvent(payload.event) ?? config.channels.notification,
        event: payload.event,
      },
      'Channel notifikasi tidak dapat diakses',
    );
    sendJson(response, 422, { message: 'Channel notifikasi tidak dapat diakses.' });
    return;
  }

  try {
    const mention = payload.mention_staff && config.roles.staff !== ''
      ? `<@&${config.roles.staff}>`
      : undefined;

    const message = await channel.send({
      content: mention,
      embeds: [buildEmbed(payload)],
    });

    log.info({ event: payload.event, channel: channel.id }, 'Notifikasi dikirim');

    sendJson(response, 200, { status: 'sent', message_id: message.id });
  } catch (error) {
    log.error({ err: error.message }, 'Gagal mengirim notifikasi');
    sendJson(response, 502, { message: 'Gagal mengirim notifikasi ke Discord.' });
  }
}

/**
 * Membuat dan mengikat server HTTP pada allocation publik. Resolve setelah
 * server benar-benar mendengarkan, reject bila bind gagal (mis. EADDRINUSE),
 * agar panel hosting menganggap proses gagal, bukan diam-diam berjalan.
 */
export function createWebhookServer(client, { host = config.webhook.host, port = config.webhook.port } = {}) {
  const server = http.createServer((request, response) => {
    handleRequest(client, request, response).catch((error) => {
      log.error({ err: error.message }, 'Kesalahan tak terduga saat memproses webhook');

      if (!response.headersSent) {
        sendJson(response, 500, { message: 'Kesalahan internal server webhook.' });
      } else {
        response.end();
      }
    });
  });

  return new Promise((resolve, reject) => {
    const onError = (error) => {
      log.error({ err: error.message, host, port }, 'Server webhook gagal mengikat allocation');
      reject(error);
    };

    server.once('error', onError);

    server.listen(port, host, () => {
      server.removeListener('error', onError);
      server.on('error', (error) => {
        log.error({ err: error.message }, 'Server webhook mengalami kesalahan runtime');
      });

      log.info({ host, port }, 'Webhook aktif pada allocation publik');
      resolve(server);
    });
  });
}

/**
 * Menjalankan server webhook. Mengembalikan fungsi penutup untuk shutdown,
 * atau null bila fitur webhook dimatikan.
 */
export async function startWebhookServer(client) {
  if (!config.webhook.enabled) {
    log.info('Webhook tidak aktif. Isi WEBHOOK_TOKEN minimal 32 karakter untuk mengaktifkan.');
    return null;
  }

  let server;

  try {
    server = await createWebhookServer(client);
  } catch (error) {
    logger.fatal({ err: error.message }, 'Tidak dapat memulai server webhook');
    throw error;
  }

  return () => new Promise((resolve) => server.close(resolve));
}

export const __testing = { handleRequest, authorizedBearer, buildEmbed, resolveChannel };
