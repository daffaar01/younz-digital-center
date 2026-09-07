import {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  MessageFlags,
  SlashCommandBuilder,
} from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, dangerEmbed, warningEmbed } from '../core/format.js';
import { isStaff } from '../core/permissions.js';
import { fetchServices } from '../services/younz.js';

export const PANEL_ORDER_BUTTON_ID = 'panel:order_modal';
export const PANEL_TICKET_BUTTON_ID = 'panel:ticket_modal';
export const PANEL_REFRESH_CATALOG_ID = 'panel:refresh_catalog';

export default {
  data: new SlashCommandBuilder()
    .setName('panel')
    .setDescription('Pasang panel interaktif ke channel YOUNZSTATION.')
    .addSubcommand((sub) => sub
      .setName('order')
      .setDescription('Pasang panel tombol pesan layanan.'))
    .addSubcommand((sub) => sub
      .setName('support')
      .setDescription('Pasang panel tombol Buka Tiket support.'))
    .addSubcommand((sub) => sub
      .setName('katalog')
      .setDescription('Post atau perbarui daftar layanan aktif.')),

  async execute(interaction) {
    if (!isStaff(interaction)) {
      await interaction.reply({
        content: 'Hanya staff yang dapat memasang panel.',
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const sub = interaction.options.getSubcommand();
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    if (sub === 'order') {
      const channelId = config.channels.orderLayanan || interaction.channelId;
      const channel = interaction.guild.channels.cache.get(channelId)
        ?? await interaction.guild.channels.fetch(channelId).catch(() => null);

      if (!channel?.isTextBased()) {
        await interaction.editReply({ content: 'Channel order-layanan tidak ditemukan.' });
        return;
      }

      const embed = brandEmbed({
        title: '🛒 Order Layanan Younz Digital Center',
        description: 'Klik tombol di bawah ini untuk memulai pemesanan layanan jasa/digital secara cepat, atau kunjungi website resmi kami.',
        color: BRAND.primary,
        fields: [
          { name: '🌐 Website', value: 'https://younzdigitalcenter.my.id/pesan', inline: true },
          { name: '📱 Top Up Digital', value: 'https://younzdigitalcenter.my.id/topup', inline: true },
        ],
      });

      const row = new ActionRowBuilder().addComponents(
        new ButtonBuilder()
          .setCustomId(PANEL_ORDER_BUTTON_ID)
          .setLabel('Pesan via Discord')
          .setStyle(ButtonStyle.Success)
          .setEmoji('🛍️'),
        new ButtonBuilder()
          .setLabel('Order via Web')
          .setStyle(ButtonStyle.Link)
          .setURL('https://younzdigitalcenter.my.id/pesan')
          .setEmoji('🔗'),
      );

      await channel.send({ embeds: [embed], components: [row] });
      await interaction.editReply({ content: `Panel Order berhasil dipasang di <#${channel.id}>.` });
      return;
    }

    if (sub === 'support') {
      const channelId = config.channels.customerSupport || interaction.channelId;
      const channel = interaction.guild.channels.cache.get(channelId)
        ?? await interaction.guild.channels.fetch(channelId).catch(() => null);

      if (!channel?.isTextBased()) {
        await interaction.editReply({ content: 'Channel customer-support tidak ditemukan.' });
        return;
      }

      const embed = brandEmbed({
        title: '🎟️ Customer Support Younz Digital Center',
        description: 'Butuh bantuan terkait pesanan, pembayaran, atau kendala teknis? Klik tombol di bawah ini untuk membuka tiket privat dengan Tim Support kami.',
        color: BRAND.info,
        footer: 'Support Operasional 09:00 - 17:00 WIB',
      });

      const row = new ActionRowBuilder().addComponents(
        new ButtonBuilder()
          .setCustomId(PANEL_TICKET_BUTTON_ID)
          .setLabel('Buka Tiket Support')
          .setStyle(ButtonStyle.Primary)
          .setEmoji('💬'),
      );

      await channel.send({ embeds: [embed], components: [row] });
      await interaction.editReply({ content: `Panel Support berhasil dipasang di <#${channel.id}>.` });
      return;
    }

    if (sub === 'katalog') {
      const channelId = config.channels.daftarLayanan || interaction.channelId;
      const channel = interaction.guild.channels.cache.get(channelId)
        ?? await interaction.guild.channels.fetch(channelId).catch(() => null);

      if (!channel?.isTextBased()) {
        await interaction.editReply({ content: 'Channel daftar-layanan tidak ditemukan.' });
        return;
      }

      const services = await fetchServices().catch(() => []);

      const fields = services.length > 0
        ? services.map((s) => ({
          name: `📋 ${s.name}`,
          value: `${s.description || 'Layanan profesional Younz Digital Center.'}\n💰 Mulai ${s.price_formatted || 'Hubungi Admin'}`,
          inline: false,
        }))
        : [{ name: 'Informasi Layanan', value: 'Kunjungi https://younzdigitalcenter.my.id untuk melihat katalog lengkap.', inline: false }];

      const embed = brandEmbed({
        title: '📋 Daftar Layanan & Jasa - Younz Digital Center',
        description: 'Berikut daftar layanan resmi yang tersedia:',
        color: BRAND.success,
        fields,
      });

      await channel.send({ embeds: [embed] });
      await interaction.editReply({ content: `Daftar Layanan berhasil diposting di <#${channel.id}>.` });
    }
  },
};
