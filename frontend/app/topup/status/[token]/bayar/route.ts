import { NextRequest } from 'next/server';

export async function POST(request: NextRequest, context: { params: Promise<{ token: string }> }) {
  const { token } = await context.params;
  const backend = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
  const target = new URL(`/topup/status/${encodeURIComponent(token)}/bayar`, backend);
  target.search = request.nextUrl.search;

  const response = await fetch(target, {
    method: 'POST',
    redirect: 'manual',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });
  const location = response.headers.get('location');

  if (location) return Response.redirect(location, 303);
  return Response.json({ message: 'Pembayaran belum dapat dibuat.' }, { status: response.status || 502 });
}
