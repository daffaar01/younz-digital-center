import { getTopupStatus, type PageSearchParams } from '../../topup-api';
import TopupDocument from '../topup-document';

export default async function InvoicePage({
  params,
  searchParams,
}: {
  params: Promise<{ token: string }>;
  searchParams: Promise<PageSearchParams>;
}) {
  const { token } = await params;
  const result = await getTopupStatus(`/topup/status/${encodeURIComponent(token)}/invoice`, await searchParams);
  return <TopupDocument kind="invoice" order={result.data} />;
}
