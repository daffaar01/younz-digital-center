import { SlashCommandBuilder } from 'discord.js';

import { BRAND, brandEmbed, formatUptime } from '../core/format.js';
import { featureSummary } from '../core/config.js';

export default {
  data: new SlashCommandBuilder()
    .setName('ping')
    .setDescription('Cek respons bot, latensi gateway, dan fitur yang aktif.'),

  async execute(interaction) {
    const sentAt = Date.now();

    await interaction.deferReply();

    const roundTrip = Date.now() - sentAt;
    const gateway = Math.max(0, Math.round(interaction.client.ws.ping));
    const features = featureSummary();

    const activeFeatures = Object.entries(features)
      .map(([name, enabled]) => `${enabled ? '🟢' : '⚪'} ${name}`)
      .join('\n');

    const embed = brandEmbed({
      title: 'Status Bot',
      color: gateway < 200 ? BRAND.success : BRAND.warning,
      fields: [
        { name: 'Latensi perintah', value: `${roundTrip} ms`, inline: true },
        { name: 'Latensi gateway', value: `${gateway} ms`, inline: true },
        { name: 'Aktif selama', value: formatUptime(interaction.client.uptime), inline: true },
        { name: 'Fitur', value: activeFeatures, inline: false },
      ],
    });

    await interaction.editReply({ embeds: [embed] });
  },
};
