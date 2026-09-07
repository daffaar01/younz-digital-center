'use client';

import {
  createPurchasePayload,
  createTransactionPayload,
  type PurchaseInput,
  type TransactionInput,
} from './younz-erp-api';

const STORAGE_KEY = 'younz_offline_queue';

export type OfflineOperation = {
  id: string;
  kind: 'transaction' | 'purchase';
  token: string;
  businessId: string;
  payload: TransactionInput | PurchaseInput;
  createdAt: string;
};

function readQueue(): OfflineOperation[] {
  if (typeof window === 'undefined') return [];
  try {
    const value = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]');
    return Array.isArray(value) ? (value as OfflineOperation[]) : [];
  } catch {
    return [];
  }
}

function writeQueue(queue: OfflineOperation[]) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
  window.dispatchEvent(new Event('younz-offline-queue-changed'));
}

export function getOfflineQueueCount() {
  return readQueue().length;
}

export function enqueueOfflineOperation(
  kind: OfflineOperation['kind'],
  token: string,
  businessId: string,
  payload: OfflineOperation['payload'],
) {
  const queue = readQueue();
  queue.push({
    id: crypto.randomUUID(),
    kind,
    token,
    businessId,
    payload,
    createdAt: new Date().toISOString(),
  });
  writeQueue(queue);
  return queue.length;
}

export function isNetworkError(reason: unknown) {
  if (!(reason instanceof Error)) return false;
  return reason.name === 'TypeError' || /failed to fetch|network|load failed|timed out/i.test(reason.message);
}

export async function syncOfflineQueue(token: string, businessId: string) {
  const queue = readQueue();
  const remaining: OfflineOperation[] = [];
  let synced = 0;
  let discarded = 0;

  for (const operation of queue) {
    if (operation.businessId !== businessId) {
      remaining.push(operation);
      continue;
    }

    try {
      if (operation.kind === 'transaction') {
        await createTransactionPayload(token, businessId, operation.payload as TransactionInput);
      } else {
        await createPurchasePayload(token, businessId, operation.payload as PurchaseInput);
      }
      synced += 1;
    } catch (reason) {
      if (isNetworkError(reason)) {
        remaining.push(operation, ...queue.slice(queue.indexOf(operation) + 1));
        break;
      }
      discarded += 1;
    }
  }

  writeQueue(remaining);
  window.dispatchEvent(new Event('younz-offline-queue-synced'));
  return { synced, discarded, remaining: remaining.length };
}
