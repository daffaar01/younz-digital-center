import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';

const WEBHOOK_TOKEN = 'webhook-token-for-contract-test-12345678';
const CHANNEL_ID = '111111111111111111';
const SUCCESS_CHANNEL_ID = '222222222222222222';
const FAILED_CHANNEL_ID = '333333333333333333';
const ERROR_CHANNEL_ID = '444444444444444444';

process.env.WEBHOOK_TOKEN = WEBHOOK_TOKEN;
process.env.WEBHOOK_HOST = '127.0.0.1';
process.env.SERVER_PORT = '0';
process.env.WEBHOOK_MAX_SKEW_SECONDS = '300';
process.env.CHANNEL_NOTIFICATION_ID = CHANNEL_ID;
process.env.CHANNEL_TRANSACTION_SUCCESS_ID = SUCCESS_CHANNEL_ID;
process.env.CHANNEL_TRANSACTION_FAILED_ID = FAILED_CHANNEL_ID;
process.env.CHANNEL_BOT_ERRORS_ID = ERROR_CHANNEL_ID;

const { createWebhookServer } = await import('../src/services/webhook-server.js');
const { createSignature } = await import('../src/services/webhook-signature.js');

function fakeClient({ ready = true } = {}) {
  const sent = [];

  function makeChannel(id) {
    return {
      id,
      isTextBased: () => true,
      send: async (message) => {
        sent.push({ channelId: id, message });
        return { id: `message-${id}` };
      },
    };
  }

  const cache = new Map(
    [CHANNEL_ID, SUCCESS_CHANNEL_ID, FAILED_CHANNEL_ID, ERROR_CHANNEL_ID].map((id) => [id, makeChannel(id)]),
  );

  return {
    isReady: () => ready,
    sent,
    channels: {
      cache,
      fetch: async () => null,
    },
  };
}

let server;
let baseUrl;
let client;

before(async () => {
  client = fakeClient();
  server = await createWebhookServer(client, { host: '127.0.0.1', port: 0 });
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});

after(async () => {
  await new Promise((resolve) => server.close(resolve));
});

function signedHeaders({ token = WEBHOOK_TOKEN, timestamp, body, signature } = {}) {
  const headers = {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };

  if (timestamp !== undefined && signature === undefined) {
    signature = createSignature(token, timestamp, body);
  }

  if (timestamp !== undefined) headers['X-Younz-Timestamp'] = timestamp;
  if (signature !== undefined) headers['X-Younz-Signature'] = signature;

  return headers;
}

async function postNotify(body = {}, options = {}) {
  const payload = typeof body === 'string' ? body : JSON.stringify(body);
  const timestamp = options.timestamp ?? String(Math.floor(Date.now() / 1000));

  return fetch(`${baseUrl}/notify`, {
    method: 'POST',
    headers: signedHeaders({ timestamp, body: payload, signature: options.signature }),
    body: payload,
  });
}

test('GET /health menandai bot siap', async () => {
  const response = await fetch(`${baseUrl}/health`);

  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), {
    status: 'ok',
    service: 'younz-discord-bot',
    ready: true,
  });
});

test('GET /health menandai bot belum siap dengan 503', async () => {
  const slowServer = await createWebhookServer(fakeClient({ ready: false }), { host: '127.0.0.1', port: 0 });

  try {
    const response = await fetch(`http://127.0.0.1:${slowServer.address().port}/health`);

    assert.equal(response.status, 503);
    assert.deepEqual(await response.json(), {
      status: 'starting',
      service: 'younz-discord-bot',
      ready: false,
    });
  } finally {
    await new Promise((resolve) => slowServer.close(resolve));
  }
});

test('POST /notify dengan bearer dan HMAC valid mengirim notifikasi', async () => {
  const payload = {
    event: 'topup.paid',
    title: 'Top Up Berhasil',
    message: 'Pesanan telah dibayar.',
    fields: [{ name: 'Produk', value: 'Pulsa', inline: true }],
    amount: 25000,
    reference: 'ORDER-123',
    mention_staff: true,
  };

  const response = await postNotify(payload);

  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), { status: 'sent', message_id: `message-${SUCCESS_CHANNEL_ID}` });
});

test('POST /notify mengarahkan topup.failed ke channel transaction-failed', async () => {
  const payload = { event: 'topup.failed', title: 'Top Up Gagal' };
  const response = await postNotify(payload);

  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), { status: 'sent', message_id: `message-${FAILED_CHANNEL_ID}` });
});

test('POST /notify mengarahkan system.alert ke channel bot-errors', async () => {
  const payload = { event: 'system.alert', title: 'Alert System' };
  const response = await postNotify(payload);

  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), { status: 'sent', message_id: `message-${ERROR_CHANNEL_ID}` });
});

test('POST /notify menolak bearer yang salah dengan 401', async () => {
  const payload = { event: 'system.alert', title: 'Peringatan' };
  const body = JSON.stringify(payload);
  const timestamp = String(Math.floor(Date.now() / 1000));

  const response = await fetch(`${baseUrl}/notify`, {
    method: 'POST',
    headers: signedHeaders({ token: 'salah-salah-salah-salah-salah-12345678', timestamp, body }),
    body,
  });

  assert.equal(response.status, 401);
});

test('POST /notify menolak tanda tangan yang dimodifikasi dengan 401', async () => {
  const payload = { event: 'system.alert', title: 'Peringatan' };
  const timestamp = String(Math.floor(Date.now() / 1000));

  const response = await postNotify(payload, { timestamp, signature: 'f'.repeat(64) });

  assert.equal(response.status, 401);
});

test('POST /notify menolak tanda tangan kedaluwarsa dengan 401', async () => {
  const payload = { event: 'system.alert', title: 'Peringatan' };

  const response = await postNotify(payload, { timestamp: '1500000000' });

  assert.equal(response.status, 401);
});

test('POST /notify menolak payload tanpa event, title, atau message dengan 422', async () => {
  const response = await postNotify({ amount: 25000 });

  assert.equal(response.status, 422);
});

test('POST /notify menolak payload non-JSON dengan 400', async () => {
  const timestamp = String(Math.floor(Date.now() / 1000));
  const body = '{bukan json';

  const response = await fetch(`${baseUrl}/notify`, {
    method: 'POST',
    headers: signedHeaders({ timestamp, body }),
    body,
  });

  assert.equal(response.status, 400);
});

test('POST /notify menolak payload melebihi 32 KiB dengan 400', async () => {
  const body = JSON.stringify({ event: 'system.alert', title: 'Besar', message: 'x'.repeat(33 * 1024) });

  const response = await postNotify(body);

  assert.equal(response.status, 400);
});

test('Rute yang tidak dikenal ditolak dengan 404', async () => {
  const response = await fetch(`${baseUrl}/lainnya`);

  assert.equal(response.status, 404);
});
