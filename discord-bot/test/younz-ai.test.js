import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

process.env.OPENAI_COMPATIBLE_URL = 'http://ai.test/v1';
process.env.OPENAI_COMPATIBLE_API_KEY = 'test-api-key';
process.env.OPENAI_COMPATIBLE_MODEL = 'test-model';
process.env.AI_HISTORY_MESSAGES = '4';
process.env.AI_MAX_OUTPUT_TOKENS = '500';

const {
  appendHistory,
  askYounzAi,
  clearHistory,
  describeYounzAiError,
  getHistory,
  isYounzAiEnabled,
} = await import('../src/services/younz-ai.js');
const { HttpError } = await import('../src/core/http.js');

const originalFetch = globalThis.fetch;

function mockFetch(response) {
  globalThis.fetch = async () => ({
    ok: response.ok ?? true,
    status: response.status ?? 200,
    text: async () => JSON.stringify(response.body ?? {}),
  });
}

function mockCompletion(content, extra = {}) {
  mockFetch({
    body: {
      choices: [{ message: { role: 'assistant', content } }],
      usage: { prompt_tokens: 10, completion_tokens: 5 },
      model: 'test-model',
      ...extra,
    },
  });
}

afterEach(() => {
  globalThis.fetch = originalFetch;
});

test('isYounzAiEnabled bernilai true saat konfigurasi terisi', () => {
  assert.equal(isYounzAiEnabled(), true);
});

test('askYounzAi mengirim permintaan yang benar dan mem-parsing jawaban', async () => {
  let captured;

  globalThis.fetch = async (url, options) => {
    captured = { url, options };
    return {
      ok: true,
      status: 200,
      text: async () => JSON.stringify({
        choices: [{ message: { role: 'assistant', content: 'Halo! Ada yang bisa saya bantu?' } }],
        usage: { prompt_tokens: 12, completion_tokens: 7 },
        model: 'test-model',
      }),
    };
  };

  const result = await askYounzAi('Halo', [{ role: 'user', content: 'Sebelumnya' }]);

  assert.equal(result.answer, 'Halo! Ada yang bisa saya bantu?');
  assert.equal(result.inputTokens, 12);
  assert.equal(result.outputTokens, 7);
  assert.equal(result.model, 'test-model');
  assert.equal(captured.url, 'http://ai.test/v1/chat/completions');
  assert.equal(captured.options.method, 'POST');
  assert.equal(captured.options.headers.Authorization, 'Bearer test-api-key');
  assert.match(captured.options.body, /test-model/);
  assert.match(captured.options.body, /Sebelumnya/);
});

test('askYounzAi menolak jawaban kosong', async () => {
  mockCompletion('   ');

  await assert.rejects(() => askYounzAi('Halo'), /tidak mengembalikan jawaban/);
});

test('askYounzAi mengangkat HttpError untuk status 401', async () => {
  mockFetch({ ok: false, status: 401, body: { message: 'unauthorized' } });

  await assert.rejects(
    () => askYounzAi('Halo'),
    (error) => error instanceof HttpError && error.status === 401,
  );
});

test('describeYounzAiError menjelaskan error API dengan ramah', () => {
  assert.equal(
    describeYounzAiError(new HttpError('auth', 401)),
    'Kunci API ditolak. Periksa OPENAI_COMPATIBLE_API_KEY.',
  );
  assert.equal(
    describeYounzAiError(new HttpError('rate', 429)),
    'Kuota penyedia AI sedang penuh. Coba lagi nanti.',
  );
  assert.equal(
    describeYounzAiError(new Error('jaringan')),
    'Younz AI tidak dapat dihubungi saat ini. Coba lagi beberapa saat.',
  );
});

test('appendHistory menyimpan giliran dan memangkas ke batas maksimal', () => {
  clearHistory('user-1');

  for (let i = 0; i < 5; i += 1) {
    appendHistory('user-1', `tanya-${i}`, `jawab-${i}`);
  }

  const history = getHistory('user-1');

  assert.equal(history.length, 4);
  assert.equal(history[0].role, 'user');
  assert.equal(history[0].content, 'tanya-3');
  assert.equal(history[1].role, 'assistant');
  assert.equal(history[1].content, 'jawab-3');
  assert.equal(history[history.length - 1].content, 'jawab-4');
});

test('appendHistory tanpa jawaban tetap menyimpan pertanyaan sebagai konteks', () => {
  clearHistory('user-2');

  appendHistory('user-2', 'tanya-saja');
  appendHistory('user-2', 'tanya-lain', 'jawaban');

  const history = getHistory('user-2');

  assert.equal(history.length, 3);
  assert.equal(history[0].content, 'tanya-saja');
  assert.equal(history[1].content, 'tanya-lain');
  assert.equal(history[2].content, 'jawaban');
});

test('clearHistory menghapus riwayat pengguna', () => {
  appendHistory('user-3', 'tanya', 'jawab');
  clearHistory('user-3');

  assert.deepEqual(getHistory('user-3'), []);
});
