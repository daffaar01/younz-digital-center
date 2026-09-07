import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import config from '../next.config.mjs';

const rules = await config.headers();
const globalRule = rules.find((rule) => rule.source === '/:path((?!__/auth/).*)');
assert.ok(globalRule, 'global security header rule with Firebase helper exclusion is required');
const globalHeaders = Object.fromEntries(globalRule.headers.map(({key, value}) => [key, value]));
assert.match(globalHeaders['Content-Security-Policy'] || '', /frame-ancestors 'none'/);
assert.equal(globalHeaders['X-Frame-Options'], 'DENY');
assert.equal(globalHeaders['Strict-Transport-Security'], 'max-age=31536000; includeSubDomains');
assert.equal(globalHeaders['X-Content-Type-Options'], 'nosniff');
assert.equal(globalHeaders['Referrer-Policy'], 'strict-origin-when-cross-origin');
assert.equal(globalHeaders['Permissions-Policy'], 'camera=(), microphone=(), geolocation=(), payment=()');

const accountRule = rules.find((rule) => rule.source === '/akun/:path*');
assert.ok(accountRule, 'account cache rule is required');
const accountHeaders = Object.fromEntries(accountRule.headers.map(({key, value}) => [key, value]));
assert.equal(accountHeaders['Cache-Control'], 'private, no-store, max-age=0, must-revalidate');

const accountLayout = await readFile(new URL('../app/akun/layout.tsx', import.meta.url), 'utf8');
assert.match(accountLayout, /export const dynamic = 'force-dynamic'/);
assert.match(accountLayout, /export const revalidate = 0/);

console.log('Frontend security hardening contract verified.');
