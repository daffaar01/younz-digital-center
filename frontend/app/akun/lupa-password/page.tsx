'use client';
import { FormEvent, useState } from 'react';
import SiteHeader from '../../site-header';

export default function ForgotPasswordPage() {
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');
  const [success, setSuccess] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setMessage('');
    try {
      const data = Object.fromEntries(new FormData(event.currentTarget));
      const response = await fetch('/backend/v1/auth/forgot-password', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
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

  return <main><SiteHeader/><section className="auth-page"><form className="auth-card" onSubmit={submit}><p className="eyebrow">Pemulihan akun</p><h1>Reset password pelanggan</h1><p>Masukkan email akun. Jika terdaftar, kami akan mengirim tautan reset melalui email.</p>{message&&<div className={success?'form-success':'form-error'}>{message}</div>}<label>Email<input name="email" type="email" required autoComplete="email" autoFocus/></label><button className="button button-dark full-button" disabled={pending}>{pending?'Mengirim…':'Kirim tautan reset'}</button><a href="/akun/masuk">Kembali ke login</a></form></section></main>;
}