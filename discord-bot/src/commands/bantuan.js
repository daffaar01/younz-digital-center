import { MessageFlags, SlashCommandBuilder } from 'discord.js';

import { BRAND, brandEmbed } from '../core/format.js';
import { config, featureSummary } from '../core/config.js';
import { isStaff } from '../core/permissions.js';

export default {
  data: new SlashCommandBuilder()
    .setName('bantuan')
    .setDescription('Daftar perintah yang tersedia beserta kegunaannya.'),

  async execute(interaction) {
    const staff = isStaff(interaction);
    const features = featureSummary();

    const publicSection = [
      '`/bantuan` — menampilkan panduan ini',
      '`/ping` — cek respons dan fitur bot',
      features.younzAi ? '`/younzai tanya` — tanya Younz AI, asisten virtual toko' : null,
      '`/younzai cekstatus` — cek status pesanan layanan atau top up',
      features.tickets ? '`/tiket buka` — buka tiket support privat' : null,
      features.tickets ? '`/tiket tutup` — tutup tiket pada channel saat ini' : null,
    ].filter(Boolean);

    const fields = [
      { name: 'Untuk semua anggota', value: publicSection.join('\n'), inline: false },
    ];

    if (staff) {
      const younzSection = features.younz
        ? [
          '`/younz ringkasan` — pendapatan, pesanan, approval hari ini',
          '`/younz stok` — produk dengan stok menipis',
          '`/younz transaksi` — sepuluh transaksi terakhir',
          '`/younz health` — cek situs publik dan portal admin',
        ].join('\n')
        : 'Belum aktif. Isi `YOUNZ_API_URL` dan `YOUNZ_API_TOKEN`.';

      const moderationSection = [
        '`/moderasi peringatan` — beri peringatan tercatat',
        '`/moderasi riwayat` — lihat riwayat peringatan',
        '`/moderasi timeout` — bisukan sementara',
        '`/moderasi kick` — keluarkan anggota',
        '`/moderasi ban` — blokir anggota',
        '`/moderasi bersihkan` — hapus pesan massal',
      ].join('\n');

      fields.push(
        { name: 'Younz Digital Center', value: younzSection, inline: false },
        { name: 'Moderasi', value: moderationSection, inline: false },
      );
    }

    if (!staff && config.roles.staff !== '') {
      fields.push({
        name: 'Perintah staff',
        value: `Perintah operasional hanya tersedia untuk pemegang role <@&${config.roles.staff}>.`,
        inline: false,
      });
    }

    const embed = brandEmbed({
      title: 'Panduan Bot Younz Digital Center',
      description: 'Semua perintah memakai slash command. Ketik `/` pada kolom pesan untuk melihat daftar lengkap.',
      color: BRAND.primary,
      fields,
    });

    await interaction.reply({ embeds: [embed], flags: MessageFlags.Ephemeral });
  },
};
