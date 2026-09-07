/**
 * Logika tiket support.
 *
 * Setiap tiket dibuat sebagai channel privat di dalam kategori yang
 * dikonfigurasi. Hanya pembuat tiket dan role staff yang dapat melihatnya.
 */

import {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  ChannelType,
  PermissionFlagsBits,
} from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, formatDateTime } from '../core/format.js';
import { childLogger } from '../core/logger.js';
import { JsonStore } from './store.js';
import { SlidingWindowLimiter } from './rate-limiter.js';

const log = childLogger('tickets');

const store = new JsonStore('tickets.json', { counter: 0, open: {} });

const creationLimiter = new SlidingWindowLimiter({ max: 2, windowMs: 10 * 60 * 1000 });

export const TICKET_TOPICS = [
  { name: 'Pesanan & Layanan', value: 'pesanan' },
  { name: 'Top Up & Pembayaran', value: 'topup' },
  { name: 'Kendala Teknis', value: 'teknis' },
  { name: 'Lainnya', value: 'lain' },
];

const TOPIC_LABELS = Object.fromEntries(TICKET_TOPICS.map((topic) => [topic.value, topic.name]));

export const CLOSE_BUTTON_ID = 'ticket:close';
export const CLAIM_BUTTON_ID = 'ticket:claim';

function ticketControls() {
  return new ActionRowBuilder().addComponents(
    new ButtonBuilder()
      .setCustomId(CLAIM_BUTTON_ID)
      .setLabel('Tangani')
      .setStyle(ButtonStyle.Primary)
      .setEmoji('🙋'),
    new ButtonBuilder()
      .setCustomId(CLOSE_BUTTON_ID)
      .setLabel('Tutup tiket')
      .setStyle(ButtonStyle.Danger)
      .setEmoji('🔒'),
  );
}

export function isTicketEnabled() {
  return true;
}

export async function findOpenTicket(guildId, userId) {
  const data = await store.read();
  const record = data.open[`${guildId}:${userId}`];

  return record ?? null;
}

/**
 * Membuat channel tiket baru. Mengembalikan hasil yang menjelaskan
 * keberhasilan atau alasan penolakan.
 */
function resolveCategory(guild) {
  const categoryId = config.channels.ticketCategory;

  if (categoryId !== '') {
    return guild.channels.cache.get(categoryId)
      ?? guild.channels.fetch(categoryId).catch(() => null);
  }

  return guild.channels.cache.find(
    (ch) => ch.type === ChannelType.GuildCategory && ch.name.toLowerCase() === 'customer support',
  ) ?? null;
}

export async function createTicket({ guild, member, topic, description }) {
  const category = await resolveCategory(guild);

  const existing = await findOpenTicket(guild.id, member.id);

  if (existing) {
    const channel = guild.channels.cache.get(existing.channelId)
      ?? await guild.channels.fetch(existing.channelId).catch(() => null);

    if (channel) {
      return { ok: false, reason: 'already-open', channelId: channel.id };
    }
  }

  const limit = creationLimiter.hit(`${guild.id}:${member.id}`);

  if (limit.exceeded) {
    return { ok: false, reason: 'rate-limited', retryAfterMs: limit.retryAfterMs };
  }

  const data = await store.write((current) => ({
    ...current,
    counter: current.counter + 1,
  }));

  const number = String(data.counter).padStart(4, '0');

  const overwrites = [
    {
      id: guild.roles.everyone.id,
      deny: [PermissionFlagsBits.ViewChannel],
    },
    {
      id: member.id,
      allow: [
        PermissionFlagsBits.ViewChannel,
        PermissionFlagsBits.SendMessages,
        PermissionFlagsBits.ReadMessageHistory,
        PermissionFlagsBits.AttachFiles,
      ],
    },
  ];

  if (config.roles.staff !== '') {
    overwrites.push({
      id: config.roles.staff,
      allow: [
        PermissionFlagsBits.ViewChannel,
        PermissionFlagsBits.SendMessages,
        PermissionFlagsBits.ReadMessageHistory,
        PermissionFlagsBits.AttachFiles,
        PermissionFlagsBits.ManageMessages,
      ],
    });
  }

  const channel = await guild.channels.create({
    name: `tiket-${number}`,
    type: ChannelType.GuildText,
    parent: category?.id,
    topic: `Tiket ${number} · ${TOPIC_LABELS[topic] ?? topic} · dibuka oleh ${member.user.tag}`,
    permissionOverwrites: overwrites,
    reason: `Tiket support dibuat oleh ${member.user.tag}`,
  });

  await store.write((current) => ({
    ...current,
    open: {
      ...current.open,
      [`${guild.id}:${member.id}`]: {
        channelId: channel.id,
        number,
        topic,
        openedAt: new Date().toISOString(),
      },
    },
  }));

  const mention = config.roles.staff !== '' ? `<@&${config.roles.staff}>` : '';

  const embed = brandEmbed({
    title: `Tiket #${number}`,
    description: description,
    color: BRAND.info,
    fields: [
      { name: 'Kategori', value: TOPIC_LABELS[topic] ?? topic, inline: true },
      { name: 'Dibuka oleh', value: `<@${member.id}>`, inline: true },
      { name: 'Waktu', value: formatDateTime(), inline: true },
    ],
    footer: 'Tim akan merespons secepatnya. Tekan Tutup tiket bila sudah selesai.',
  });

  await channel.send({
    content: `<@${member.id}> ${mention}`.trim(),
    embeds: [embed],
    components: [ticketControls()],
  });

  log.info({ ticket: number, user: member.id }, 'Tiket dibuat');

  return { ok: true, channelId: channel.id, number };
}

/**
 * Menutup tiket dan menghapus channel setelah tenggang waktu singkat.
 */
export async function closeTicket({ channel, closedBy }) {
  const data = await store.read();
  const entry = Object.entries(data.open).find(([, value]) => value.channelId === channel.id);

  if (entry) {
    const [key] = entry;

    await store.write((current) => {
      const next = { ...current.open };
      delete next[key];

      return { ...current, open: next };
    });
  }

  await channel.send({
    embeds: [brandEmbed({
      title: 'Tiket ditutup',
      description: 'Channel ini akan dihapus dalam 10 detik.',
      color: BRAND.neutral,
      fields: [{ name: 'Ditutup oleh', value: `<@${closedBy.id}>`, inline: true }],
    })],
  });

  setTimeout(() => {
    channel.delete(`Tiket ditutup oleh ${closedBy.tag}`).catch((error) => {
      log.warn({ channel: channel.id, err: error.message }, 'Gagal menghapus channel tiket');
    });
  }, 10_000);

  log.info({ channel: channel.id, closedBy: closedBy.id }, 'Tiket ditutup');
}

export function isTicketChannel(channel) {
  return typeof channel?.name === 'string'
    && channel.name.startsWith('tiket-')
    && (config.channels.ticketCategory === '' || channel.parentId === config.channels.ticketCategory);
}
