'use client';
import { FormEvent, useEffect, useState } from 'react';
import SiteHeader from '../../../site-header';

export default function ResetPasswordPage({ params }: { params: Promise<{ token: string }> }) {
  const [token, setToken] = useState('');
  const [email, setEmail] = useState('');
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    params.then((value) => setToken(value.token));
    setEmail(new URLSearchParams(window.location.search).get('email') || '');
  }, [params]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setMessage('');
    const data = Object.fromEntries(new FormData(event.currentTarget));
    try {
      const response = await fetch('/backend/v1/auth/reset-password', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, token }),
      });
      const payload = await response.json();
      if (!response.ok) {
        setSuccess(false);
        setMessage(String(Object.values(payload.errors || {}).flat()[0] || payload.message));
        return;
      }
      setSuccess(true);
      setMessage(payload.message);
    } catch {
      setSuccess(false);
      setMessage('Koneksi ke Laravel terputus.');
    } finally {
      setPending(false);
    }
  }

  return <main><SiteHeader/><section className="auth-page"><form className="auth-card" onSubmit={submit}><p className="eyebrow">Keamanan akun</p><h1>Buat password baru</h1>{message&&<div className={success?'form-success':'form-error'}>{message}</div>}{success?<a className="button button-dark full-button" href="/akun/masuk">Masuk dengan password baru</a>:<><label>Email<input name="email" type="email" required autoComplete="email" value={email} onChange={(event)=>setEmail(event.target.value)}/></label><label>Password baru<input name="password" type="password" required minLength={12} autoComplete="new-password"/><small>Minimal 12 karakter, berisi huruf besar, huruf kecil, dan angka.</small></label><label>Ulangi password<input name="password_confirmation" type="password" required minLength={12} autoComplete="new-password"/></label><button className="button button-dark full-button" disabled={pending||!token}>{pending?'Menyimpan…':'Simpan password baru'}</button></>}</form></section></main>;
}