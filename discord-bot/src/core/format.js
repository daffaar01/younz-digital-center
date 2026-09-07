import { EmbedBuilder } from 'discord.js';

import { config } from './config.js';

export const BRAND = {
  primary: 0x006c49,
  success: 0x10b981,
  warning: 0xf59e0b,
  danger: 0xdc2626,
  neutral: 0x64748b,
  info: 0x2563eb,
};

const numberFormat = new Intl.NumberFormat('id-ID');

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: config.timezone,
});

export function formatNumber(value) {
  const parsed = Number(value);

  return Number.isFinite(parsed) ? numberFormat.format(parsed) : '0';
}

export function formatRupiah(value) {
  return `Rp ${formatNumber(value)}`;
}

export function formatDateTime(value = new Date()) {
  const date = value instanceof Date ? value : new Date(value);

  return Number.isNaN(date.getTime()) ? '-' : dateTimeFormat.format(date);
}

export function formatBytes(bytes) {
  const parsed = Number(bytes);

  if (!Number.isFinite(parsed) || parsed <= 0) {
    return '0 MB';
  }

  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let value = parsed;
  let unit = 0;

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }

  return `${value.toFixed(unit >= 2 ? 1 : 0)} ${units[unit]}`;
}

export function formatUptime(milliseconds) {
  const total = Math.floor(Number(milliseconds) / 1000);

  if (!Number.isFinite(total) || total <= 0) {
    return '-';
  }

  const days = Math.floor(total / 86400);
  const hours = Math.floor((total % 86400) / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;

  const parts = [];
  if (days > 0) parts.push(`${days} hari`);
  if (hours > 0) parts.push(`${hours} jam`);
  if (minutes > 0) parts.push(`${minutes} menit`);
  if (parts.length === 0) parts.push(`${seconds} detik`);

  return parts.join(' ');
}

/**
 * Membuat embed dengan gaya visual yang konsisten untuk seluruh bot.
 */
export function brandEmbed({ title, description, color = BRAND.primary, fields = [], footer } = {}) {
  const embed = new EmbedBuilder().setColor(color).setTimestamp(new Date());

  if (title) embed.setTitle(title);
  if (description) embed.setDescription(description);
  if (fields.length > 0) embed.addFields(fields);

  embed.setFooter({ text: footer ?? 'Younz Digital Center' });

  return embed;
}

export function successEmbed(options) {
  return brandEmbed({ ...options, color: BRAND.success });
}

export function warningEmbed(options) {
  return brandEmbed({ ...options, color: BRAND.warning });
}

export function dangerEmbed(options) {
  return brandEmbed({ ...options, color: BRAND.danger });
}

export function infoEmbed(options) {
  return brandEmbed({ ...options, color: BRAND.info });
}

/**
 * Memotong teks agar tidak melewati batas panjang Discord.
 */
export function truncate(value, max = 1024) {
  const text = String(value ?? '');

  return text.length <= max ? text : `${text.slice(0, max - 3)}...`;
}
