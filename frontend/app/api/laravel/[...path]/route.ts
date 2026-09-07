import type { NextRequest } from 'next/server';
const laravelUrl = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8080';
async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  const target = new URL('/api/' + path.join('/'), laravelUrl);
  target.search = request.nextUrl.search;
  const headers = new Headers();
  for (const name of ['accept', 'authorization', 'content-type', 'idempotency-key']) {
    const value = request.headers.get(name); if (value) headers.set(name, value);
  }
  const init: RequestInit = { method: request.method, headers, redirect: 'manual', cache: 'no-store' };
  if (!['GET','HEAD'].includes(request.method)) init.body = await request.arrayBuffer();
  const response = await fetch(target, init);
  const outgoing = new Headers();
  for (const name of ['content-type','content-disposition','cache-control','location','content-security-policy','x-content-type-options','cross-origin-resource-policy']) {
    const value = response.headers.get(name); if (value) outgoing.set(name, value);
  }
  return new Response(response.body, { status: response.status, headers: outgoing });
}
export const GET=proxy;export const POST=proxy;export const PUT=proxy;export const PATCH=proxy;export const DELETE=proxy;