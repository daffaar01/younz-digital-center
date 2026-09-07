import { ActivityType, Events } from 'discord.js';

import { featureSummary } from '../core/config.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('event:ready');

export default {
  name: Events.ClientReady,
  once: true,

  async execute(client) {
    client.user.setPresence({
      status: 'online',
      activities: [{
        name: 'Younz Digital Center',
        type: ActivityType.Watching,
      }],
    });

    log.info({
      tag: client.user.tag,
      guilds: client.guilds.cache.size,
      commands: client.commands.size,
      features: featureSummary(),
    }, 'Bot siap');
  },
};
