import crypto from 'node:crypto';
import {readFileSync} from 'node:fs';
import {mkdir, readdir, rm} from 'node:fs/promises';
import http from 'node:http';
import {join} from 'node:path';
import makeWASocket, {
  Browsers,
  DisconnectReason,
  downloadContentFromMessage,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from 'baileys';
import pino from 'pino';
import QRCode from 'qrcode';

const loadEnvFile = () => {
  try {
    const content = readFileSync(new URL('../.env', import.meta.url), 'utf8');
    for (const line of content.split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)\s*$/i);
      if (!match || match[1] in process.env) continue;
      process.env[match[1]] = match[2].replace(/^(["'])(.*)\1$/, '$2');
    }
  } catch {}
};

loadEnvFile();

const port = Number.parseInt(process.env.PORT ?? '3000', 10);
const bindHost = process.env.WHATSAPP_BIND_HOST ?? '0.0.0.0';
const apiToken = process.env.WHATSAPP_INTERNAL_TOKEN ?? '';
const authDirectory = process.env.WHATSAPP_AUTH_DIR ?? '/data/auth';
const laravelWebhookUrl = process.env.WHATSAPP_WEBHOOK_URL ?? '';
const documentHosts = new Set((process.env.WHATSAPP_DOCUMENT_HOSTS ?? 'younzdigitalcenter.my.id,www.younzdigitalcenter.my.id')
  .split(',')
  .map((host) => host.trim().toLowerCase())
  .filter(Boolean));
const reconnectBaseDelay = 2_000;
const logger = pino({level: process.env.LOG_LEVEL ?? 'info'});

if (apiToken.length < 16) {
  throw new Error('WHATSAPP_INTERNAL_TOKEN must contain at least 16 characters.');
}

let socket;
let reconnectTimer;
let reconnectAttempts = 0;
let connecting = false;
let stopping = false;
let connectionState = 'starting';
let qrDataUrl = null;
let connectedJid = null;
let lastError = null;

const json = (response, status, body) => {
  const payload = JSON.stringify(body);
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(payload),
    'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff',
  });
  response.end(payload);
};

const authorized = (request) => {
  const header = request.headers.authorization ?? '';
  const supplied = header.startsWith('Bearer ') ? header.slice(7) : '';
  if (supplied.length !== apiToken.length) return false;
  return crypto.timingSafeEqual(Buffer.from(supplied), Buffer.from(apiToken));
};

const readJson = async (request) => {
  const chunks = [];
  let length = 0;
  for await (const chunk of request) {
    length += chunk.length;
    if (length > 64 * 1024) throw new Error('Payload too large.');
    chunks.push(chunk);
  }
  return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
};

const readResponseBuffer = async (response, maxBytes) => {
  if (!response.body) return Buffer.alloc(0);
  const chunks = [];
  let length = 0;
  for await (const chunk of response.body) {
    length += chunk.length;
    if (length > maxBytes) throw new Error('Dokumen terlalu besar.');
    chunks.push(chunk);
  }
  return Buffer.concat(chunks);
};

const statusPayload = () => ({
  state: connectionState,
  connected: connectionState === 'connected',
  qr: qrDataUrl,
  account: connectedJid?.split(':')[0]?.split('@')[0] ?? null,
  lastError,
});

const resetAuthState = async () => {
  await mkdir(authDirectory, {recursive: true});
  const entries = await readdir(authDirectory);
  await Promise.all(entries.map((entry) => rm(join(authDirectory, entry), {recursive: true, force: true})));
};

const scheduleReconnect = () => {
  if (stopping || reconnectTimer) return;
  const delay = Math.min(30_000, reconnectBaseDelay * (2 ** reconnectAttempts)) + Math.floor(Math.random() * 750);
  reconnectAttempts += 1;
  connectionState = 'reconnecting';
  reconnectTimer = setTimeout(() => {
    reconnectTimer = undefined;
    void connect();
  }, delay);
};

const connect = async () => {
  if (connecting || stopping) return;
  connecting = true;
  qrDataUrl = null;
  lastError = null;
  connectionState = 'connecting';

  try {
    const {state, saveCreds} = await useMultiFileAuthState(authDirectory);
    const {version} = await fetchLatestBaileysVersion();
    const nextSocket = makeWASocket({
      auth: state,
      version,
      browser: Browsers.ubuntu('Younz Digital Center'),
      logger: logger.child({component: 'baileys'}, {level: 'warn'}),
      markOnlineOnConnect: false,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
    });
    socket = nextSocket;

    nextSocket.ev.on('creds.update', saveCreds);
    nextSocket.ev.on('messages.upsert', ({type, messages}) => {
      if (type !== 'notify') return;
      for (const message of messages) void publishInboundMessage(message);
    });
    nextSocket.ev.on('connection.update', async ({connection, lastDisconnect, qr}) => {
      if (socket !== nextSocket) return;

      if (qr) {
        connectionState = 'qr';
        qrDataUrl = await QRCode.toDataURL(qr, {margin: 1, width: 360});
      }

      if (connection === 'open') {
        connectionState = 'connected';
        connectedJid = nextSocket.user?.id ?? null;
        qrDataUrl = null;
        reconnectAttempts = 0;
        lastError = null;
        logger.info({account: connectedJid?.split(':')[0]}, 'WhatsApp connected');
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = statusCode === DisconnectReason.loggedOut;
        connectedJid = null;
        qrDataUrl = null;

        if (loggedOut) {
          socket = undefined;
          connectionState = 'reconnecting';
          lastError = 'Sesi WhatsApp telah keluar. Menyiapkan QR baru.';
          try {
            await resetAuthState();
            reconnectAttempts = 0;
            scheduleReconnect();
          } catch (error) {
            connectionState = 'error';
            lastError = 'Sesi lama gagal dibersihkan. Coba hubungkan ulang.';
            logger.error({err: error}, 'WhatsApp auth reset failed');
          }
          return;
        }

        lastError = 'Koneksi WhatsApp terputus.';
        connectionState = 'disconnected';
        scheduleReconnect();
      }
    });
  } catch (error) {
    lastError = error instanceof Error ? error.message : 'Gagal menghubungkan WhatsApp.';
    connectionState = 'error';
    logger.error({err: error}, 'WhatsApp connection failed');
    scheduleReconnect();
  } finally {
    connecting = false;
  }
};

const normalizeJid = (phone) => {
  let digits = String(phone ?? '').replace(/\D/g, '');
  if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
  if (!digits.startsWith('62') || digits.length < 10 || digits.length > 15) return null;
  return `${digits}@s.whatsapp.net`;
};

const extractNativeFlowCommand = (message) => {
  const paramsJson = message?.interactiveResponseMessage?.nativeFlowResponseMessage?.paramsJson;
  if (typeof paramsJson !== 'string') return null;
  try {
    const params = JSON.parse(paramsJson);
    return typeof params.id === 'string' ? params.id : null;
  } catch {
    return null;
  }
};

const extractText = (message) => message?.conversation
  ?? message?.extendedTextMessage?.text
  ?? message?.imageMessage?.caption
  ?? message?.videoMessage?.caption
  ?? message?.buttonsResponseMessage?.selectedButtonId
  ?? message?.templateButtonReplyMessage?.selectedId
  ?? message?.listResponseMessage?.singleSelectReply?.selectedRowId
  ?? extractNativeFlowCommand(message)
  ?? null;

const phoneJid = (...candidates) => candidates.find((jid) => typeof jid === 'string' && jid.endsWith('@s.whatsapp.net'));

const publishInboundMessage = async (message) => {
  if (!laravelWebhookUrl || message.key.fromMe || !message.key.remoteJid || !message.key.id) return;
  const text = extractText(message.message)?.trim() ?? '';
  const image = message.message?.imageMessage;
  if (image?.viewOnce) return;
  if (!text && !image) return;

  // Baileys 7 can use a private @lid identifier as the primary JID. Laravel
  // needs the alternate PN JID so it can validate and reply to the phone number.
  const chatJid = phoneJid(message.key.remoteJid, message.key.remoteJidAlt);
  const senderJid = phoneJid(message.key.participant, message.key.participantAlt, chatJid);
  if (!chatJid || !senderJid) {
    logger.warn({addressingMode: message.key.addressingMode}, 'Inbound direct message has no phone JID');
    return;
  }

  try {
    const fields = {
      messageId: message.key.id, chatJid, senderJid, text,
      timestamp: Number(message.messageTimestamp ?? Math.floor(Date.now() / 1000)),
    };
    let body = JSON.stringify(fields);
    const headers = {Authorization: `Bearer ${apiToken}`, 'Content-Type': 'application/json'};
    if (image) {
      const maxBytes = 5 * 1024 * 1024;
      if (Number(image.fileLength ?? 0) > maxBytes || !['image/jpeg', 'image/png', 'image/webp'].includes(image.mimetype)) return;
      const stream = await downloadContentFromMessage(image, 'image');
      const timer = setTimeout(() => stream.destroy(new Error('Image download timed out')), 15_000);
      const chunks = [];
      let size = 0;
      try {
        for await (const chunk of stream) {
          size += chunk.length;
          if (size > maxBytes) throw new Error('Image exceeds size limit');
          chunks.push(chunk);
        }
      } finally {
        clearTimeout(timer);
        stream.destroy();
      }
      const form = new FormData();
      for (const [key, value] of Object.entries(fields)) form.append(key, String(value));
      const extension = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp'}[image.mimetype];
      form.append('image', new Blob(chunks, {type: image.mimetype}), `image.${extension}`);
      body = form;
      delete headers['Content-Type'];
    }
    const response = await fetch(laravelWebhookUrl, {
      method: 'POST',
      redirect: 'manual',
      headers,
      body,
      signal: AbortSignal.timeout(10_000),
    });
    if (!response.ok) {
      logger.warn({status: response.status, location: response.headers.get('location')}, 'Inbound webhook rejected');
    }
  } catch (error) {
    logger.error({err: error}, 'Inbound webhook failed');
  }
};

const sendInteractiveMenu = async (jid, payload) => {
  const title = typeof payload.title === 'string' ? payload.title.trim() : '';
  const body = typeof payload.body === 'string' ? payload.body.trim() : '';
  const footer = typeof payload.footer === 'string' ? payload.footer.trim() : '';
  const buttons = Array.isArray(payload.buttons) ? payload.buttons : [];
  const validButtons = buttons.length >= 1 && buttons.length <= 10 && buttons.every((button) => (
    typeof button?.id === 'string'
    && /^\/[a-z0-9_-]{1,30}$/i.test(button.id)
    && typeof button?.label === 'string'
    && button.label.trim().length >= 1
    && button.label.trim().length <= 24
    && (button.description === undefined || (typeof button.description === 'string' && button.description.trim().length <= 72))
  ));

  if (!title || title.length > 60 || !body || body.length > 1024 || footer.length > 60 || !validButtons) {
    throw new Error('Isi menu tidak valid.');
  }

  const lines = [`*${title}*`, '', body, ''];
  buttons.forEach((button, index) => {
    lines.push(`*${index + 1}. ${button.label.trim()}*`);
    if (typeof button.description === 'string' && button.description.trim()) {
      lines.push(button.description.trim());
    }
    lines.push('');
  });
  lines.push('Balas dengan angka pilihan, misalnya *1*.', footer);

  const result = await socket.sendMessage(jid, {text: lines.join('\n'), linkPreview: null});
  return result?.key?.id ?? null;
};

const server = http.createServer(async (request, response) => {
  const url = new URL(request.url ?? '/', 'http://gateway.internal');

  if (request.method === 'GET' && url.pathname === '/health') {
    return json(response, 200, {ok: true, state: connectionState});
  }

  if (!authorized(request)) return json(response, 401, {message: 'Unauthorized.'});

  try {
    if (request.method === 'GET' && url.pathname === '/v1/status') {
      return json(response, 200, statusPayload());
    }

    if (request.method === 'POST' && url.pathname === '/v1/reconnect') {
      const needsFreshSession = connectionState === 'logged_out';
      clearTimeout(reconnectTimer);
      reconnectTimer = undefined;
      socket?.end(new Error('Manual reconnect'));
      socket = undefined;
      if (needsFreshSession) await resetAuthState();
      reconnectAttempts = 0;
      connectionState = 'reconnecting';
      await connect();
      return json(response, 202, statusPayload());
    }

    if (request.method === 'POST' && url.pathname === '/v1/logout') {
      if (socket) await socket.logout();
      connectionState = 'logged_out';
      connectedJid = null;
      qrDataUrl = null;
      return json(response, 200, statusPayload());
    }

    if (request.method === 'POST' && url.pathname === '/v1/menu') {
      if (connectionState !== 'connected' || !socket) {
        return json(response, 503, {message: 'WhatsApp belum terhubung.'});
      }
      const payload = await readJson(request);
      const jid = normalizeJid(payload.to);
      if (!jid) return json(response, 422, {message: 'Nomor WhatsApp tidak valid.'});
      const messageId = await sendInteractiveMenu(jid, payload);
      return json(response, 202, {accepted: true, messageId});
    }

    if (request.method === 'POST' && url.pathname === '/v1/messages') {
      if (connectionState !== 'connected' || !socket) {
        return json(response, 503, {message: 'WhatsApp belum terhubung.'});
      }
      const payload = await readJson(request);
      const jid = normalizeJid(payload.to);
      const text = typeof payload.text === 'string' ? payload.text.trim() : '';
      if (!jid || !text || text.length > 4096) {
        return json(response, 422, {message: 'Nomor atau isi pesan tidak valid.'});
      }
      const result = await socket.sendMessage(jid, {text});
      return json(response, 202, {accepted: true, messageId: result?.key?.id ?? null});
    }

    if (request.method === 'POST' && url.pathname === '/v1/documents') {
      if (connectionState !== 'connected' || !socket) {
        return json(response, 503, {message: 'WhatsApp belum terhubung.'});
      }
      const payload = await readJson(request);
      const jid = normalizeJid(payload.to);
      const documentUrl = typeof payload.url === 'string' ? payload.url.trim() : '';
      const filename = typeof payload.filename === 'string' ? payload.filename.trim() : 'dokumen.pdf';
      const caption = typeof payload.caption === 'string' ? payload.caption.trim() : '';
      let parsedUrl;
      try {
        parsedUrl = new URL(documentUrl);
      } catch {
        parsedUrl = null;
      }
       if (!jid || !parsedUrl || parsedUrl.protocol !== 'https:'
        || !documentHosts.has(parsedUrl.hostname.toLowerCase())
        || !/^\/topup\/status\/[^/]+\/struk\.pdf$/i.test(parsedUrl.pathname)
        || !parsedUrl.searchParams.has('expires')
        || !parsedUrl.searchParams.has('signature')
        || !/^[^\\/\r\n]{1,120}\.pdf$/i.test(filename)
        || caption.length > 1024) {
        return json(response, 422, {message: 'Nomor, URL, nama, atau caption dokumen tidak valid.'});
      }

      const documentResponse = await fetch(parsedUrl, {
        redirect: 'error',
        signal: AbortSignal.timeout(30_000),
      });
      if (!documentResponse.ok) {
        throw new Error(`Dokumen gagal diambil (HTTP ${documentResponse.status}).`);
      }
      const contentType = (documentResponse.headers.get('content-type') ?? '').toLowerCase();
      const document = await readResponseBuffer(documentResponse, 10 * 1024 * 1024);
      if (!contentType.includes('application/pdf') || document.subarray(0, 5).toString() !== '%PDF-') {
        throw new Error('URL tidak mengembalikan PDF yang valid.');
      }

      const result = await socket.sendMessage(jid, {
        document,
        mimetype: 'application/pdf',
        fileName: filename,
        ...(caption ? {caption} : {}),
      });
      return json(response, 202, {accepted: true, messageId: result?.key?.id ?? null});
    }

    return json(response, 404, {message: 'Not found.'});
  } catch (error) {
    logger.error({err: error, path: url.pathname}, 'Gateway request failed');
    return json(response, 500, {message: 'Gateway gagal memproses permintaan.'});
  }
});

const shutdown = () => {
  stopping = true;
  clearTimeout(reconnectTimer);
  socket?.end(new Error('Service shutting down'));
  server.close(() => process.exit(0));
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

server.listen(port, bindHost, () => {
  logger.info({port}, 'WhatsApp gateway listening');
  void connect();
});
