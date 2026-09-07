import { getTopupStatus, type PageSearchParams } from '../../topup-api';
import TopupDocument from '../topup-document';

export default async function ReceiptPage({
  params,
  searchParams,
}: {
  params: Promise<{ token: string }>;
  searchParams: Promise<PageSearchParams>;
}) {
  const { token } = await params;
  const result = await getTopupStatus(`/topup/status/${encodeURIComponent(token)}/struk`, await searchParams);
  return <TopupDocument kind="receipt" order={result.data} />;
}
