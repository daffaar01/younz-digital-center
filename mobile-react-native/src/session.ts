import * as SecureStore from 'expo-secure-store';

import type { ApiSession } from './api';

export type SessionKind = 'customer' | 'staff';

export type StoredSession = ApiSession & {
  kind: SessionKind;
};

const SESSION_KEY = 'younz.mobile.session.v1';

export async function loadSession(): Promise<StoredSession | null> {
  const raw = await SecureStore.getItemAsync(SESSION_KEY);
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw) as StoredSession;
    return parsed.token && parsed.user ? parsed : null;
  } catch {
    return null;
  }
}

export function saveSession(session: StoredSession) {
  return SecureStore.setItemAsync(SESSION_KEY, JSON.stringify(session));
}

export function clearSession() {
  return SecureStore.deleteItemAsync(SESSION_KEY);
}
