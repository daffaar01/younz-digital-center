import { MessageFlags, SlashCommandBuilder } from 'discord.js';

import { brandEmbed, dangerEmbed, warningEmbed } from '../core/format.js';
import { isStaff } from '../core/permissions.js';
import { childLogger } from '../core/logger.js';
import {
  TICKET_TOPICS,
  closeTicket,
  createTicket,
  isTicketChannel,
  isTicketEnabled,
} from '../services/tickets.js';

const log = childLogger('command:tiket');

export default {
  data: new SlashCommandBuilder()
    .setName('tiket')
    .setDescription('Buka atau tutup tiket support.')
    .addSubcommand((sub) => sub
      .setName('buka')
      .setDescription('Buat channel tiket privat untuk dibantu tim.')
      .addStringOption((option) => option
        .setName('kategori')
        .setDescription('Jenis kendala yang Anda alami.')
        .setRequired(true)
        .addChoices(...TICKET_TOPICS))
      .addStringOption((option) => option
        .setName('keterangan')
        .setDescription('Jelaskan kendala Anda secara singkat.')
        .setRequired(true)
        .setMinLength(10)
        .setMaxLength(1000)))
    .addSubcommand((sub) => sub
      .setName('tutup')
      .setDescription('Tutup tiket pada channel ini.')),

  async execute(interaction) {
    if (!isTicketEnabled()) {
      await interaction.reply({
        embeds: [warningEmbed({
          title: 'Fitur tiket belum dikonfigurasi',
          description: 'Isi `CATEGORY_TICKET_ID` pada file `.env` dengan ID kategori tempat tiket dibuat.',
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    if (!interaction.inGuild()) {
      await interaction.reply({
        content: 'Perintah ini hanya dapat dijalankan di dalam server.',
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const subcommand = interaction.options.getSubcommand();

    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    try {
      if (subcommand === 'buka') {
        const result = await createTicket({
          guild: interaction.guild,
          member: interaction.member,
          topic: interaction.options.getString('kategori', true),
          description: interaction.options.getString('keterangan', true),
        });

        if (result.ok) {
          await interaction.editReply({
            embeds: [brandEmbed({
              title: `Tiket #${result.number} dibuat`,
              description: `Lanjutkan percakapan di <#${result.channelId}>.`,
            })],
          });
          return;
        }

        const messages = {
          'category-missing': 'Kategori tiket tidak ditemukan. Periksa `CATEGORY_TICKET_ID`.',
          'already-open': `Anda masih punya tiket terbuka di <#${result.channelId}>. Selesaikan dulu tiket tersebut.`,
          'rate-limited': `Terlalu banyak tiket dalam waktu singkat. Coba lagi dalam ${Math.ceil((result.retryAfterMs ?? 0) / 60000)} menit.`,
        };

        await interaction.editReply({
          embeds: [warningEmbed({
            title: 'Tiket tidak dapat dibuat',
            description: messages[result.reason] ?? 'Terjadi kendala yang tidak diketahui.',
          })],
        });
        return;
      }

      if (subcommand === 'tutup') {
        if (!isTicketChannel(interaction.channel)) {
          await interaction.editReply({
            embeds: [warningEmbed({
              title: 'Bukan channel tiket',
              description: 'Jalankan perintah ini di dalam channel tiket.',
            })],
          });
          return;
        }

        await closeTicket({ channel: interaction.channel, closedBy: interaction.user });

        await interaction.editReply({
          embeds: [brandEmbed({
            title: 'Tiket ditutup',
            description: 'Channel akan dihapus otomatis.',
          })],
        });
        return;
      }

      await interaction.editReply({ content: 'Subperintah tidak dikenal.' });
    } catch (error) {
      log.error({ err: error.message, subcommand }, 'Perintah tiket gagal');

      const hint = error.code === 50013
        ? 'Bot tidak punya izin Manage Channels pada kategori tiket.'
        : 'Terjadi kendala saat memproses tiket.';

      await interaction.editReply({
        embeds: [dangerEmbed({ title: 'Gagal', description: hint })],
      });
    }
  },

  metadata: { staffOnly: false, isStaffHelper: isStaff },
};
