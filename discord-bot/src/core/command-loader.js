import { readdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

import { Collection } from 'discord.js';

import { childLogger } from './logger.js';

const log = childLogger('commands');
const here = dirname(fileURLToPath(import.meta.url));

/**
 * Memuat seluruh slash command dari direktori src/commands.
 *
 * Command yang tidak memiliki struktur lengkap dilewati dengan peringatan,
 * bukan menggagalkan proses startup keseluruhan.
 */
export async function loadCommands() {
  const commandsDir = join(here, '..', 'commands');
  const commands = new Collection();

  let entries;

  try {
    entries = await readdir(commandsDir, { withFileTypes: true });
  } catch (error) {
    if (error.code === 'ENOENT') {
      log.warn('Direktori commands tidak ditemukan.');
      return commands;
    }

    throw error;
  }

  for (const entry of entries) {
    if (!entry.isFile() || !entry.name.endsWith('.js')) {
      continue;
    }

    const modulePath = pathToFileURL(join(commandsDir, entry.name)).href;

    try {
      const module = await import(modulePath);
      const command = module.default ?? module.command;

      if (!command?.data?.name || typeof command.execute !== 'function') {
        log.warn({ file: entry.name }, 'Command dilewati karena struktur tidak lengkap');
        continue;
      }

      commands.set(command.data.name, command);
    } catch (error) {
      log.error({ file: entry.name, err: error.message }, 'Gagal memuat command');
    }
  }

  log.info({ total: commands.size, names: [...commands.keys()] }, 'Command dimuat');

  return commands;
}

export function commandPayloads(commands) {
  return [...commands.values()].map((command) => command.data.toJSON());
}
