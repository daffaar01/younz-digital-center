/**
 * Mendaftarkan slash command ke Discord.
 *
 * Bila DISCORD_GUILD_ID diisi, command didaftarkan khusus untuk server itu
 * dan langsung tersedia. Tanpa guild ID, command didaftarkan global dan
 * propagasinya dapat memakan waktu hingga satu jam.
 */

import { REST, Routes } from 'discord.js';
import { getGlobalDispatcher } from 'undici';

import { assertCoreConfig, config } from './core/config.js';
import { commandPayloads, loadCommands } from './core/command-loader.js';
import { logger } from './core/logger.js';

try {
  assertCoreConfig();
} catch (error) {
  logger.fatal({ err: error.message }, 'Pendaftaran command dibatalkan');
  process.exit(1);
}

const commands = await loadCommands();

if (commands.size === 0) {
  logger.error('Tidak ada command yang dapat didaftarkan.');
  process.exit(1);
}

const payloads = commandPayloads(commands);
const rest = new REST({ version: '10' }).setToken(config.discord.token);

/**
 * Keluar secara natural setelah menutup koneksi keep-alive undici.
 *
 * process.exit() langsung di Windows memicu assertion libuv
 * (UV_HANDLE_CLOSING) karena koneksi keep-alive dispatcher masih
 * dalam proses ditutup saat proses dihentikan paksa. destroy() dipilih
 * karena ini murni jalur keluar: tidak menunggu request enqueued,
 * sehingga tidak berisiko menggantung pada koneksi yang macet.
 */
async function finishExit(code) {
  try {
    getGlobalDispatcher().destroy();
  } catch {
    // Dispatcher sudah ditutup atau request batal; lanjutkan keluar.
  }

  process.exitCode = code;
}

const scope = config.discord.guildId !== ''
  ? { route: Routes.applicationGuildCommands(config.discord.clientId, config.discord.guildId), label: `guild ${config.discord.guildId}` }
  : { route: Routes.applicationCommands(config.discord.clientId), label: 'global' };

try {
  const result = await rest.put(scope.route, { body: payloads });

  logger.info({
    total: Array.isArray(result) ? result.length : payloads.length,
    scope: scope.label,
    commands: payloads.map((command) => command.name),
  }, 'Slash command terdaftar');

  if (config.discord.guildId === '') {
    logger.warn('Pendaftaran global dapat memerlukan waktu hingga 1 jam untuk tampil.');
  }

  await finishExit(0);
} catch (error) {
  const detail = error.rawError?.message ?? error.message;

  logger.fatal({ err: detail, status: error.status }, 'Gagal mendaftarkan command');

  if (error.status === 401) {
    logger.error('Token ditolak. Periksa DISCORD_TOKEN.');
  }

  if (error.status === 403) {
    logger.error('Bot belum diundang ke server, atau DISCORD_CLIENT_ID tidak cocok dengan token.');
  }

  await finishExit(1);
}
