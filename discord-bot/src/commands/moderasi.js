import { MessageFlags, PermissionFlagsBits, SlashCommandBuilder } from 'discord.js';

import { BRAND, brandEmbed, dangerEmbed, formatDateTime, truncate, warningEmbed } from '../core/format.js';
import { config } from '../core/config.js';
import { isModerator } from '../core/permissions.js';
import { childLogger } from '../core/logger.js';
import { JsonStore } from '../services/store.js';

const log = childLogger('command:moderasi');

const warningStore = new JsonStore('warnings.json', { entries: {} });

const TIMEOUT_CHOICES = [
  { name: '60 detik', value: 60 },
  { name: '5 menit', value: 300 },
  { name: '10 menit', value: 600 },
  { name: '1 jam', value: 3600 },
  { name: '1 hari', value: 86400 },
  { name: '1 minggu', value: 604800 },
];

async function sendModerationLog(guild, embed) {
  if (config.channels.log === '') {
    return;
  }

  const channel = guild.channels.cache.get(config.channels.log)
    ?? await guild.channels.fetch(config.channels.log).catch(() => null);

  if (!channel?.isTextBased()) {
    return;
  }

  await channel.send({ embeds: [embed] }).catch((error) => {
    log.warn({ err: error.message }, 'Gagal menulis log moderasi');
  });
}

function actionEmbed({ action, target, moderator, reason, color, extra = [] }) {
  return brandEmbed({
    title: `Moderasi: ${action}`,
    color,
    fields: [
      { name: 'Anggota', value: `${target.tag}\n\`${target.id}\``, inline: true },
      { name: 'Moderator', value: `<@${moderator.id}>`, inline: true },
      { name: 'Waktu', value: formatDateTime(), inline: true },
      { name: 'Alasan', value: truncate(reason || 'Tidak disebutkan', 1000), inline: false },
      ...extra,
    ],
  });
}

export default {
  data: new SlashCommandBuilder()
    .setName('moderasi')
    .setDescription('Tindakan moderasi anggota server.')
    .setDefaultMemberPermissions(PermissionFlagsBits.ModerateMembers)
    .addSubcommand((sub) => sub
      .setName('peringatan')
      .setDescription('Beri peringatan tercatat kepada anggota.')
      .addUserOption((option) => option.setName('anggota').setDescription('Anggota yang diperingatkan.').setRequired(true))
      .addStringOption((option) => option.setName('alasan').setDescription('Alasan peringatan.').setRequired(true).setMaxLength(500)))
    .addSubcommand((sub) => sub
      .setName('riwayat')
      .setDescription('Lihat riwayat peringatan anggota.')
      .addUserOption((option) => option.setName('anggota').setDescription('Anggota yang diperiksa.').setRequired(true)))
    .addSubcommand((sub) => sub
      .setName('timeout')
      .setDescription('Bisukan anggota untuk sementara.')
      .addUserOption((option) => option.setName('anggota').setDescription('Anggota yang dibisukan.').setRequired(true))
      .addIntegerOption((option) => option
        .setName('durasi')
        .setDescription('Lama timeout.')
        .setRequired(true)
        .addChoices(...TIMEOUT_CHOICES))
      .addStringOption((option) => option.setName('alasan').setDescription('Alasan timeout.').setMaxLength(500)))
    .addSubcommand((sub) => sub
      .setName('kick')
      .setDescription('Keluarkan anggota dari server.')
      .addUserOption((option) => option.setName('anggota').setDescription('Anggota yang dikeluarkan.').setRequired(true))
      .addStringOption((option) => option.setName('alasan').setDescription('Alasan kick.').setMaxLength(500)))
    .addSubcommand((sub) => sub
      .setName('ban')
      .setDescription('Blokir anggota dari server.')
      .addUserOption((option) => option.setName('anggota').setDescription('Anggota yang diblokir.').setRequired(true))
      .addStringOption((option) => option.setName('alasan').setDescription('Alasan ban.').setMaxLength(500)))
    .addSubcommand((sub) => sub
      .setName('bersihkan')
      .setDescription('Hapus sejumlah pesan terakhir pada channel ini.')
      .addIntegerOption((option) => option
        .setName('jumlah')
        .setDescription('Jumlah pesan (1-100).')
        .setRequired(true)
        .setMinValue(1)
        .setMaxValue(100))),

  async execute(interaction) {
    if (!interaction.inGuild()) {
      await interaction.reply({ content: 'Perintah ini hanya untuk di dalam server.', flags: MessageFlags.Ephemeral });
      return;
    }

    if (!isModerator(interaction)) {
      await interaction.reply({ content: 'Perintah ini hanya untuk moderator.', flags: MessageFlags.Ephemeral });
      return;
    }

    const subcommand = interaction.options.getSubcommand();

    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    try {
      if (subcommand === 'bersihkan') {
        const amount = interaction.options.getInteger('jumlah', true);
        const deleted = await interaction.channel.bulkDelete(amount, true);

        await interaction.editReply({
          embeds: [brandEmbed({
            title: 'Pesan dibersihkan',
            description: `${deleted.size} pesan dihapus.`,
            color: BRAND.info,
            footer: 'Pesan lebih lama dari 14 hari tidak dapat dihapus massal.',
          })],
        });

        await sendModerationLog(interaction.guild, brandEmbed({
          title: 'Moderasi: Bersihkan pesan',
          color: BRAND.info,
          fields: [
            { name: 'Channel', value: `<#${interaction.channelId}>`, inline: true },
            { name: 'Jumlah', value: String(deleted.size), inline: true },
            { name: 'Moderator', value: `<@${interaction.user.id}>`, inline: true },
          ],
        }));

        return;
      }

      const target = interaction.options.getUser('anggota', true);
      const reason = interaction.options.getString('alasan') ?? '';

      if (target.id === interaction.user.id) {
        await interaction.editReply({
          embeds: [warningEmbed({ title: 'Tidak diizinkan', description: 'Anda tidak dapat menargetkan diri sendiri.' })],
        });
        return;
      }

      if (target.bot) {
        await interaction.editReply({
          embeds: [warningEmbed({ title: 'Tidak diizinkan', description: 'Target adalah bot.' })],
        });
        return;
      }

      if (subcommand === 'peringatan') {
        const key = `${interaction.guildId}:${target.id}`;

        const data = await warningStore.write((current) => {
          const list = current.entries[key] ?? [];

          return {
            ...current,
            entries: {
              ...current.entries,
              [key]: [
                ...list,
                {
                  reason,
                  moderatorId: interaction.user.id,
                  at: new Date().toISOString(),
                },
              ],
            },
          };
        });

        const total = data.entries[key].length;

        await target.send({
          embeds: [warningEmbed({
            title: `Peringatan dari ${interaction.guild.name}`,
            description: truncate(reason, 1000),
            footer: `Peringatan ke-${total}`,
          })],
        }).catch(() => undefined);

        const embed = actionEmbed({
          action: 'Peringatan',
          target,
          moderator: interaction.user,
          reason,
          color: BRAND.warning,
          extra: [{ name: 'Total peringatan', value: String(total), inline: true }],
        });

        await interaction.editReply({ embeds: [embed] });
        await sendModerationLog(interaction.guild, embed);
        return;
      }

      if (subcommand === 'riwayat') {
        const data = await warningStore.read();
        const list = data.entries[`${interaction.guildId}:${target.id}`] ?? [];

        if (list.length === 0) {
          await interaction.editReply({
            embeds: [brandEmbed({
              title: 'Riwayat Peringatan',
              description: `${target.tag} belum pernah diperingatkan.`,
              color: BRAND.success,
            })],
          });
          return;
        }

        const lines = list.slice(-10).reverse().map((item, index) => (
          `**${list.length - index}.** ${formatDateTime(item.at)} · <@${item.moderatorId}>\n${truncate(item.reason || 'Tidak disebutkan', 200)}`
        ));

        await interaction.editReply({
          embeds: [brandEmbed({
            title: `Riwayat Peringatan (${list.length})`,
            description: lines.join('\n\n'),
            color: BRAND.warning,
            footer: `Anggota: ${target.tag}`,
          })],
        });
        return;
      }

      const member = await interaction.guild.members.fetch(target.id).catch(() => null);

      if (!member) {
        await interaction.editReply({
          embeds: [warningEmbed({ title: 'Anggota tidak ditemukan', description: 'Anggota sudah tidak berada di server.' })],
        });
        return;
      }

      if (!member.manageable) {
        await interaction.editReply({
          embeds: [warningEmbed({
            title: 'Tidak dapat ditindak',
            description: 'Role anggota tersebut setara atau lebih tinggi dari bot.',
          })],
        });
        return;
      }

      if (subcommand === 'timeout') {
        const seconds = interaction.options.getInteger('durasi', true);

        await member.timeout(seconds * 1000, reason || 'Tidak disebutkan');

        const embed = actionEmbed({
          action: 'Timeout',
          target,
          moderator: interaction.user,
          reason,
          color: BRAND.warning,
          extra: [{
            name: 'Durasi',
            value: TIMEOUT_CHOICES.find((choice) => choice.value === seconds)?.name ?? `${seconds} detik`,
            inline: true,
          }],
        });

        await interaction.editReply({ embeds: [embed] });
        await sendModerationLog(interaction.guild, embed);
        return;
      }

      if (subcommand === 'kick') {
        await member.kick(reason || 'Tidak disebutkan');

        const embed = actionEmbed({
          action: 'Kick',
          target,
          moderator: interaction.user,
          reason,
          color: BRAND.danger,
        });

        await interaction.editReply({ embeds: [embed] });
        await sendModerationLog(interaction.guild, embed);
        return;
      }

      if (subcommand === 'ban') {
        await member.ban({ reason: reason || 'Tidak disebutkan', deleteMessageSeconds: 24 * 3600 });

        const embed = actionEmbed({
          action: 'Ban',
          target,
          moderator: interaction.user,
          reason,
          color: BRAND.danger,
          extra: [{ name: 'Pesan dihapus', value: '24 jam terakhir', inline: true }],
        });

        await interaction.editReply({ embeds: [embed] });
        await sendModerationLog(interaction.guild, embed);
        return;
      }

      await interaction.editReply({ content: 'Subperintah tidak dikenal.' });
    } catch (error) {
      log.error({ err: error.message, subcommand }, 'Perintah moderasi gagal');

      const hint = error.code === 50013
        ? 'Bot tidak memiliki izin yang dibutuhkan. Periksa urutan role bot.'
        : 'Terjadi kendala saat menjalankan tindakan moderasi.';

      await interaction.editReply({
        embeds: [dangerEmbed({ title: 'Gagal', description: hint })],
      });
    }
  },
};
