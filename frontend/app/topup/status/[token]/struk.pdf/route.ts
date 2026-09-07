import type { NextRequest } from 'next/server';

const laravelUrl = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';

export async function GET(
  request: NextRequest,
  context: { params: Promise<{ token: string }> },
): Promise<Response> {
  const { token } = await context.params;
  const target = new URL(
    `/topup/status/${encodeURIComponent(token)}/struk.pdf`,
    laravelUrl,
  );
  target.search = request.nextUrl.search;

  const response = await fetch(target, {
    headers: { Accept: 'application/pdf' },
    redirect: 'manual',
    cache: 'no-store',
  });
  const outgoing = new Headers();

  for (const name of ['content-type', 'content-disposition', 'cache-control']) {
    const value = response.headers.get(name);
    if (value) outgoing.set(name, value);
  }

  return new Response(response.body, {
    status: response.status,
    headers: outgoing,
  });
}
