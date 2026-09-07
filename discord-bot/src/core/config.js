/**
 * Konfigurasi terpusat.
 *
 * Setiap fitur dinyalakan hanya bila variabel lingkungan yang dibutuhkan
 * tersedia, sehingga bot tetap dapat berjalan meski Anda baru mengisi
 * sebagian konfigurasi.
 */

import { loadEnv, projectRoot } from './env.js';

loadEnv();

function text(key, fallback = '') {
  const value = process.env[key];

  return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function number(key, fallback) {
  const parsed = Number.parseInt(text(key), 10);

  return Number.isFinite(parsed) ? parsed : fallback;
}

function requireAll(...keys) {
  return keys.every((key) => text(key) !== '');
}

const discordToken = text('DISCORD_TOKEN');
const clientId = text('DISCORD_CLIENT_ID');
const guildId = text('DISCORD_GUILD_ID');

export const config = {
  projectRoot,
  timezone: text('TIMEZONE', 'Asia/Jakarta'),
  logLevel: text('LOG_LEVEL', 'info'),

  discord: {
    token: discordToken,
    clientId,
    guildId,
  },

  channels: {
    notification: text('CHANNEL_NOTIFICATION_ID'),
    log: text('CHANNEL_LOG_ID'),
    welcome: text('CHANNEL_WELCOME_ID'),
    ticketCategory: text('CATEGORY_TICKET_ID'),
    younzInformation: text('CHANNEL_YOUNZ_INFORMATION_ID'),
    transactionSuccess: text('CHANNEL_TRANSACTION_SUCCESS_ID'),
    transactionFailed: text('CHANNEL_TRANSACTION_FAILED_ID'),
    botErrors: text('CHANNEL_BOT_ERRORS_ID'),
    promoUpdate: text('CHANNEL_PROMO_UPDATE_ID'),
    daftarLayanan: text('CHANNEL_DAFTAR_LAYANAN_ID'),
    orderLayanan: text('CHANNEL_ORDER_LAYANAN_ID'),
    customerSupport: text('CHANNEL_CUSTOMER_SUPPORT_ID'),
    testimoni: text('CHANNEL_TESTIMONI_ID'),
  },

  roles: {
    staff: text('ROLE_STAFF_ID'),
    auto: text('ROLE_AUTO_ID'),
  },

  younz: {
    enabled: requireAll('YOUNZ_API_URL', 'YOUNZ_API_TOKEN'),
    url: text('YOUNZ_API_URL', 'https://admin.younzdigitalcenter.my.id').replace(/\/+$/, ''),
    // Base URL backend Laravel yang dapat dijangkau bot secara langsung
    // (signed URL top up menunjuk ke domain publik yang dilayani Next.js,
    // sehingga perlu di-rewrite ke backend untuk mendapat JSON).
    backendUrl: text('YOUNZ_BACKEND_URL', text('YOUNZ_API_URL', 'https://admin.younzdigitalcenter.my.id')).replace(/\/+$/, ''),
    token: text('YOUNZ_API_TOKEN'),
  },

  ai: {
    enabled: requireAll('OPENAI_COMPATIBLE_URL', 'OPENAI_COMPATIBLE_API_KEY'),
    url: text('OPENAI_COMPATIBLE_URL').replace(/\/+$/, ''),
    apiKey: text('OPENAI_COMPATIBLE_API_KEY'),
    model: text('OPENAI_COMPATIBLE_MODEL', 'cx/gpt-5.6-sol'),
    timeoutMs: number('OPENAI_COMPATIBLE_TIMEOUT', 120) * 1000,
    historyMessages: number('AI_HISTORY_MESSAGES', 12),
    maxMessageCharacters: number('AI_MESSAGE_CHARACTERS', 3000),
    maxOutputTokens: number('AI_MAX_OUTPUT_TOKENS', 1200),
    maxRequestsPerWindow: number('AI_MAX_REQUESTS_PER_WINDOW', 8),
    requestWindowMs: number('AI_REQUEST_WINDOW_MS', 60_000),
  },

  webhook: {
    enabled: text('WEBHOOK_TOKEN').length >= 32,
    token: text('WEBHOOK_TOKEN'),
    host: text('WEBHOOK_HOST', '0.0.0.0'),
    port: number('SERVER_PORT', number('WEBHOOK_PORT', number('PORT', 3200))),
    maxSkewSeconds: number('WEBHOOK_MAX_SKEW_SECONDS', 300),
  },

  antispam: {
    maxMessages: number('ANTISPAM_MAX_MESSAGES', 6),
    windowMs: number('ANTISPAM_WINDOW_MS', 7000),
    timeoutMinutes: number('ANTISPAM_TIMEOUT_MINUTES', 10),
  },
};

export function assertCoreConfig() {
  const missing = [];

  if (config.discord.token === '') missing.push('DISCORD_TOKEN');
  if (config.discord.clientId === '') missing.push('DISCORD_CLIENT_ID');

  if (missing.length > 0) {
    throw new Error(
      `Konfigurasi wajib belum lengkap: ${missing.join(', ')}. Salin .env.example menjadi .env lalu isi nilainya.`,
    );
  }
}

export function featureSummary() {
  return {
    younz: config.younz.enabled,
    younzAi: config.ai.enabled,
    webhook: config.webhook.enabled,
    tickets: config.channels.ticketCategory !== '',
    welcome: config.channels.welcome !== '',
    moderationLog: config.channels.log !== '',
  };
}
