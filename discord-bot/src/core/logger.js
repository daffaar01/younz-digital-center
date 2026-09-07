import pino from 'pino';

import { config } from './config.js';

/**
 * Logger terstruktur.
 *
 * Field yang berpotensi memuat kredensial diredaksi otomatis supaya token
 * tidak pernah bocor ke log panel hosting.
 */
export const logger = pino({
  level: config.logLevel,
  redact: {
    paths: [
      'token',
      'apiKey',
      'authorization',
      'headers.authorization',
      'headers.Authorization',
      '*.token',
      '*.apiKey',
      '*.password',
      '*.secret',
    ],
    censor: '[REDACTED]',
  },
  base: undefined,
  timestamp: pino.stdTimeFunctions.isoTime,
});

export function childLogger(component) {
  return logger.child({ component });
}
