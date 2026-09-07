'use client';

import { FormEvent, useEffect, useRef, useState } from 'react';

type Project = {
  id: number; project_name: string; customer_name: string; whatsapp: string | null;
  email: string | null; address: string | null; project_type: 'web' | 'android';
  hosting_provider: string | null; active_from: string | null; active_until: string | null;
  days_remaining: number | null; expiry_state: 'active' | 'expiring_soon' | 'expired' | 'unknown';
  hosting_login_email_masked: string | null; has_hosting_password: boolean;
  payment_status: 'belum_diisi' | 'dp' | 'lunas'; amount: number | null;
  transaction_date: string | null; contact_method: 'belum_diisi' | 'whatsapp' | 'tatap_muka'; notes: string | null;
};
type Payload = { items: Project[]; summary: { total: number; expiring_soon: number; expired: number } };
type Credentials = { hosting_login_email: string | null; hosting_login_password: string | null };

const money = new Intl.NumberFormat('id-ID');
const date = (value: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Belum diisi';
const label = (value: string) => ({ web: 'Web', android: 'Android', dp: 'DP', lunas: 'Lunas', belum_diisi: 'Belum diisi', whatsapp: 'WhatsApp', tatap_muka: 'Tatap muka' }[value] || value);
const empty = { project_name: '', customer_name: '', whatsapp: '', email: '', address: '', project_type: 'web', hosting_provider: '', active_from: '', active_until: '', hosting_login_email: '', hosting_login_password: '', payment_status: 'belum_diisi', amount: '', transaction_date: '', contact_method: 'belum_diisi', notes: '' };

export default function ProjectReminders({ token }: { token: string }) {
  const [data, setData] = useState<Payload | null>(null);
  const [form, setForm] = useState<Record<string, string>>(empty);
  const [editing, setEditing] = useState<number | null>(null);
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');
  const [credentials, setCredentials] = useState<Record<number, Credentials>>({});
  const [clearCredentials, setClearCredentials] = useState(false);
  const revealTimers = useRef<Record<number, ReturnType<typeof setTimeout>>>({});
  const loadGeneration = useRef(0);
  const revealGeneration = useRef(0);

  const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };
  async function json(response: Response): Promise<Record<string, any>> {
    if (response.status === 401) {
      localStorage.removeItem('ydc_staff_token');
      location.replace('/admin/masuk');
      throw new Error('Sesi admin telah berakhir.');
    }
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) throw new Error('Respons server tidak valid. Silakan coba lagi.');
    try { return await response.json(); }
    catch { throw new Error('Respons server tidak dapat dibaca. Silakan coba lagi.'); }
  }
  async function load() {
    const generation = ++loadGeneration.current;
    setCredentials({});
    const response = await fetch('/backend/v1/staff/project-reminders', { headers });
    const payload = await json(response);
    if (!response.ok) throw new Error('Pengingat proyek belum dapat dimuat.');
    if (generation !== loadGeneration.current) return;
    if (!payload.data || !Array.isArray(payload.data.items) || typeof payload.data.summary !== 'object') {
      throw new Error('Format data pengingat tidak valid.');
    }
    setData(payload.data);
  }
  useEffect(() => {
    const hideCredentials = () => { if (document.hidden) { revealGeneration.current += 1; setCredentials({}); } };
    document.addEventListener('visibilitychange', hideCredentials);
    void load().catch((error) => setMessage(error.message));
    return () => {
      document.removeEventListener('visibilitychange', hideCredentials);
      loadGeneration.current += 1;
      revealGeneration.current += 1;
      Object.values(revealTimers.current).forEach(clearTimeout);
      setCredentials({});
    };
  }, [token]);

  function closeForm() {
    if (pending) return;
    revealGeneration.current += 1;
    setOpen(false); setEditing(null); setForm(empty); setClearCredentials(false); setCredentials({}); setMessage('');
  }
  function startCreate() { if (pending) return; revealGeneration.current += 1; setEditing(null); setForm(empty); setClearCredentials(false); setCredentials({}); setMessage(''); setOpen(true); }
  function startEdit(item: Project) {
    if (pending) return;
    revealGeneration.current += 1;
    setEditing(item.id);
    setForm({ project_name: item.project_name, customer_name: item.customer_name, whatsapp: item.whatsapp || '', email: item.email || '', address: item.address || '', project_type: item.project_type, hosting_provider: item.hosting_provider || '', active_from: item.active_from || '', active_until: item.active_until || '', hosting_login_email: '', hosting_login_password: '', payment_status: item.payment_status, amount: item.amount?.toString() || '', transaction_date: item.transaction_date || '', contact_method: item.contact_method, notes: item.notes || '' });
    setClearCredentials(false); setCredentials({}); setMessage(''); setOpen(true);
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setPending(true); setMessage('');
    const body: Record<string, string | number | null> = {};
    for (const [key, value] of Object.entries(form)) body[key] = value === '' ? null : value;
    if (form.amount) body.amount = Number(form.amount);
    if (editing && clearCredentials) {
      body.hosting_login_email = null;
      body.hosting_login_password = null;
    } else if (editing) {
      if (!form.hosting_login_email) delete body.hosting_login_email;
      if (!form.hosting_login_password) delete body.hosting_login_password;
    }
    try {
      const response = await fetch(`/backend/v1/staff/project-reminders${editing ? `/${editing}` : ''}`, { method: editing ? 'PATCH' : 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const payload = await json(response);
      if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] as string || payload.message || 'Data belum dapat disimpan.');
      setOpen(false); setEditing(null); setForm(empty); setClearCredentials(false); await load();
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Data belum dapat disimpan.'); }
    finally { setPending(false); }
  }
  async function reveal(id: number) {
    const generation = ++revealGeneration.current;
    setMessage('');
    try {
      const response = await fetch(`/backend/v1/staff/project-reminders/${id}/reveal-credentials`, { method: 'POST', headers });
      const payload = await json(response);
      if (!response.ok) throw new Error(payload.message || 'Kredensial tidak dapat ditampilkan.');
      if (generation !== revealGeneration.current) return;
      if (!payload.data || !('hosting_login_email' in payload.data) || !('hosting_login_password' in payload.data)) {
        throw new Error('Format kredensial dari server tidak valid.');
      }
      setCredentials((current) => ({ ...current, [id]: payload.data }));
      clearTimeout(revealTimers.current[id]);
      revealTimers.current[id] = setTimeout(() => {
        setCredentials((current) => { const next = { ...current }; delete next[id]; return next; });
        delete revealTimers.current[id];
      }, 30_000);
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Kredensial tidak dapat ditampilkan.'); }
  }
  function hideCredential(id: number) {
    clearTimeout(revealTimers.current[id]);
    delete revealTimers.current[id];
    setCredentials((current) => { const next = { ...current }; delete next[id]; return next; });
  }

  return <section className="project-reminders" aria-labelledby="project-reminder-title">
    <header className="project-reminders-head"><div><p className="eyebrow">Pengingat proyek pelanggan</p><h2 id="project-reminder-title">Masa aktif & hosting</h2><p>Pantau layanan, pembayaran, dan kredensial hosting proyek jangka panjang.</p></div><button className="button button-dark" type="button" disabled={pending} onClick={startCreate}>+ Tambah proyek</button></header>
    {message && <div className="project-reminder-error" role="alert">{message}</div>}
    <div className="project-reminder-stats"><article><small>Total proyek</small><strong>{data?.summary.total ?? '—'}</strong></article><article><small>Berakhir ≤ 90 hari</small><strong>{data?.summary.expiring_soon ?? '—'}</strong></article><article><small>Sudah berakhir</small><strong>{data?.summary.expired ?? '—'}</strong></article></div>

    {open && <form className="project-reminder-form" onSubmit={submit}>
      <header><div><small>{editing ? 'PERBARUI DATA' : 'PROYEK BARU'}</small><h3>{editing ? 'Edit pengingat proyek' : 'Tambah pengingat proyek'}</h3></div><button type="button" disabled={pending} onClick={closeForm} aria-label="Tutup form">×</button></header>
      <div className="project-form-grid">
        <label>Nama proyek<input required value={form.project_name} onChange={(e) => setForm({ ...form, project_name: e.target.value })} /></label>
        <label>Nama pelanggan / pembeli<input required value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} /></label>
        <label>No. WhatsApp<input value={form.whatsapp} onChange={(e) => setForm({ ...form, whatsapp: e.target.value })} /></label>
        <label>Email pelanggan<input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
        <label className="wide">Alamat pelanggan / pembeli<textarea rows={2} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} /></label>
        <label>Jenis<select value={form.project_type} onChange={(e) => setForm({ ...form, project_type: e.target.value })}><option value="web">Web</option><option value="android">Android</option></select></label>
        <label>Hosting<input list="hosting-providers" value={form.hosting_provider} onChange={(e) => setForm({ ...form, hosting_provider: e.target.value })} placeholder="IDwebhost, Hostinger, Domainesia…" /><datalist id="hosting-providers"><option value="IDwebhost"/><option value="Hostinger"/><option value="Domainesia"/><option value="Niagahoster"/><option value="Rumahweb"/></datalist></label>
        <label>Masa aktif mulai<input type="date" value={form.active_from} onChange={(e) => setForm({ ...form, active_from: e.target.value })} /></label>
        <label>Masa aktif berakhir<input type="date" min={form.active_from} value={form.active_until} onChange={(e) => setForm({ ...form, active_until: e.target.value })} /></label>
        <label>Email login hosting<input type="email" disabled={clearCredentials} value={form.hosting_login_email} onChange={(e) => setForm({ ...form, hosting_login_email: e.target.value })} placeholder={editing ? 'Kosongkan bila tidak diubah' : ''} /></label>
        <label>Password login hosting<input type="password" autoComplete="new-password" disabled={clearCredentials} value={form.hosting_login_password} onChange={(e) => setForm({ ...form, hosting_login_password: e.target.value })} placeholder={editing ? 'Kosongkan bila tidak diubah' : ''} /></label>
        {editing && <label className="wide credential-clear"><input type="checkbox" checked={clearCredentials} onChange={(e) => setClearCredentials(e.target.checked)} /> Hapus email dan password hosting yang tersimpan</label>}
        <label>Status biaya<select value={form.payment_status} onChange={(e) => setForm({ ...form, payment_status: e.target.value })}><option value="belum_diisi">Belum diisi</option><option value="dp">DP</option><option value="lunas">Lunas</option></select></label>
        <label>Nominal biaya<input type="number" min="0" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></label>
        <label>Tanggal transaksi<input type="date" value={form.transaction_date} onChange={(e) => setForm({ ...form, transaction_date: e.target.value })} /></label>
        <label>Pesan melalui<select value={form.contact_method} onChange={(e) => setForm({ ...form, contact_method: e.target.value })}><option value="belum_diisi">Belum diisi</option><option value="whatsapp">WhatsApp</option><option value="tatap_muka">Tatap muka</option></select></label>
        <label className="wide">Catatan<textarea rows={2} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></label>
      </div><footer><button type="button" disabled={pending} onClick={closeForm}>Batal</button><button className="button button-dark" disabled={pending}>{pending ? 'Menyimpan…' : 'Simpan pengingat'}</button></footer>
    </form>}

    <div className="project-reminder-list">
      {!data ? <div className="admin-empty">Memuat pengingat proyek…</div> : data.items.length === 0 ? <div className="admin-empty">Belum ada proyek. Tambahkan proyek pertama Anda.</div> : data.items.map((item, index) => <article className={`project-reminder-card ${item.expiry_state}`} key={item.id}>
        <header><span className="project-number">{String(index + 1).padStart(2, '0')}</span><div><small>{label(item.project_type)} · {item.hosting_provider || 'Hosting belum diisi'}</small><h3>{item.project_name}</h3><p>{item.customer_name}</p></div><span className={`expiry-badge ${item.expiry_state}`}>{item.expiry_state === 'expired' ? 'Kedaluwarsa' : item.expiry_state === 'expiring_soon' ? `${item.days_remaining} hari lagi` : item.expiry_state === 'active' ? 'Aktif' : 'Tanggal belum diisi'}</span></header>
        <div className="project-detail-grid"><div><small>WhatsApp</small><strong>{item.whatsapp || 'Belum diisi'}</strong></div><div><small>Email</small><strong>{item.email || 'Belum diisi'}</strong></div><div className="wide"><small>Alamat</small><strong>{item.address || 'Belum diisi'}</strong></div><div><small>Masa aktif</small><strong>{date(item.active_from)} — {date(item.active_until)}</strong></div><div><small>Biaya</small><strong>{label(item.payment_status)}{item.amount !== null ? ` · Rp ${money.format(item.amount)}` : ''}</strong></div><div><small>Tanggal transaksi</small><strong>{date(item.transaction_date)}</strong></div><div><small>Pesan via</small><strong>{label(item.contact_method)}</strong></div><div className="wide"><small>Login hosting</small>{credentials[item.id] ? <strong className="credential-value">{credentials[item.id].hosting_login_email || 'Email belum diisi'}<br/>{credentials[item.id].hosting_login_password || 'Password belum diisi'}</strong> : <strong>{item.hosting_login_email_masked || 'Email belum diisi'} · {item.has_hosting_password ? '••••••••••' : 'Password belum diisi'}</strong>}</div>{item.notes && <div className="wide"><small>Catatan</small><strong>{item.notes}</strong></div>}</div>
        <footer><button type="button" disabled={pending} onClick={() => startEdit(item)}>Edit data</button>{(item.hosting_login_email_masked || item.has_hosting_password) && <button className="credential-button" type="button" disabled={pending} onClick={() => credentials[item.id] ? hideCredential(item.id) : void reveal(item.id)}>{credentials[item.id] ? 'Sembunyikan kredensial' : 'Tampilkan kredensial'}</button>}</footer>
      </article>)}
    </div>
  </section>;
}
