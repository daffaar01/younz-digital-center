import { Events, PermissionFlagsBits } from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, truncate } from '../core/format.js';
import { childLogger } from '../core/logger.js';
import { SlidingWindowLimiter } from '../services/rate-limiter.js';

const log = childLogger('event:antispam');

const limiter = new SlidingWindowLimiter({
  max: config.antispam.maxMessages,
  windowMs: config.antispam.windowMs,
});

const punished = new SlidingWindowLimiter({ max: 1, windowMs: 60_000 });

const INVITE_PATTERN = /(discord\.(gg|io|me|li)|discordapp\.com\/invite)\/[a-z0-9-]+/i;

function isExempt(message) {
  const member = message.member;

  if (!member) {
    return true;
  }

  if (message.guild.ownerId === member.id) {
    return true;
  }

  if (member.permissions.has(PermissionFlagsBits.ManageMessages)) {
    return true;
  }

  if (config.roles.staff !== '' && member.roles.cache.has(config.roles.staff)) {
    return true;
  }

  return false;
}

async function logViolation(guild, embed) {
  if (config.channels.log === '') {
    return;
  }

  const channel = guild.channels.cache.get(config.channels.log)
    ?? await guild.channels.fetch(config.channels.log).catch(() => null);

  if (!channel?.isTextBased()) {
    return;
  }

  await channel.send({ embeds: [embed] }).catch(() => undefined);
}

export default {
  name: Events.MessageCreate,

  async execute(message) {
    if (message.author.bot || !message.inGuild()) {
      return;
    }

    if (isExempt(message)) {
      return;
    }

    const key = `${message.guildId}:${message.author.id}`;

    if (INVITE_PATTERN.test(message.content)) {
      await message.delete().catch(() => undefined);

      const embed = brandEmbed({
        title: 'Anti-spam: tautan undangan dihapus',
        color: BRAND.warning,
        fields: [
          { name: 'Anggota', value: `<@${message.author.id}>`, inline: true },
          { name: 'Channel', value: `<#${message.channelId}>`, inline: true },
          { name: 'Isi pesan', value: truncate(message.content, 500), inline: false },
        ],
      });

      await logViolation(message.guild, embed);

      await message.channel.send({
        content: `<@${message.author.id}> tautan undangan tidak diizinkan di server ini.`,
      }).then((notice) => {
        setTimeout(() => notice.delete().catch(() => undefined), 8000);
      }).catch(() => undefined);

      return;
    }

    const result = limiter.hit(key);

    if (!result.exceeded) {
      return;
    }

    if (punished.hit(key).exceeded) {
      return;
    }

    const member = message.member;

    if (!member.moderatable) {
      log.warn({ user: member.id }, 'Anggota tidak dapat ditimeout oleh bot');
      return;
    }

    const minutes = config.antispam.timeoutMinutes;

    await member.timeout(minutes * 60 * 1000, 'Anti-spam otomatis').catch((error) => {
      log.warn({ err: error.message }, 'Gagal menerapkan timeout anti-spam');
    });

    await message.channel.send({
      content: `<@${member.id}> dibisukan ${minutes} menit karena mengirim pesan terlalu cepat.`,
    }).catch(() => undefined);

    const embed = brandEmbed({
      title: 'Anti-spam: timeout otomatis',
      color: BRAND.danger,
      fields: [
        { name: 'Anggota', value: `<@${member.id}>\n\`${member.id}\``, inline: true },
        { name: 'Channel', value: `<#${message.channelId}>`, inline: true },
        { name: 'Durasi', value: `${minutes} menit`, inline: true },
        {
          name: 'Pemicu',
          value: `${result.count} pesan dalam ${Math.round(config.antispam.windowMs / 1000)} detik`,
          inline: false,
        },
      ],
    });

    await logViolation(message.guild, embed);

    limiter.reset(key);

    log.info({ user: member.id, count: result.count }, 'Timeout anti-spam diterapkan');
  },
};
