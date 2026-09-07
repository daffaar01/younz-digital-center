export type TopupDetailValue = string | number | boolean | null | TopupDetailValue[] | { [key: string]: TopupDetailValue };

export type TopupStatus = {
  order_number: string;
  product_name: string;
  transaction_type: { code: string; label: string };
  destination: string;
  provider_customer_name: string | null;
  bill_details: Record<string, TopupDetailValue> | null;
  selling_price: number;
  admin_fee: number;
  total_amount: number;
  payment_status: { code: string; label: string };
  fulfillment_status: { code: string; label: string };
  serial_number: string | null;
  midtrans_payment_type: string | null;
  midtrans_redirect_url: string | null;
  created_at: string | null;
  paid_at: string | null;
  expires_at: string | null;
  refresh_url: string;
  payment_url: string | null;
  receipt_url: string | null;
  invoice_url: string | null;
  sku?: string;
  category?: string;
  brand?: string;
  customer_name?: string;
  customer_email?: string | null;
  customer_phone?: string;
  payment_method?: string;
  payment_reference?: string | null;
  provider_reference?: string | null;
  provider_rc?: string | null;
  fulfilled_at?: string | null;
  source?: { code: 'offline' | 'whatsapp' | 'web'; label: string };
  merchant?: { name: string; address: string; phone: string; hours: string };
};

export type PageSearchParams = Record<string, string | string[] | undefined>;

function queryString(searchParams: PageSearchParams): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(searchParams)) {
    if (typeof value === 'string') query.set(key, value);
    else if (Array.isArray(value)) value.forEach((item) => query.append(key, item));
  }
  return query.toString();
}

export async function getTopupStatus(
  path: string,
  searchParams: PageSearchParams,
): Promise<{ data: TopupStatus | null; status: number }> {
  const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
  const target = new URL(path, backend);
  target.search = queryString(searchParams);

  try {
    const response = await fetch(target, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return { data: null, status: response.status };
    const payload = (await response.json()) as { data: TopupStatus };
    return { data: payload.data, status: response.status };
  } catch {
    return { data: null, status: 503 };
  }
}
