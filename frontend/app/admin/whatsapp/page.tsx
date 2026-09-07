'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminHeader from '../admin-header';

type WhatsAppStatus = {
  state: string;
  connected: boolean;
  qr: string | null;
  account: string | null;
  lastError: string | null;
};

function statusLabel(status: WhatsAppStatus) {
  if (status.connected) return 'Terhubung';
  if (status.state === 'qr') return 'Menunggu scan QR';
  if (status.state === 'unavailable') return 'Gateway offline';
  return status.state.replaceAll('_', ' ');
}

export default function WhatsAppAdminPage() {
  const [status, setStatus] = useState<WhatsAppStatus | null>(null);
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  const api = useCallback(async (path: string, init: RequestInit = {}) => {
    const token = localStorage.getItem('ydc_staff_token');
    if (!token) {
      location.replace('/admin/masuk');
      throw new Error('Sesi pegawai berakhir.');
    }

    const response = await fetch(`/backend/v1/staff/whatsapp${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
        ...init.headers,
      },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(payload.message || 'Permintaan WhatsApp gagal.');
    }

    return payload;
  }, []);

  const load = useCallback(async () => {
    try {
      const payload = await api('/status');
      setStatus(payload.data);
      setMessage('');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Status WhatsApp tidak dapat dimuat.');
    }
  }, [api]);

  useEffect(() => {
    load();
    const timer = window.setInterval(load, 3000);
    return () => window.clearInterval(timer);
  }, [load]);

  async function reconnect() {
    setBusy(true);
    setMessage('');
    try {
      await api('/reconnect', { method: 'POST' });
      setMessage('Gateway sedang menyiapkan QR baru.');
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Gateway gagal dihubungkan ulang.');
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    if (!window.confirm('Keluarkan perangkat WhatsApp ini dan buat QR baru?')) return;
    setBusy(true);
    setMessage('');
    try {
      await api('/logout', { method: 'POST' });
      setMessage('Perangkat dikeluarkan. Tunggu QR baru muncul.');
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Perangkat gagal dikeluarkan.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="admin-page">
      <AdminHeader name="Pegawai" role="Staff" />
      <section className="admin-shell">
        <header className="admin-welcome">
          <div>
            <p className="eyebrow">Integrasi</p>
            <h1>WhatsApp Gateway</h1>
            <p>Hubungkan satu perangkat WhatsApp untuk notifikasi pesanan dan otomasi layanan.</p>
          </div>
          <span className={status?.connected ? 'status-pill status-pill-success' : 'status-pill status-pill-warning'}>
            <span aria-hidden="true">●</span> {status ? statusLabel(status) : 'Memuat'}
          </span>
        </header>

        {message && <div className="form-success">{message}</div>}

        <div className="admin-grid">
          <section className="admin-surface whatsapp-qr-card">
            {status?.qr ? (
              <div className="whatsapp-qr-content">
                <p className="eyebrow">Perangkat tertaut</p>
                <h2>Scan QR WhatsApp</h2>
                <p>Buka WhatsApp di HP, pilih <strong>Perangkat tertaut</strong> → <strong>Tautkan perangkat</strong>, lalu scan QR ini.</p>
                <img src={status.qr} alt="QR untuk menghubungkan WhatsApp" className="whatsapp-qr-image" />
                <small>QR akan diperbarui otomatis jika kedaluwarsa.</small>
              </div>
            ) : status?.connected ? (
              <div className="whatsapp-empty-state">
                <span className="whatsapp-connected-icon" aria-hidden="true">✓</span>
                <h2>WhatsApp siap digunakan</h2>
                <p>Akun terhubung: <strong>{status.account || 'Tersambung'}</strong></p>
              </div>
            ) : (
              <div className="whatsapp-empty-state">
                <span className="whatsapp-pending-icon" aria-hidden="true">!</span>
                <h2>Menunggu gateway</h2>
                <p>{status?.lastError || 'Gateway sedang menyiapkan koneksi dan QR.'}</p>
              </div>
            )}
          </section>

          <aside className="admin-surface whatsapp-controls">
            <p className="eyebrow">Kontrol sesi</p>
            <h2>Kelola koneksi</h2>
            <p>Notifikasi status pesanan hanya dikirim setelah perangkat WhatsApp berhasil terhubung.</p>
            <div className="whatsapp-actions">
              <button type="button" className="button button-dark" onClick={reconnect} disabled={busy}>
                {busy ? 'Memproses…' : 'Hubungkan Ulang'}
              </button>
              {status?.connected && (
                <button type="button" className="button button-light" onClick={logout} disabled={busy}>
                  Keluarkan Perangkat
                </button>
              )}
            </div>
            <div className="whatsapp-help">
              <strong>Catatan keamanan</strong>
              <p>QR hanya tampil di halaman admin yang membutuhkan login staff. Jangan bagikan screenshot QR kepada orang lain.</p>
            </div>
          </aside>
        </div>
      </section>
    </main>
  );
}
