import { MessageFlags, SlashCommandBuilder } from 'discord.js';

import { config } from '../core/config.js';
import { BRAND, brandEmbed, dangerEmbed, formatDateTime, formatRupiah, truncate, warningEmbed } from '../core/format.js';
import { childLogger } from '../core/logger.js';
import { SlidingWindowLimiter } from '../services/rate-limiter.js';
import { describeTrackError, trackOrder, trackTopupOrder } from '../services/younz.js';
import {
  appendHistory,
  askYounzAi,
  clearHistory,
  describeYounzAiError,
  getHistory,
  isYounzAiEnabled,
} from '../services/younz-ai.js';

const log = childLogger('command:younzai');

const limiter = new SlidingWindowLimiter({
  max: config.ai.maxRequestsPerWindow,
  windowMs: config.ai.requestWindowMs,
});

/** Pembatas terpisah untuk cekstatus: backend membatasi 20/menit per IP,
 * dan semua pengguna berbagi satu IP bot, jadi dijaga longgar di sisi bot. */
const trackLimiter = new SlidingWindowLimiter({
  max: 5,
  windowMs: 60_000,
});

/** Warna embed sesuai status pesanan dari backend. */
function statusColor(statusCode) {
  switch (statusCode) {
    case 'selesai':
      return BRAND.success;
    case 'dibatalkan':
      return BRAND.danger;
    case 'siap_diambil':
      return BRAND.info;
    case 'sedang_dikerjakan':
    case 'masuk_antrean':
      return BRAND.warning;
    default:
      return BRAND.primary;
  }
}

function priceField(label, value, inline = true) {
  return { name: label, value: value == null || value === 0 ? '-' : formatRupiah(value), inline };
}

function isTopupOrder(orderNumber) {
  return /^TOP-\d{8}-\d{4}$/i.test(orderNumber);
}

function topupStatusEmoji(statusCode) {
  if (statusCode === 'success') return '✅';
  if (statusCode === 'failed' || statusCode === 'refunded' || statusCode === 'cancelled') return '❌';
  if (statusCode === 'pending') return '⏳';
  return '📦';
}

async function handleCekStatusTopup(interaction, orderNumber, email, phone) {
  const order = await trackTopupOrder(orderNumber, email, phone);

  if (!order) {
    await interaction.editReply({
      embeds: [warningEmbed({
        title: 'Transaksi Tidak Ditemukan',
        description: 'Transaksi top up tidak ditemukan atau tautan akses tidak valid. Periksa kembali nomor pesanan, email, dan nomor WhatsApp.',
      })],
    });
    return;
  }

  const payment = order.payment_status ?? {};
  const fulfillment = order.fulfillment_status ?? {};
  const paid = payment.code === 'paid';

  const fields = [
    { name: 'Produk', value: order.product_name ?? '-', inline: false },
    { name: 'Tujuan', value: order.destination ?? '-', inline: true },
    { name: 'Pembayaran', value: `${paid ? '✅' : '⏳'} ${payment.label ?? payment.code ?? '-'}`, inline: true },
    { name: 'Pemenuhan', value: `${topupStatusEmoji(fulfillment.code)} ${fulfillment.label ?? fulfillment.code ?? '-'}`, inline: false },
    priceField('Harga Produk', order.selling_price),
    priceField('Biaya Admin', order.admin_fee),
    priceField('Total', order.total_amount),
    { name: 'Nomor Seri', value: order.serial_number ?? '-', inline: false },
    { name: 'Dibuat', value: order.created_at ? formatDateTime(order.created_at) : '-', inline: true },
    { name: 'Dibayar', value: order.paid_at ? formatDateTime(order.paid_at) : '-', inline: true },
  ];

  const embed = brandEmbed({
    title: `Status Top Up ${order.order_number ?? orderNumber}`,
    description: 'Data diambil langsung dari Younz Digital Center.',
    color: fulfillment.code === 'success'
      ? BRAND.success
      : fulfillment.code === 'failed' || fulfillment.code === 'refunded' || fulfillment.code === 'cancelled'
        ? BRAND.danger
        : BRAND.info,
    fields,
  });

  await interaction.editReply({ embeds: [embed] });
}

async function handleCekStatus(interaction) {
  const orderNumber = interaction.options.getString('nomor_pesanan', true).trim();
  const phone = interaction.options.getString('nomor_whatsapp', true).trim();

  if (orderNumber === '' || phone === '') {
    await interaction.reply({
      content: 'Nomor pesanan dan nomor WhatsApp tidak boleh kosong.',
      flags: MessageFlags.Ephemeral,
    });
    return;
  }

  const limit = trackLimiter.hit(interaction.user.id);

  if (limit.exceeded) {
    await interaction.reply({
      embeds: [warningEmbed({
        title: 'Terlalu Banyak Permintaan',
        description: 'Anda telah mencapai batas 5 permintaan cek status per menit. Coba lagi sebentar.',
      })],
      flags: MessageFlags.Ephemeral,
    });
    return;
  }

  await interaction.deferReply({ flags: MessageFlags.Ephemeral });

  try {
    if (isTopupOrder(orderNumber)) {
      const email = (interaction.options.getString('email') ?? '').trim();

      if (email === '') {
        await interaction.editReply({
          embeds: [warningEmbed({
            title: 'Email Diperlukan',
            description: 'Untuk mengecek transaksi top up, isi opsi `email` dengan email yang dipakai saat memesan.',
          })],
        });
        return;
      }

      await handleCekStatusTopup(interaction, orderNumber, email, phone);
      return;
    }

    const order = await trackOrder(orderNumber, phone);
    const status = order.status ?? {};
    const statusLabel = status.label ?? status.code ?? '-';
    const emoji = status.code === 'selesai' ? '✅' : status.code === 'dibatalkan' ? '❌' : '📦';

    const fields = [
      { name: 'Layanan', value: order.service ?? '-', inline: false },
      { name: 'Status', value: `${emoji} ${statusLabel}`, inline: true },
      priceField('Estimasi', order.estimated_price),
      priceField('Harga Final', order.final_price),
      priceField('Terbayar', order.paid_amount),
      { name: 'Dibuat', value: order.created_at ? formatDateTime(order.created_at) : '-', inline: true },
      { name: 'Deadline', value: order.deadline_at ? formatDateTime(order.deadline_at) : '-', inline: true },
      { name: 'Siap Diambil', value: order.pickup_at ? formatDateTime(order.pickup_at) : '-', inline: true },
    ];

    const embed = brandEmbed({
      title: `Status Pesanan ${order.order_number ?? orderNumber}`,
      description: 'Data diambil langsung dari Younz Digital Center.',
      color: statusColor(status.code),
      fields,
    });

    await interaction.editReply({ embeds: [embed] });
  } catch (error) {
    log.error({ err: error.message, orderNumber, user: interaction.user.id }, 'Cek status pesanan gagal');

    await interaction.editReply({
      embeds: [dangerEmbed({
        title: 'Gagal Mengecek Status',
        description: describeTrackError(error),
      })],
    });
  }
}

function notConfiguredEmbed() {
  return warningEmbed({
    title: 'Younz AI belum aktif',
    description: 'Isi `OPENAI_COMPATIBLE_URL` dan `OPENAI_COMPATIBLE_API_KEY` pada file `.env`.',
  });
}

export default {
  data: new SlashCommandBuilder()
    .setName('younzai')
    .setDescription('Tanya Younz AI, asisten virtual Younz Digital Center.')
    .addSubcommand((sub) => sub
      .setName('tanya')
      .setDescription('Ajukan pertanyaan ke Younz AI.')
      .addStringOption((option) => option
        .setName('pertanyaan')
        .setDescription('Pertanyaan Anda.')
        .setRequired(true)
        .setMinLength(2)
        .setMaxLength(config.ai.maxMessageCharacters)))
    .addSubcommand((sub) => sub
      .setName('hapus')
      .setDescription('Hapus riwayat percakapan Anda dengan Younz AI.'))
    .addSubcommand((sub) => sub
      .setName('cekstatus')
      .setDescription('Cek status pesanan layanan atau top up Anda di Younz Digital Center.')
      .addStringOption((option) => option
        .setName('nomor_pesanan')
        .setDescription('Nomor pesanan, contoh: YD-20260807-001 atau TOP-20260807-0001')
        .setRequired(true)
        .setMaxLength(30))
      .addStringOption((option) => option
        .setName('nomor_whatsapp')
        .setDescription('Nomor WhatsApp yang dipakai saat memesan, contoh: 082123456789')
        .setRequired(true)
        .setMaxLength(30))
      .addStringOption((option) => option
        .setName('email')
        .setDescription('Email yang dipakai saat top up (wajib untuk nomor TOP-).')
        .setRequired(false)
        .setMaxLength(150))),

  async execute(interaction) {
    const userId = interaction.user.id;
    const subcommand = interaction.options.getSubcommand();

    if (subcommand === 'hapus') {
      clearHistory(userId);

      await interaction.reply({
        embeds: [brandEmbed({
          title: 'Riwayat Percakapan Dihapus',
          description: 'Percakapan Anda dengan Younz AI telah dihapus.',
          color: BRAND.success,
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    if (subcommand === 'cekstatus') {
      await handleCekStatus(interaction);
      return;
    }

    if (!isYounzAiEnabled()) {
      await interaction.reply({
        embeds: [notConfiguredEmbed()],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const question = interaction.options.getString('pertanyaan', true).trim();

    if (question === '') {
      await interaction.reply({
        content: 'Pertanyaan tidak boleh kosong.',
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    const limit = limiter.hit(userId);

    if (limit.exceeded) {
      await interaction.reply({
        embeds: [warningEmbed({
          title: 'Terlalu Banyak Pertanyaan',
          description: `Anda telah mencapai batas ${config.ai.maxRequestsPerWindow} pertanyaan per menit. Coba lagi sebentar.`,
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    try {
      const history = getHistory(userId);
      const result = await askYounzAi(question, history);

      appendHistory(userId, question, result.answer);

      const description = result.answer.length > 4000
        ? `${truncate(result.answer, 4000 - 130)}\n\n_…jawaban dipotong karena terlalu panjang._`
        : result.answer;

      const embed = brandEmbed({
        title: 'Younz AI',
        description,
        color: BRAND.primary,
        footer: `Dijawab oleh ${result.model} · Younz Digital Center`,
      });

      await interaction.editReply({ embeds: [embed] });
    } catch (error) {
      log.error({ err: error.message, user: userId, question: question.slice(0, 120) }, 'Younz AI gagal menjawab');

      await interaction.editReply({
        embeds: [dangerEmbed({
          title: 'Gagal Menjawab',
          description: describeYounzAiError(error),
        })],
      });
    }
  },
};
