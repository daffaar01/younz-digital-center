import { MessageFlags, SlashCommandBuilder } from 'discord.js';

import {
  BRAND,
  brandEmbed,
  dangerEmbed,
  formatDateTime,
  formatNumber,
  formatRupiah,
  truncate,
  warningEmbed,
} from '../core/format.js';
import { DENIED_MESSAGE, isStaff } from '../core/permissions.js';
import { describeYounzError, fetchDashboard, fetchHealth, isYounzEnabled } from '../services/younz.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('command:younz');

async function handleRingkasan(interaction) {
  const dashboard = await fetchDashboard();
  const summary = dashboard.summary;

  const attention = [];
  if (summary.lateOrders > 0) attention.push(`${summary.lateOrders} pesanan lewat deadline`);
  if (summary.failedDigital > 0) attention.push(`${summary.failedDigital} transaksi digital gagal`);
  if (summary.pendingApprovals > 0) attention.push(`${summary.pendingApprovals} approval menunggu`);

  const netIncome = summary.todayRevenue - summary.todayExpenses;

  const embed = brandEmbed({
    title: 'Ringkasan Hari Ini',
    description: `Data diambil sebagai **${dashboard.user.name}** (${dashboard.user.role}).`,
    color: attention.length > 0 ? BRAND.warning : BRAND.success,
    fields: [
      { name: 'Pendapatan', value: formatRupiah(summary.todayRevenue), inline: true },
      { name: 'Pengeluaran', value: formatRupiah(summary.todayExpenses), inline: true },
      { name: 'Selisih', value: formatRupiah(netIncome), inline: true },
      { name: 'Transaksi selesai', value: formatNumber(summary.todayTransactions), inline: true },
      { name: 'Pesanan aktif', value: formatNumber(summary.activeOrders), inline: true },
      { name: 'Approval menunggu', value: formatNumber(summary.pendingApprovals), inline: true },
      {
        name: 'Perlu perhatian',
        value: attention.length > 0 ? attention.map((item) => `⚠️ ${item}`).join('\n') : '✅ Tidak ada masalah.',
        inline: false,
      },
    ],
  });

  await interaction.editReply({ embeds: [embed] });
}

async function handleStok(interaction) {
  const dashboard = await fetchDashboard();

  if (dashboard.lowStock.length === 0) {
    await interaction.editReply({
      embeds: [brandEmbed({
        title: 'Stok Produk',
        description: '✅ Semua stok berada dalam batas aman.',
        color: BRAND.success,
      })],
    });
    return;
  }

  const fields = dashboard.lowStock.slice(0, 12).map((product) => ({
    name: truncate(product.name, 250),
    value: `SKU \`${product.sku}\`\nStok **${formatNumber(product.stock)}** dari minimum ${formatNumber(product.minimumStock)}`,
    inline: true,
  }));

  await interaction.editReply({
    embeds: [brandEmbed({
      title: `Stok Menipis (${dashboard.lowStock.length})`,
      description: 'Produk berikut berada pada atau di bawah batas minimum.',
      color: BRAND.warning,
      fields,
    })],
  });
}

async function handleTransaksi(interaction) {
  const dashboard = await fetchDashboard();

  if (dashboard.recentSales.length === 0) {
    await interaction.editReply({
      embeds: [brandEmbed({
        title: 'Transaksi Terbaru',
        description: 'Belum ada transaksi yang tercatat.',
        color: BRAND.neutral,
      })],
    });
    return;
  }

  const lines = dashboard.recentSales.slice(0, 10).map((sale) => {
    const when = sale.completedAt ? formatDateTime(sale.completedAt) : '-';

    return `\`${sale.invoiceNumber}\` · ${formatRupiah(sale.total)} · ${sale.cashier} · ${when}`;
  });

  await interaction.editReply({
    embeds: [brandEmbed({
      title: 'Transaksi Terbaru',
      description: lines.join('\n'),
      color: BRAND.info,
    })],
  });
}

async function handleHealth(interaction) {
  const results = await fetchHealth();
  const allHealthy = results.every((result) => result.ok);

  const fields = results.map((result) => ({
    name: result.label,
    value: result.ok
      ? `✅ HTTP ${result.status} · ${result.latencyMs} ms`
      : `❌ ${result.status === 0 ? 'tidak merespons' : `HTTP ${result.status}`}`,
    inline: true,
  }));

  await interaction.editReply({
    embeds: [brandEmbed({
      title: 'Kesehatan Layanan',
      color: allHealthy ? BRAND.success : BRAND.danger,
      fields,
    })],
  });
}

export default {
  data: new SlashCommandBuilder()
    .setName('younz')
    .setDescription('Pantau operasional Younz Digital Center.')
    .addSubcommand((sub) => sub
      .setName('ringkasan')
      .setDescription('Pendapatan, pesanan, dan approval hari ini.'))
    .addSubcommand((sub) => sub
      .setName('stok')
      .setDescription('Daftar produk dengan stok menipis.'))
    .addSubcommand((sub) => sub
      .setName('transaksi')
      .setDescription('Sepuluh transaksi terakhir.'))
    .addSubcommand((sub) => sub
      .setName('health')
      .setDescription('Cek apakah situs publik dan portal admin merespons.')),

  async execute(interaction) {
    if (!isStaff(interaction)) {
      await interaction.reply({ content: DENIED_MESSAGE, flags: MessageFlags.Ephemeral });
      return;
    }

    const subcommand = interaction.options.getSubcommand();

    if (subcommand !== 'health' && !isYounzEnabled()) {
      await interaction.reply({
        embeds: [warningEmbed({
          title: 'Integrasi belum dikonfigurasi',
          description: 'Isi `YOUNZ_API_URL` dan `YOUNZ_API_TOKEN` pada file `.env`.',
        })],
        flags: MessageFlags.Ephemeral,
      });
      return;
    }

    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    try {
      if (subcommand === 'ringkasan') {
        await handleRingkasan(interaction);
        return;
      }

      if (subcommand === 'stok') {
        await handleStok(interaction);
        return;
      }

      if (subcommand === 'transaksi') {
        await handleTransaksi(interaction);
        return;
      }

      if (subcommand === 'health') {
        await handleHealth(interaction);
        return;
      }

      await interaction.editReply({ content: 'Subperintah tidak dikenal.' });
    } catch (error) {
      log.error({ err: error.message, subcommand }, 'Perintah younz gagal');

      await interaction.editReply({
        embeds: [dangerEmbed({
          title: 'Gagal mengambil data',
          description: describeYounzError(error),
        })],
      });
    }
  },
};
