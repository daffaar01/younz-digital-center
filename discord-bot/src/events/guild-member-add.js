import { Events } from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, formatDateTime } from '../core/format.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('event:member-add');

export default {
  name: Events.GuildMemberAdd,

  async execute(member) {
    if (config.roles.auto !== '') {
      const role = member.guild.roles.cache.get(config.roles.auto);

      if (!role) {
        log.warn({ role: config.roles.auto }, 'Role otomatis tidak ditemukan');
      } else if (role.comparePositionTo(member.guild.members.me.roles.highest) >= 0) {
        log.warn({ role: role.id }, 'Role otomatis berada di atas role bot, tidak dapat diberikan');
      } else {
        await member.roles.add(role, 'Auto-role member baru').catch((error) => {
          log.warn({ err: error.message }, 'Gagal memberikan role otomatis');
        });
      }
    }

    if (config.channels.welcome === '') {
      return;
    }

    const channel = member.guild.channels.cache.get(config.channels.welcome)
      ?? await member.guild.channels.fetch(config.channels.welcome).catch(() => null);

    if (!channel?.isTextBased()) {
      log.warn({ channel: config.channels.welcome }, 'Channel sambutan tidak dapat diakses');
      return;
    }

    const accountAge = Date.now() - member.user.createdTimestamp;
    const isNewAccount = accountAge < 7 * 24 * 60 * 60 * 1000;

    const embed = brandEmbed({
      title: `Selamat datang, ${member.user.username}!`,
      description: [
        `Terima kasih sudah bergabung di **${member.guild.name}**.`,
        '',
        'Gunakan `/bantuan` untuk melihat perintah yang tersedia.',
        config.channels.ticketCategory !== ''
          ? 'Butuh bantuan? Buka tiket dengan `/tiket buka`.'
          : null,
      ].filter(Boolean).join('\n'),
      color: BRAND.success,
      fields: [
        { name: 'Anggota ke', value: String(member.guild.memberCount), inline: true },
        { name: 'Bergabung', value: formatDateTime(), inline: true },
        {
          name: 'Umur akun',
          value: isNewAccount ? '⚠️ Kurang dari 7 hari' : `<t:${Math.floor(member.user.createdTimestamp / 1000)}:R>`,
          inline: true,
        },
      ],
    });

    await channel.send({ content: `<@${member.id}>`, embeds: [embed] }).catch((error) => {
      log.warn({ err: error.message }, 'Gagal mengirim pesan sambutan');
    });

    log.info({ user: member.id, guild: member.guild.id }, 'Member baru disambut');
  },
};
