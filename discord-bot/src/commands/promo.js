import { MessageFlags, SlashCommandBuilder } from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, dangerEmbed, warningEmbed } from '../core/format.js';
import { isStaff } from '../core/permissions.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('command:promo');

export default {
  data: new SlashCommandBuilder()
    .setName('promo')
    .setDescription('Kirim pengumuman promo ke channel promo-update.')
    .addStringOption((opt) => opt
      .setName('judul')
      .setDescription('Judul promo')
      .setRequired(true))
    .addStringOption((opt) => opt
      .setName('deskripsi')
      .setDescription('Detail promo atau diskon')
      .setRequired(true))
    .addStringOption((opt) => opt
      .setName('kode')
      .setDescription('Kode voucher (opsional)')
      .setRequired(false))
    .addStringOption((opt) => opt
      .setName('gambar')
      .setDescription('URL gambar/banner promo (opsional)')
      .setRequired(false)),

  async execute(interaction) {
    if (!isStaff(interaction)) {
      await interaction.reply({
        content: 'Hanya staff yang dapat mengirim pengumuman promo.',
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const channelId = config.channels.promoUpdate;

    if (!channelId) {
      await interaction.reply({
        embeds: [warningEmbed({
          title: 'Channel promo-update belum dikonfigurasi',
          description: 'Isi `CHANNEL_PROMO_UPDATE_ID` pada file `.env`.',
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const channel = interaction.guild.channels.cache.get(channelId)
      ?? await interaction.guild.channels.fetch(channelId).catch(() => null);

    if (!channel?.isTextBased()) {
      await interaction.reply({
        embeds: [dangerEmbed({
          title: 'Channel tidak dapat diakses',
          description: 'Pastikan bot memiliki izin melihat dan mengirim pesan di channel promo-update.',
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const judul = interaction.options.getString('judul', true);
    const deskripsi = interaction.options.getString('deskripsi', true);
    const kode = interaction.options.getString('kode');
    const gambar = interaction.options.getString('gambar');

    const fields = [];
    if (kode) {
      fields.push({ name: '🎟️ Kode Voucher', value: `\`${kode}\``, inline: true });
    }

    const embed = brandEmbed({
      title: `🔔 PROMO: ${judul}`,
      description: deskripsi,
      color: BRAND.warning,
      fields,
      footer: 'Younz Digital Center Promo',
    });

    if (gambar && (gambar.startsWith('http://') || gambar.startsWith('https://'))) {
      embed.setImage(gambar);
    }

    await channel.send({ content: '@everyone Promo terbaru dari Younz Digital Center!', embeds: [embed] });

    await interaction.reply({
      content: `Promo berhasil dipublikasikan di <#${channel.id}>.`,
      flags: MessageFlags.Ephemeral,
    });

    log.info({ user: interaction.user.id, title: judul }, 'Promo dipublikasikan');
  },
};
