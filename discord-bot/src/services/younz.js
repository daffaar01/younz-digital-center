/**
 * Klien API Younz Digital Center.
 *
 * Memakai token Sanctum milik akun staff untuk membaca ringkasan operasional.
 * Semua permintaan bersifat baca saja agar bot tidak dapat mengubah data
 * bisnis dari Discord.
 */

import { config } from '../core/config.js';
import { HttpError, requestJson } from '../core/http.js';
import { childLogger } from '../core/logger.js';

const log = childLogger('younz-api');

function headers() {
  return {
    Authorization: `Bearer ${config.younz.token}`,
    Accept: 'application/json',
  };
}

export function isYounzEnabled() {
  return config.younz.enabled;
}

/**
 * Mengambil ringkasan dashboard staff: pendapatan, pesanan, approval, stok.
 */
export async function fetchDashboard() {
  const payload = await requestJson(`${config.younz.url}/backend/v1/staff/dashboard`, {
    headers: headers(),
  });

  const data = payload?.data ?? {};
  const summary = data.summary ?? {};

  return {
    user: {
      name: data.user?.name ?? '-',
      role: data.user?.role?.label ?? '-',
    },
    summary: {
      todayRevenue: Number(summary.today_revenue ?? 0),
      todayExpenses: Number(summary.today_expenses ?? 0),
      todayTransactions: Number(summary.today_transactions ?? 0),
      activeOrders: Number(summary.active_orders ?? 0),
      lateOrders: Number(summary.late_orders ?? 0),
      failedDigital: Number(summary.failed_digital_transactions ?? 0),
      pendingApprovals: Number(summary.pending_approvals ?? 0),
    },
    lowStock: Array.isArray(data.low_stock_products)
      ? data.low_stock_products.map((item) => ({
        name: item.name ?? '-',
        sku: item.sku ?? '-',
        stock: Number(item.stock ?? 0),
        minimumStock: Number(item.minimum_stock ?? 0),
      }))
      : [],
    recentSales: Array.isArray(data.recent_sales)
      ? data.recent_sales.map((item) => ({
        invoiceNumber: item.invoice_number ?? '-',
        cashier: item.cashier ?? '-',
        status: item.status ?? '-',
        total: Number(item.total ?? 0),
        completedAt: item.completed_at ?? null,
      }))
      : [],
  };
}

/**
 * Memeriksa kesehatan situs publik dan portal admin.
 */
export async function fetchHealth() {
  const checks = [
    { label: 'Situs publik', url: 'https://younzdigitalcenter.my.id/' },
    { label: 'Portal admin', url: `${config.younz.url}/admin/masuk` },
  ];

  const results = await Promise.all(checks.map(async (check) => {
    const startedAt = Date.now();

    try {
      const response = await fetch(check.url, {
        method: 'GET',
        redirect: 'manual',
        signal: AbortSignal.timeout(10_000),
      });

      return {
        label: check.label,
        ok: response.status >= 200 && response.status < 400,
        status: response.status,
        latencyMs: Date.now() - startedAt,
      };
    } catch (error) {
      log.warn({ url: check.url, err: error.message }, 'Health check gagal');

      return {
        label: check.label,
        ok: false,
        status: 0,
        latencyMs: Date.now() - startedAt,
      };
    }
  }));

  return results;
}

export async function fetchServices() {
  const payload = await requestJson(`${config.younz.url}/api/v1/services`, {
    headers: { Accept: 'application/json' },
  });
  return payload?.data ?? [];
}

export async function searchTopupCatalog(query, mode = 'prepaid') {
  const url = new URL(`${config.younz.url}/api/v1/topup/catalog`);
  url.searchParams.set('mode', mode);
  if (query) {
    url.searchParams.set('q', query);
  }

  const payload = await requestJson(url.toString(), {
    headers: { Accept: 'application/json' },
  });

  return payload?.data?.products ?? [];
}

export async function createTopupOrder(order) {
  return requestJson(`${config.younz.url}/api/v1/topup/checkout`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(order),
    retries: 0,
  });
}

/**
 * Melacak status pesanan layanan publik via nomor pesanan dan nomor HP.
 *
 * Melempar HttpError 404 bila pesanan tidak ditemukan.
 */
export async function trackOrder(orderNumber, phone) {
  const payload = await requestJson(`${config.younz.url}/api/v1/public/orders/track`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_number: orderNumber,
      phone,
    }),
    retries: 0,
  });

  return payload?.data ?? null;
}

export function describeTrackError(error) {
  if (!(error instanceof HttpError)) {
    return 'Server Younz Digital Center tidak dapat dihubungi.';
  }

  if (error.status === 404) {
    return 'Pesanan tidak ditemukan. Periksa kembali nomor pesanan dan nomor WhatsApp.';
  }

  if (error.status === 422) {
    return [
      'Data transaksi tidak cocok. Periksa kembali nomor pesanan, email, dan nomor WhatsApp.',
      '',
      '💡 Salindia (copy) data persis dari struk, email, atau pesan WhatsApp saat kamu memesan — jangan diketik ulang.',
    ].join('\n');
  }

  if (error.status === 429) {
    return 'Terlalu banyak permintaan. Coba lagi sebentar.';
  }

  return `Server merespons dengan status ${error.status}.`;
}

/**
 * Melacak status pesanan top up via endpoint akses publik.
 *
 * POST /topup/access memvalidasi order_number + email + phone lalu
 * mengembalikan redirect_url (signed URL); URL tersebut di-follow untuk
 * mengambil status terkini pesanan.
 */
export async function trackTopupOrder(orderNumber, email, phone) {
  const payload = await requestJson(`${config.younz.url}/api/v1/topup/access`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_number: orderNumber,
      customer_email: email,
      customer_phone: phone,
    }),
    retries: 0,
  });

  const redirectUrl = payload?.redirect_url;

  if (typeof redirectUrl !== 'string' || !/^https?:\/\//i.test(redirectUrl)) {
    return null;
  }

  // Signed URL menunjuk ke domain publik yang dilayani Next.js (HTML),
  // bukan JSON. Karena signature memakai signed:relative, host dapat
  // diganti ke backend Laravel langsung untuk mendapat JSON status.
  const signed = new URL(redirectUrl);
  const statusUrl = new URL(`${signed.pathname}${signed.search}`, config.younz.backendUrl);

  const statusPayload = await requestJson(statusUrl.toString(), {
    headers: { Accept: 'application/json' },
    retries: 0,
  });

  return statusPayload?.data ?? null;
}

export function describeYounzError(error) {
  if (!(error instanceof HttpError)) {
    return 'Server Younz Digital Center tidak dapat dihubungi.';
  }

  switch (error.status) {
    case 401:
      return 'Token API ditolak. Perbarui YOUNZ_API_TOKEN dengan token Sanctum yang masih berlaku.';
    case 403:
      return 'Token tidak memiliki kewenangan membaca dashboard staff.';
    case 404:
      return 'Endpoint dashboard tidak ditemukan. Periksa YOUNZ_API_URL.';
    default:
      return `Server merespons dengan status ${error.status}.`;
  }
}
