import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

process.env.YOUNZ_API_URL = 'http://younz.test';
process.env.YOUNZ_BACKEND_URL = 'http://backend.test';
process.env.YOUNZ_API_TOKEN = 'test-token';

const { describeTrackError, trackOrder, trackTopupOrder } = await import('../src/services/younz.js');
const { HttpError } = await import('../src/core/http.js');

const originalFetch = globalThis.fetch;

afterEach(() => {
  globalThis.fetch = originalFetch;
});

function mockFetch({ status = 200, body = {} } = {}) {
  globalThis.fetch = async () => ({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  });
}

test('trackOrder mengirim permintaan yang benar dan mem-parsing data', async () => {
  let captured;

  globalThis.fetch = async (url, options) => {
    captured = { url, options };
    return {
      ok: true,
      status: 200,
      text: async () => JSON.stringify({
        data: {
          order_number: 'YD-001',
          service: 'Print A4',
          status: { code: 'masuk_antrean', label: 'Masuk Antrean' },
          estimated_price: 10000,
        },
      }),
    };
  };

  const order = await trackOrder('yd-001', '082123456789');

  assert.equal(captured.url, 'http://younz.test/api/v1/public/orders/track');
  assert.equal(captured.options.method, 'POST');
  assert.equal(JSON.parse(captured.options.body).order_number, 'yd-001');
  assert.equal(JSON.parse(captured.options.body).phone, '082123456789');
  assert.equal(order.order_number, 'YD-001');
  assert.equal(order.status.label, 'Masuk Antrean');
});

test('trackOrder mengembalikan null untuk payload tanpa data', async () => {
  mockFetch({ body: {} });

  const order = await trackOrder('yd-002', '08123456789');

  assert.equal(order, null);
});

test('trackOrder mengangkat HttpError 404 saat pesanan tidak ditemukan', async () => {
  mockFetch({ status: 404, body: { message: 'Pesanan tidak ditemukan.' } });

  await assert.rejects(
    () => trackOrder('yd-999', '08123456789'),
    (error) => error instanceof HttpError && error.status === 404,
  );
});

test('describeTrackError menjelaskan error umum dengan ramah', () => {
  assert.equal(
    describeTrackError(new HttpError('not found', 404)),
    'Pesanan tidak ditemukan. Periksa kembali nomor pesanan dan nomor WhatsApp.',
  );
  const message422 = describeTrackError(new HttpError('unprocessable', 422));
  assert.match(message422, /Data transaksi tidak cocok/);
  assert.match(message422, /Salindia \(copy\) data persis/);
  assert.equal(
    describeTrackError(new HttpError('rate', 429)),
    'Terlalu banyak permintaan. Coba lagi sebentar.',
  );
  assert.equal(
    describeTrackError(new Error('jaringan')),
    'Server Younz Digital Center tidak dapat dihubungi.',
  );
});

test('trackTopupOrder memvalidasi akses lalu mem-follow redirect URL untuk status', async () => {
  const calls = [];

  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });

    if (calls.length === 1) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({
          redirect_url: 'https://younzdigitalcenter.my.id/signed/abc123?expires=1&signature=x',
        }),
      };
    }

    return {
      ok: true,
      status: 200,
      text: async () => JSON.stringify({
        data: {
          order_number: 'TOP-20260807-0001',
          product_name: 'Telkomsel 35.000',
          payment_status: { code: 'paid', label: 'Paid' },
          fulfillment_status: { code: 'success', label: 'Success' },
          total_amount: 36000,
        },
      }),
    };
  };

  const order = await trackTopupOrder('TOP-20260807-0001', 'a@b.com', '0895405739302');

  assert.equal(calls.length, 2);
  assert.equal(calls[0].url, 'http://younz.test/api/v1/topup/access');
  assert.equal(calls[0].options.method, 'POST');
  assert.equal(JSON.parse(calls[0].options.body).customer_email, 'a@b.com');
  assert.equal(calls[0].options.body.includes('0895405739302'), true);
  assert.equal(calls[1].url, 'http://backend.test/signed/abc123?expires=1&signature=x');
  assert.equal(order.product_name, 'Telkomsel 35.000');
  assert.equal(order.fulfillment_status.code, 'success');
});

test('trackTopupOrder mengembalikan null tanpa redirect_url', async () => {
  mockFetch({ body: { message: 'oke' } });

  const order = await trackTopupOrder('TOP-20260807-0001', 'a@b.com', '0895405739302');

  assert.equal(order, null);
});
