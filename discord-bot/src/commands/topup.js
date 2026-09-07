import {
  ActionRowBuilder,
  ModalBuilder,
  SlashCommandBuilder,
  TextInputBuilder,
  TextInputStyle,
} from 'discord.js';

import { formatRupiah } from '../core/format.js';
import { searchTopupCatalog } from '../services/younz.js';

export const TOPUP_MODAL_PREFIX = 'topup:checkout:';

export default {
  data: new SlashCommandBuilder()
    .setName('topup')
    .setDescription('Pesan pulsa, paket data, token PLN, dan PPOB langsung dari Discord.')
    .addStringOption((option) => option
      .setName('jenis')
      .setDescription('Pilih prabayar atau pascabayar.')
      .setRequired(true)
      .addChoices(
        { name: 'Prabayar — pulsa, data, token PLN, top up game', value: 'prepaid' },
        { name: 'Pascabayar — tagihan listrik, PDAM, internet, dan lainnya', value: 'postpaid' },
      ))
    .addStringOption((option) => option
      .setName('produk')
      .setDescription('Cari dan pilih produk PPOB.')
      .setRequired(true)
      .setAutocomplete(true)),

  async autocomplete(interaction) {
    const query = interaction.options.getFocused();
    const mode = interaction.options.getString('jenis') ?? 'prepaid';

    try {
      const products = await searchTopupCatalog(query, mode);
      await interaction.respond(products.slice(0, 25).map((product) => ({
        name: `${product.product_name} — ${formatRupiah(product.selling_price)}`.slice(0, 100),
        value: String(product.id),
      })));
    } catch {
      await interaction.respond([]);
    }
  },

  async execute(interaction) {
    const productId = interaction.options.getString('produk', true);
    const mode = interaction.options.getString('jenis', true);

    if (!/^\d+$/.test(productId)) {
      await interaction.reply({ content: 'Pilih produk dari daftar yang tersedia.', ephemeral: true });
      return;
    }

    const inputs = [
      ['destination', 'Nomor / ID Tujuan', 'Contoh: 082123456789', TextInputStyle.Short],
      ['name', 'Nama Lengkap', 'Nama penerima pesanan', TextInputStyle.Short],
      ['email', 'Email', 'contoh@email.com', TextInputStyle.Short],
      ['phone', 'Nomor WhatsApp', 'Contoh: 082123456789', TextInputStyle.Short],
    ];

    const modal = new ModalBuilder()
      .setCustomId(`${TOPUP_MODAL_PREFIX}${mode}:${productId}`)
      .setTitle('Checkout PPOB');

    modal.addComponents(...inputs.map(([id, label, placeholder, style]) => (
      new ActionRowBuilder().addComponents(
        new TextInputBuilder()
          .setCustomId(id)
          .setLabel(label)
          .setPlaceholder(placeholder)
          .setStyle(style)
          .setRequired(true),
      )
    )));

    await interaction.showModal(modal);
  },
};
