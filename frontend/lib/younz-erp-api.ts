const API_URL = process.env.NEXT_PUBLIC_YOUNZERP_API_URL ?? '/api/younz-erp';

export type ApiHealth = {
  status: string;
  service: string;
  version: string;
};

export type User = {
  id: string;
  name: string;
  email: string;
};

export type Business = {
  id: string;
  name: string;
  code: string;
};

export type Outlet = {
  id: string;
  business_id: string;
  name: string;
  code: string;
};

export type LoginResponse = {
  data: {
    token: string;
    token_type: string;
    user: User;
    business: Business;
    outlet: Outlet;
  };
};

export type Product = {
  id: string;
  provider_id?: string | null;
  name: string;
  sku: string | null;
  product_type: string;
  cost_price: string | number;
  selling_price: string | number;
  is_stockable: boolean;
  status: string;
  provider?: { id: string; name: string; balance: string | number } | null;
};

export type PaymentMethod = {
  id: string;
  name: string;
  method_type: string;
};

export type CashAccount = {
  id: string;
  name: string;
  account_type: string;
  balance: string | number;
};

export type Transaction = {
  id: string;
  transaction_number: string;
  transaction_date: string;
  total_amount: string | number;
  paid_amount: string | number;
  status: string;
  payment_status: string;
  customer: { id: string; name: string } | null;
  items: Array<{ id: string; product_id: string; description: string; quantity: string | number; subtotal_amount: string | number }>;
};

export type ProductInput = {
  name: string;
  provider_id?: string;
  sku?: string;
  product_type: string;
  cost_price: number;
  selling_price: number;
  is_stockable: boolean;
};

export type TransactionInput = {
  outlet_id: string;
  customer_id?: string;
  project_id?: string;
  client_reference?: string;
  items: Array<{ product_id: string; quantity: number; details?: Record<string, unknown> }>;
  payments: Array<{
    payment_method_id: string;
    cash_account_id?: string;
    amount: number;
  }>;
};

export type Purchase = {
  id: string;
  purchase_number: string;
  purchase_date: string;
  total_amount: string | number;
  paid_amount: string | number;
  status: string;
  payment_status: string;
  supplier: { id: string; name: string } | null;
  items: Array<{ id: string; product_id: string; description: string; quantity: string | number; unit_cost: string | number; subtotal_amount: string | number }>;
};

export type PurchaseInput = {
  outlet_id: string;
  supplier_id?: string;
  client_reference?: string;
  items: Array<{ product_id: string; quantity: number; unit_cost: number }>;
  payments?: Array<{
    payment_method_id: string;
    cash_account_id?: string;
    amount: number;
  }>;
};

export type CustomerRecord = {
  id: string;
  name: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  status: string;
};

export type SupplierRecord = CustomerRecord;

export type StockBalance = {
  id: string;
  quantity: string | number;
  minimum_quantity: string | number;
  product: { id: string; name: string; sku: string | null; is_stockable: boolean };
  outlet: { id: string; name: string };
};

export type ReportSummary = {
  period: { from: string; to: string };
  summary: {
    transaction_count: number;
    items_sold: number;
    sales: number;
    paid: number;
    cost: number;
    profit: number;
  };
  counts: { customers: number; suppliers: number; low_stock: number };
  top_products: Array<{ id: string; name: string; quantity: number; sales: number }>;
};

export type RoleRecord = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  users_count: number;
  permissions: Array<{ id: string; name: string; slug: string }>;
};

export type PermissionRecord = { id: string; name: string; slug: string };

export type Provider = {
  id: string;
  name: string;
  provider_type: string;
  account_identifier: string | null;
  opening_balance: string | number;
  balance: string | number;
  products_count?: number;
  status: string;
};

export type IntegrationStatus = {
  digiflazz: { configured: boolean; testing: boolean; endpoint: string | null };
  midtrans: { configured: boolean; production: boolean; notification_configured: boolean };
};

export type Project = {
  id: string;
  project_number: string;
  name: string;
  project_type: string;
  status: string;
  quoted_amount: string | number;
  cost_amount: string | number;
  paid_amount: string | number;
  start_date: string | null;
  due_date: string | null;
  description: string | null;
  customer: { id: string; name: string } | null;
};

export type FinanceSummary = {
  period: { from: string; to: string };
  summary: {
    sales: number;
    sales_paid: number;
    gross_profit: number;
    other_income: number;
    expenses: number;
    net_profit: number;
    cash_in: number;
    cash_out: number;
  };
  counts: { projects: number; active_projects: number; providers: number; customers: number; suppliers: number };
  provider_balance: number;
};

export type FinanceEntry = {
  id: string;
  category: string;
  amount: string | number;
  income_date?: string;
  expense_date?: string;
  description: string;
  project?: { id: string; name: string } | null;
  provider?: { id: string; name: string } | null;
};

type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number };

type ApiOptions = RequestInit & {
  token?: string;
  businessId?: string;
};

export async function apiFetch<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const { token, businessId, ...requestInit } = options;
  const headers = new Headers(requestInit.headers);
  headers.set('Accept', 'application/json');
  headers.set('Content-Type', 'application/json');

  if (token) {
    headers.set('Authorization', 'Bearer ' + token);
  }

  if (businessId) {
    headers.set('X-Business-Id', businessId);
  }

  const response = await fetch(API_URL + path, {
    ...requestInit,
    headers,
    cache: 'no-store',
  });

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const message =
      payload && typeof payload === 'object' && 'message' in payload
        ? String(payload.message)
        : 'API error ' + response.status;
    throw new Error(message);
  }

  return payload as T;
}

export function getApiHealth() {
  return apiFetch<ApiHealth>('/health');
}

export function login(email: string, password: string) {
  return apiFetch<LoginResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
}

export function getProducts(token: string, businessId: string) {
  return apiFetch<{ data: Product[] }>('/products', { token, businessId });
}

export function createProduct(token: string, businessId: string, data: ProductInput) {
  return apiFetch<{ data: Product }>('/products', {
    method: 'POST',
    token,
    businessId,
    body: JSON.stringify(data),
  });
}

export function getProviders(token: string, businessId: string) {
  return apiFetch<{ data: Provider[] }>('/providers', { token, businessId });
}

export function getIntegrationStatus(token: string, businessId: string) {
  return apiFetch<{ data: IntegrationStatus }>('/integrations/status', { token, businessId });
}

export function getDigiflazzBalance(token: string, businessId: string) {
  return apiFetch<{ data: { deposit?: number | string } }>('/integrations/digiflazz/balance', { token, businessId });
}

export function syncDigiflazzCatalog(token: string, businessId: string, type: 'prepaid' | 'postpaid' = 'prepaid') {
  return apiFetch<{ data: { provider_id: string; type: string; received: number; created: number; updated: number } }>('/integrations/digiflazz/sync?type=' + type, {
    method: 'POST',
    token,
    businessId,
  });
}

export function checkMidtransConnection(token: string, businessId: string) {
  return apiFetch<{ data: { connected: boolean; status_code: number; environment: string } }>('/integrations/midtrans/check', { token, businessId });
}

export function createMidtransCheckout(token: string, businessId: string, transactionId: string) {
  return apiFetch<{ data: { order_id: string; amount: string | number; status: string; token: string; redirect_url: string } }>(`/transactions/${transactionId}/midtrans/checkout`, {
    method: 'POST',
    token,
    businessId,
  });
}

export function createProvider(token: string, businessId: string, data: { name: string; provider_type: string; account_identifier?: string; opening_balance: number; notes?: string }) {
  return apiFetch<{ data: Provider }>('/providers', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function adjustProvider(token: string, businessId: string, providerId: string, data: { direction: 'in' | 'out'; amount: number; reference?: string; description: string }) {
  return apiFetch<{ data: Provider }>(`/providers/${providerId}/adjustments`, { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getPaymentMethods(token: string, businessId: string) {
  return apiFetch<{ data: PaymentMethod[] }>('/payment-methods', { token, businessId });
}

export function getCashAccounts(token: string, businessId: string) {
  return apiFetch<{ data: CashAccount[] }>('/cash-accounts', { token, businessId });
}

export function createTransaction(token: string, businessId: string, data: TransactionInput) {
  return createTransactionPayload(token, businessId, data);
}

export function createTransactionPayload(token: string, businessId: string, data: TransactionInput) {
  return apiFetch<{ data: Transaction }>('/transactions', {
    method: 'POST',
    token,
    businessId,
    body: JSON.stringify(data),
  });
}

export function getTransactions(token: string, businessId: string) {
  return apiFetch<Paginated<Transaction>>('/transactions', { token, businessId });
}

export function getPurchases(token: string, businessId: string) {
  return apiFetch<Paginated<Purchase>>('/purchases', { token, businessId });
}

export function createPurchase(token: string, businessId: string, data: PurchaseInput) {
  return createPurchasePayload(token, businessId, data);
}

export function createPurchasePayload(token: string, businessId: string, data: PurchaseInput) {
  return apiFetch<{ data: Purchase }>('/purchases', {
    method: 'POST',
    token,
    businessId,
    body: JSON.stringify(data),
  });
}

export function getCustomers(token: string, businessId: string, query = '') {
  return apiFetch<Paginated<CustomerRecord>>('/customers' + (query ? '?q=' + encodeURIComponent(query) : ''), { token, businessId });
}

export function createCustomer(token: string, businessId: string, data: Pick<CustomerRecord, 'name' | 'phone' | 'email' | 'address'>) {
  return apiFetch<{ data: CustomerRecord }>('/customers', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getSuppliers(token: string, businessId: string, query = '') {
  return apiFetch<Paginated<SupplierRecord>>('/suppliers' + (query ? '?q=' + encodeURIComponent(query) : ''), { token, businessId });
}

export function createSupplier(token: string, businessId: string, data: Pick<SupplierRecord, 'name' | 'phone' | 'email' | 'address'>) {
  return apiFetch<{ data: SupplierRecord }>('/suppliers', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getStock(token: string, businessId: string) {
  return apiFetch<Paginated<StockBalance>>('/stock', { token, businessId });
}

export function adjustStock(token: string, businessId: string, data: { product_id: string; outlet_id: string; movement_type: 'in' | 'out' | 'adjustment'; quantity: number; minimum_quantity?: number; unit_cost?: number; note?: string }) {
  return apiFetch<{ data: StockBalance }>('/stock/adjustments', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getReportSummary(token: string, businessId: string) {
  return apiFetch<{ data: ReportSummary }>('/reports/summary', { token, businessId });
}

export function getProjects(token: string, businessId: string) {
  return apiFetch<Paginated<Project>>('/projects', { token, businessId });
}

export function createProject(token: string, businessId: string, data: { outlet_id: string; customer_id?: string; name: string; project_type: string; quoted_amount: number; due_date?: string; description?: string }) {
  return apiFetch<{ data: Project }>('/projects', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getFinanceSummary(token: string, businessId: string) {
  return apiFetch<{ data: FinanceSummary }>('/finance/summary', { token, businessId });
}

export function getIncomes(token: string, businessId: string) {
  return apiFetch<Paginated<FinanceEntry>>('/incomes', { token, businessId });
}

export function getExpenses(token: string, businessId: string) {
  return apiFetch<Paginated<FinanceEntry>>('/expenses', { token, businessId });
}

export function createIncome(token: string, businessId: string, data: { outlet_id: string; project_id?: string; customer_id?: string; cash_account_id?: string; category: string; amount: number; description: string }) {
  return apiFetch<{ data: FinanceEntry }>('/incomes', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function createExpense(token: string, businessId: string, data: { outlet_id: string; project_id?: string; provider_id?: string; supplier_id?: string; cash_account_id?: string; category: string; amount: number; description: string }) {
  return apiFetch<{ data: FinanceEntry }>('/expenses', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}

export function getRoles(token: string, businessId: string) {
  return apiFetch<{ data: RoleRecord[] }>('/roles', { token, businessId });
}

export function getPermissions(token: string, businessId: string) {
  return apiFetch<{ data: PermissionRecord[] }>('/permissions', { token, businessId });
}

export function createRole(token: string, businessId: string, data: { name: string; slug: string; description?: string; permission_ids: string[] }) {
  return apiFetch<{ data: RoleRecord }>('/roles', { method: 'POST', token, businessId, body: JSON.stringify(data) });
}
