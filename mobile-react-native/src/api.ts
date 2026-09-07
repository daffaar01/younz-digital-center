export type ApiUser = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role?: { code?: string; label?: string } | string;
};

export type ApiSession = {
  token: string;
  user: ApiUser;
};

export type ApiResult<T> = {
  data?: T;
  message?: string;
  errors?: Record<string, string[] | string>;
  redirect_url?: string;
};

export class ApiError extends Error {
  status: number;
  payload: ApiResult<unknown>;

  constructor(status: number, payload: ApiResult<unknown>) {
    super(readApiMessage(payload) || `Permintaan gagal (${status}).`);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

const API_URL = (process.env.EXPO_PUBLIC_API_URL || 'http://10.0.2.2:8080').replace(/\/$/, '');

function readApiMessage(payload: ApiResult<unknown>): string | null {
  if (typeof payload.message === 'string' && payload.message.trim()) return payload.message;

  if (payload.errors) {
    for (const value of Object.values(payload.errors)) {
      const message = Array.isArray(value) ? value[0] : value;
      if (typeof message === 'string' && message.trim()) return message;
    }
  }

  return null;
}

async function request<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  headers.set('Content-Type', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);

  let response: Response;
  try {
    response = await fetch(`${API_URL}${path}`, { ...options, headers });
  } catch {
    throw new Error('Server tidak dapat dihubungi. Periksa EXPO_PUBLIC_API_URL dan koneksi jaringan.');
  }

  const payload = (await response.json().catch(() => ({}))) as ApiResult<T>;
  if (!response.ok) throw new ApiError(response.status, payload as ApiResult<unknown>);

  return payload as T;
}

export const api = {
  async customerLogin(email: string, password: string): Promise<ApiSession> {
    const response = await request<ApiResult<ApiSession>>('/api/v1/auth/customer-login', {
      method: 'POST',
      body: JSON.stringify({ email, password, device_name: 'Younz Android Customer' }),
    });
    if (!response.data?.token || !response.data.user) throw new Error('Respons login customer tidak lengkap.');
    return response.data;
  },

  async staffLogin(email: string, password: string, accessCode: string, twoFactorCode: string): Promise<ApiSession> {
    const response = await request<ApiResult<ApiSession>>('/api/v1/staff/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        access_code: accessCode || undefined,
        two_factor_code: twoFactorCode || undefined,
        device_name: 'Younz Android Staff',
      }),
    });
    if (!response.data?.token || !response.data.user) {
      if ((response.data as { requires_two_factor_setup?: boolean } | undefined)?.requires_two_factor_setup) {
        throw new Error('Akun staf perlu aktivasi 2FA terlebih dahulu melalui web.');
      }
      throw new Error('Respons login staf tidak lengkap.');
    }
    return response.data;
  },

  logout(token: string) {
    return request<ApiResult<unknown>>('/api/v1/auth/logout', { method: 'POST' }, token);
  },

  site() {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/site');
  },

  digitalProducts() {
    return request<ApiResult<unknown[]>>('/api/v1/digital-products');
  },

  topupCatalog(mode = 'prepaid') {
    return request<ApiResult<{ products?: unknown[]; categories?: string[]; brands?: string[] }>>(`/api/v1/topup/catalog?mode=${encodeURIComponent(mode)}`);
  },

  topupCheckout(payload: Record<string, unknown>) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/topup/checkout', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  customerPortal(token: string) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/customer/portal', {}, token);
  },

  staffDashboard(token: string) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/staff/dashboard', {}, token);
  },

  staffDigitalTransactions(token: string) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/staff/digital-transactions', {}, token);
  },

  staffOrders(token: string) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/staff/orders', {}, token);
  },

  staffDailyReport(token: string) {
    return request<ApiResult<Record<string, unknown>>>('/api/v1/staff/reports/daily', {}, token);
  },
};

export function apiErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  return 'Terjadi kesalahan yang tidak diketahui.';
}

export function apiData<T>(response: ApiResult<T> | null | undefined): T | null {
  return response?.data ?? null;
}

export function asArray(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? value.filter((item): item is Record<string, unknown> => !!item && typeof item === 'object') : [];
}

export function valueOf(item: Record<string, unknown>, ...keys: string[]): unknown {
  for (const key of keys) {
    if (item[key] !== undefined && item[key] !== null) return item[key];
  }
  return null;
}

export function displayValue(value: unknown, fallback = '-') {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'object' && value !== null && 'label' in value) return String((value as { label?: unknown }).label || fallback);
  return String(value);
}

export function formatRupiah(value: unknown): string {
  const number = Number(value || 0);
  if (!Number.isFinite(number)) return 'Rp 0';
  return `Rp ${new Intl.NumberFormat('id-ID').format(number)}`;
}

export function createIdempotencyKey(): string {
  const bytes = Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = bytes.map((byte) => byte.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
