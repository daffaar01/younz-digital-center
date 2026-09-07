import { randomUUID } from 'node:crypto';

import {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  Events,
  MessageFlags,
  ModalBuilder,
  TextInputBuilder,
  TextInputStyle,
} from 'discord.js';

import {
  PANEL_ORDER_BUTTON_ID,
  PANEL_TICKET_BUTTON_ID,
} from '../commands/panel.js';
import { TOPUP_MODAL_PREFIX } from '../commands/topup.js';
import { createTopupOrder } from '../services/younz.js';

import { dangerEmbed } from '../core/format.js';
import { childLogger } from '../core/logger.js';
import { isStaff } from '../core/permissions.js';
import {
  CLAIM_BUTTON_ID,
  CLOSE_BUTTON_ID,
  closeTicket,
  createTicket,
  isTicketChannel,
} from '../services/tickets.js';

const log = childLogger('event:interaction');

async function respondWithError(interaction, description) {
  const payload = {
    embeds: [dangerEmbed({ title: 'Terjadi kendala', description })],
    flags: MessageFlags.Ephemeral,
  };

  try {
    if (interaction.deferred || interaction.replied) {
      await interaction.followUp(payload);
      return;
    }

    await interaction.reply(payload);
  } catch (error) {
    log.warn({ err: error.message }, 'Gagal mengirim pesan error ke pengguna');
  }
}

function ticketModal(customId, title, fields) {
  const modal = new ModalBuilder().setCustomId(customId).setTitle(title);
  modal.addComponents(...fields.map(({ id, label, style = TextInputStyle.Short, required = true, placeholder }) => (
    new ActionRowBuilder().addComponents(
      new TextInputBuilder()
        .setCustomId(id)
        .setLabel(label)
        .setStyle(style)
        .setRequired(required)
        .setPlaceholder(placeholder ?? ''),
    )
  )));
  return modal;
}

async function handlePanelButton(interaction) {
  if (interaction.customId === PANEL_ORDER_BUTTON_ID) {
    await interaction.showModal(ticketModal('panel:order_submit', 'Pesan Layanan', [
      { id: 'service', label: 'Layanan yang diinginkan', placeholder: 'Contoh: Jasa desain logo' },
      { id: 'details', label: 'Detail kebutuhan', style: TextInputStyle.Paragraph, placeholder: 'Jelaskan kebutuhan Anda secara singkat' },
    ]));
    return;
  }

  if (interaction.customId === PANEL_TICKET_BUTTON_ID) {
    await interaction.showModal(ticketModal('panel:support_submit', 'Buka Tiket Support', [
      { id: 'topic', label: 'Kategori kendala', placeholder: 'Pesanan, Top Up, Teknis, atau Lainnya' },
      { id: 'details', label: 'Jelaskan kendala Anda', style: TextInputStyle.Paragraph, placeholder: 'Minimal 10 karakter' },
    ]));
  }
}

async function handleTopupModal(interaction) {
  const [mode, productRaw] = interaction.customId.slice(TOPUP_MODAL_PREFIX.length).split(':');
  const productId = Number(productRaw);
  const destination = interaction.fields.getTextInputValue('destination').trim();

  await interaction.deferReply({ flags: MessageFlags.Ephemeral });

  try {
    const result = await createTopupOrder({
      idempotency_key: randomUUID(),
      product_id: productId,
      destination,
      destination_confirmation: destination,
      customer_name: interaction.fields.getTextInputValue('name').trim(),
      customer_email: interaction.fields.getTextInputValue('email').trim(),
      customer_phone: interaction.fields.getTextInputValue('phone').trim(),
      terms: true,
    });

    const order = result.data;
    const row = result.redirect_url
      ? [new ActionRowBuilder().addComponents(
        new ButtonBuilder()
          .setLabel('Lanjutkan Pembayaran')
          .setStyle(ButtonStyle.Link)
          .setURL(result.redirect_url),
      )]
      : [];

    await interaction.editReply({
      content: [
        `Pesanan **${order.order_number}** berhasil dibuat.`,
        `Produk: **${order.product_name}**`,
        `Total: **Rp ${Number(order.total_amount).toLocaleString('id-ID')}**`,
        'Klik tombol di bawah untuk melanjutkan pembayaran.',
      ].join('\n'),
      components: row,
    });
  } catch (error) {
    let message = 'Pesanan belum dapat dibuat. Periksa data lalu coba lagi.';

    try {
      const body = JSON.parse(error.body ?? '{}');
      message = body.message ?? message;
      const details = Object.values(body.errors ?? {}).flat();
      if (details.length > 0) message += `\n${details.join('\n')}`;
    } catch {}

    await interaction.editReply({ content: message });
  }
}

async function handlePanelModal(interaction) {
  const details = interaction.fields.getTextInputValue('details');
  const isOrder = interaction.customId === 'panel:order_submit';
  const topic = isOrder ? 'pesanan' : 'lain';
  const label = isOrder
    ? interaction.fields.getTextInputValue('service')
    : interaction.fields.getTextInputValue('topic');

  if (details.trim().length < 10) {
    await interaction.reply({
      content: 'Keterangan harus terdiri dari minimal 10 karakter.',
      flags: MessageFlags.Ephemeral,
    });
    return;
  }

  await interaction.deferReply({ flags: MessageFlags.Ephemeral });
  const result = await createTicket({
    guild: interaction.guild,
    member: interaction.member,
    topic,
    description: `**${isOrder ? 'Layanan' : 'Kategori'}:** ${label}\n\n${details}`,
  });

  if (!result.ok) {
    const messages = {
      'category-missing': 'Kategori tiket belum dikonfigurasi.',
      'already-open': `Anda masih memiliki tiket terbuka di <#${result.channelId}>.`,
      'rate-limited': 'Terlalu banyak tiket dibuat dalam waktu singkat. Coba lagi nanti.',
    };
    await interaction.editReply({ content: messages[result.reason] ?? 'Tiket tidak dapat dibuat.' });
    return;
  }

  await interaction.editReply({ content: `Tiket #${result.number} dibuat. Lanjutkan di <#${result.channelId}>.` });
}

async function handleTicketButton(interaction) {
  if (!isTicketChannel(interaction.channel)) {
    await interaction.reply({
      content: 'Tombol ini hanya berlaku di channel tiket.',
      flags: MessageFlags.Ephemeral,
    });
    return;
  }

  if (interaction.customId === CLAIM_BUTTON_ID) {
    if (!isStaff(interaction)) {
      await interaction.reply({
        content: 'Hanya staff yang dapat menangani tiket.',
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    await interaction.reply({
      content: `<@${interaction.user.id}> menangani tiket ini.`,
    });
    return;
  }

  if (interaction.customId === CLOSE_BUTTON_ID) {
    await interaction.reply({
      content: 'Menutup tiket...',
      flags: MessageFlags.Ephemeral,
    });

    await closeTicket({ channel: interaction.channel, closedBy: interaction.user });
  }
}

export default {
  name: Events.InteractionCreate,

  async execute(interaction, client) {
    try {
      if (interaction.isButton()) {
        if (interaction.customId.startsWith('ticket:')) {
          await handleTicketButton(interaction);
        } else if (interaction.customId.startsWith('panel:')) {
          await handlePanelButton(interaction);
        }

        return;
      }

      if (interaction.isModalSubmit()) {
        if (interaction.customId.startsWith(TOPUP_MODAL_PREFIX)) {
          await handleTopupModal(interaction);
        } else if (interaction.customId.startsWith('panel:')) {
          await handlePanelModal(interaction);
        }

        return;
      }

      if (interaction.isAutocomplete()) {
        const command = client.commands.get(interaction.commandName);
        if (command?.autocomplete) {
          await command.autocomplete(interaction);
        }
        return;
      }

      if (!interaction.isChatInputCommand()) {
        return;
      }

      const command = client.commands.get(interaction.commandName);

      if (!command) {
        log.warn({ command: interaction.commandName }, 'Command tidak terdaftar');

        await interaction.reply({
          content: 'Perintah ini tidak lagi tersedia. Jalankan `npm run deploy` untuk menyegarkan daftar perintah.',
          flags: MessageFlags.Ephemeral,
        });
        return;
      }

      await command.execute(interaction);
    } catch (error) {
      log.error({
        err: error.message,
        stack: error.stack,
        command: interaction.commandName ?? interaction.customId,
        user: interaction.user?.id,
      }, 'Interaksi gagal diproses');

      await respondWithError(
        interaction,
        'Perintah tidak dapat diselesaikan. Kendala sudah dicatat pada log bot.',
      );
    }
  },
};
