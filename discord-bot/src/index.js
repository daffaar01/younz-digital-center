import { Client, GatewayIntentBits, Partials } from 'discord.js';

import { assertCoreConfig, config, featureSummary } from './core/config.js';
import { loadCommands } from './core/command-loader.js';
import { registerEvents } from './core/event-loader.js';
import { logger } from './core/logger.js';
import { startWebhookServer } from './services/webhook-server.js';

try {
  assertCoreConfig();
} catch (error) {
  logger.fatal({ err: error.message }, 'Bot tidak dapat dijalankan');
  process.exit(1);
}

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMembers,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent,
  ],
  partials: [Partials.Channel, Partials.GuildMember],
  allowedMentions: { parse: ['users', 'roles'], repliedUser: false },
});

client.commands = await loadCommands();

await registerEvents(client);

let stopWebhook = null;

try {
  stopWebhook = await startWebhookServer(client);
} catch (error) {
  logger.fatal({ err: error.message }, 'Server webhook gagal dijalankan. Bot dihentikan.');

  await client.destroy().catch(() => undefined);

  process.exit(1);
}

let shuttingDown = false;

async function shutdown(signal) {
  if (shuttingDown) {
    return;
  }

  shuttingDown = true;
  logger.info({ signal }, 'Mematikan bot');

  const timer = setTimeout(() => {
    logger.warn('Shutdown melewati batas waktu, keluar paksa');
    process.exit(1);
  }, 10_000);

  timer.unref();

  try {
    if (stopWebhook) {
      await stopWebhook();
    }

    await client.destroy();
    logger.info('Bot berhenti dengan bersih');
    process.exit(0);
  } catch (error) {
    logger.error({ err: error.message }, 'Gagal mematikan bot dengan bersih');
    process.exit(1);
  }
}

process.on('SIGINT', () => void shutdown('SIGINT'));
process.on('SIGTERM', () => void shutdown('SIGTERM'));

process.on('unhandledRejection', (reason) => {
  logger.error({ err: reason instanceof Error ? reason.message : String(reason) }, 'Promise rejection tidak tertangani');
});

process.on('uncaughtException', (error) => {
  logger.fatal({ err: error.message, stack: error.stack }, 'Exception tidak tertangani');
  void shutdown('uncaughtException');
});

client.on('error', (error) => {
  logger.error({ err: error.message }, 'Error klien Discord');
});

client.on('warn', (message) => {
  logger.warn({ message }, 'Peringatan klien Discord');
});

logger.info({ features: featureSummary(), guild: config.discord.guildId || 'global' }, 'Menghubungkan ke Discord');

try {
  await client.login(config.discord.token);
} catch (error) {
  logger.fatal({ err: error.message }, 'Login gagal. Periksa DISCORD_TOKEN.');

  if (stopWebhook) {
    await stopWebhook().catch(() => undefined);
  }

  await client.destroy().catch(() => undefined);

  process.exitCode = 1;
}
