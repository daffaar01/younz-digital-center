'use client';

import { FormEvent, useEffect, useState } from 'react';
import AdminHeader from '../admin-header';

type Service = {
  id: number;
  name: string;
  slug: string;
  type: string;
  base_price: number;
  unit: string;
  description: string | null;
  is_active: boolean;
  orders_count: number;
};

type KnowledgeDocument = {
  id: number;
  title: string;
  type: string;
  content: string;
  status: string;
  updated_at: string;
};

type Testimonial = {
  id: number;
  customer_name: string;
  customer_role: string | null;
  quote: string;
  rating: number;
  source_label: string | null;
  is_published: boolean;
  consent_at: string | null;
  display_order: number;
};

type DashboardData = {
  period_days: number;
  services: Service[];
  knowledge_documents: KnowledgeDocument[];
  testimonials: Testimonial[];
  analytics: {
    landing_views: number;
    order_cta_clicks: number;
    order_form_starts: number;
    orders_submitted: number;
    landing_to_order_rate: number;
    cta_sources: { source: string; total: number }[];
  };
  ai: {
    total: number;
    success: number;
    failed: number;
    helpful: number;
    not_helpful: number;
    helpful_rate: number;
    input_tokens: number;
    output_tokens: number;
    cost_micros: number;
    providers: { provider: string; total: number }[];
    recent_feedback: {
      id: number;
      provider: string;
      model: string;
      status: string;
      feedback: 'helpful' | 'not_helpful';
      grounding: string | null;
      sources_count: number;
      feedback_at: string | null;
    }[];
  };
};

type User = { name: string; role: { label: string } };
type Tab = 'ringkasan' | 'layanan' | 'pengetahuan' | 'testimoni';

const serviceBlank = {
  id: 0,
  name: '',
  type: 'print',
  base_price: 0,
  unit: 'lembar',
  description: '',
  is_active: true,
};

const knowledgeBlank = {
  id: 0,
  title: '',
  type: 'faq',
  content: '',
  status: 'active',
};

const testimonialBlank = {
  id: 0,
  customer_name: '',
  customer_role: '',
  quote: '',
  rating: 5,
  source_label: 'Pelanggan',
  is_published: false,
  consent_at: '',
  display_order: 0,
};

const number = new Intl.NumberFormat('id-ID');

function localDateTime(value: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  const offset = date.getTimezoneOffset() * 60_000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export default function ContentAiAdminPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [tab, setTab] = useState<Tab>('ringkasan');
  const [days, setDays] = useState(30);
  const [message, setMessage] = useState('');
  const [pending, setPending] = useState(false);
  const [service, setService] = useState(serviceBlank);
  const [knowledge, setKnowledge] = useState(knowledgeBlank);
  const [testimonial, setTestimonial] = useState(testimonialBlank);

  async function api(path: string, init: RequestInit = {}) {
    const token = localStorage.getItem('ydc_staff_token');
    if (!token) {
      location.replace('/admin/masuk');
      throw new Error('Sesi admin berakhir.');
    }

    const response = await fetch(`/backend/v1/staff/content${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
        ...init.headers,
      },
    });
    const payload = await response.json();
    if (!response.ok) {
      if (response.status === 401) {
        localStorage.removeItem('ydc_staff_token');
        location.replace('/admin/masuk');
      }
      throw new Error(String(Object.values(payload.errors || {}).flat()[0] || payload.message));
    }

    return payload;
  }

  async function load(nextDays = days) {
    const payload = await api(`?days=${nextDays}`);
    setData(payload.data);
  }

  useEffect(() => {
    const stored = localStorage.getItem('ydc_staff_user');
    if (stored) setUser(JSON.parse(stored));
    load().catch((error) => setMessage(error.message));
    // Initial fetch only. Period changes are handled explicitly.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function save(
    event: FormEvent<HTMLFormElement>,
    resource: 'services' | 'knowledge' | 'testimonials',
    draft: Record<string, unknown>,
    reset: () => void,
  ) {
    event.preventDefault();
    setPending(true);
    setMessage('');

    try {
      const id = Number(draft.id || 0);
      const payload = await api(`/${resource}${id ? `/${id}` : ''}`, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(draft),
      });
      setMessage(payload.message);
      reset();
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Data belum dapat disimpan.');
    } finally {
      setPending(false);
    }
  }

  async function remove(resource: 'services' | 'knowledge' | 'testimonials', id: number, label: string) {
    if (!window.confirm(`Hapus ${label}? Tindakan ini tidak dapat dibatalkan.`)) return;

    setPending(true);
    setMessage('');
    try {
      const payload = await api(`/${resource}/${id}`, { method: 'DELETE' });
      setMessage(payload.message);
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Data belum dapat dihapus.');
    } finally {
      setPending(false);
    }
  }

  function changePeriod(value: number) {
    setDays(value);
    setMessage('');
    load(value).catch((error) => setMessage(error.message));
  }

  return (
    <main className="admin-page">
      <AdminHeader name={user?.name || 'Admin'} role={user?.role.label || 'Owner / Admin'} />
      <section className="admin-shell content-ai-page">
        <header className="admin-welcome">
          <div>
            <p className="eyebrow">Konten & Younz AI</p>
            <h1>Kelola yang dilihat pelanggan.</h1>
            <p>Katalog, pengetahuan AI, testimoni, dan performa funnel dalam satu ruang kerja.</p>
          </div>
          <label className="content-period">
            Periode
            <select value={days} onChange={(event) => changePeriod(Number(event.target.value))}>
              <option value={7}>7 hari</option>
              <option value={30}>30 hari</option>
              <option value={90}>90 hari</option>
            </select>
          </label>
        </header>

        {message && (
          <div className={message.includes('berhasil') ? 'form-success' : 'form-error'} role="status">
            {message}
          </div>
        )}

        <nav className="content-tabs" aria-label="Bagian pengelolaan konten">
          {([
            ['ringkasan', 'Ringkasan'],
            ['layanan', `Layanan (${data?.services.length || 0})`],
            ['pengetahuan', `Pengetahuan (${data?.knowledge_documents.length || 0})`],
            ['testimoni', `Testimoni (${data?.testimonials.length || 0})`],
          ] as [Tab, string][]).map(([value, label]) => (
            <button
              type="button"
              className={tab === value ? 'active' : ''}
              key={value}
              onClick={() => setTab(value)}
            >
              {label}
            </button>
          ))}
        </nav>

        {!data ? (
          <div className="admin-empty">Memuat data konten dan analytics…</div>
        ) : (
          <>
            {tab === 'ringkasan' && (
              <div className="content-overview">
                <section className="content-block">
                  <header>
                    <div>
                      <small>Funnel pemesanan</small>
                      <h2>{days} hari terakhir</h2>
                    </div>
                    <strong>{data.analytics.landing_to_order_rate}% konversi</strong>
                  </header>
                  <div className="funnel-grid">
                    {[
                      ['01', 'Landing dilihat', data.analytics.landing_views],
                      ['02', 'CTA pesanan', data.analytics.order_cta_clicks],
                      ['03', 'Form dimulai', data.analytics.order_form_starts],
                      ['04', 'Pesanan masuk', data.analytics.orders_submitted],
                    ].map(([step, label, value]) => (
                      <article key={String(label)}>
                        <span>{step}</span>
                        <strong>{number.format(Number(value))}</strong>
                        <small>{label}</small>
                      </article>
                    ))}
                  </div>
                  <div className="source-list">
                    <strong>Sumber CTA teratas</strong>
                    {data.analytics.cta_sources.length === 0 ? (
                      <span>Belum ada data setelah persetujuan statistik diberikan.</span>
                    ) : data.analytics.cta_sources.map((item) => (
                      <span key={item.source}>
                        <b>{item.source.replaceAll('_', ' ')}</b>
                        <em>{number.format(item.total)} klik</em>
                      </span>
                    ))}
                  </div>
                </section>

                <section className="content-block ai-quality">
                  <header>
                    <div>
                      <small>Kualitas Younz AI</small>
                      <h2>Jawaban dan feedback</h2>
                    </div>
                    <strong>{data.ai.helpful_rate}% membantu</strong>
                  </header>
                  <div className="ai-quality-grid">
                    <article><strong>{number.format(data.ai.total)}</strong><span>Percakapan</span></article>
                    <article><strong>{number.format(data.ai.success)}</strong><span>Berhasil</span></article>
                    <article><strong>{number.format(data.ai.helpful)}</strong><span>👍 Membantu</span></article>
                    <article><strong>{number.format(data.ai.not_helpful)}</strong><span>👎 Perlu diperbaiki</span></article>
                  </div>
                  <div className="ai-provider-list">
                    <span>
                      Token: {number.format(data.ai.input_tokens + data.ai.output_tokens)}
                    </span>
                    <span>
                      Estimasi biaya: Rp {number.format(Math.ceil(data.ai.cost_micros / 1_000_000))}
                    </span>
                    {data.ai.providers.map((item) => (
                      <span key={item.provider}>{item.provider}: {number.format(item.total)}</span>
                    ))}
                  </div>
                  <div className="feedback-list">
                    <strong>Feedback terbaru</strong>
                    {data.ai.recent_feedback.length === 0 ? (
                      <p>Belum ada feedback AI pada periode ini.</p>
                    ) : data.ai.recent_feedback.map((item) => (
                      <article key={item.id}>
                        <span className={item.feedback === 'helpful' ? 'positive' : 'negative'}>
                          {item.feedback === 'helpful' ? '👍 Membantu' : '👎 Perlu diperbaiki'}
                        </span>
                        <div>
                          <strong>{item.provider} · {item.model}</strong>
                          <small>
                            {item.sources_count} sumber · grounding {item.grounding || '-'} ·{' '}
                            {item.feedback_at ? new Date(item.feedback_at).toLocaleString('id-ID') : '-'}
                          </small>
                        </div>
                      </article>
                    ))}
                  </div>
                </section>
              </div>
            )}

            {tab === 'layanan' && (
              <div className="content-manager-grid">
                <form
                  className="content-editor"
                  onSubmit={(event) => save(
                    event,
                    'services',
                    service,
                    () => setService(serviceBlank),
                  )}
                >
                  <header>
                    <div>
                      <small>{service.id ? 'Edit layanan' : 'Layanan baru'}</small>
                      <h2>{service.id ? service.name : 'Tambah ke katalog'}</h2>
                    </div>
                    {service.id > 0 && (
                      <button type="button" onClick={() => setService(serviceBlank)}>Batal</button>
                    )}
                  </header>
                  <label>Nama layanan<input value={service.name} onChange={(event) => setService({ ...service, name: event.target.value })} required /></label>
                  <label>Jenis<select value={service.type} onChange={(event) => setService({ ...service, type: event.target.value })}>
                    {['print', 'fotokopi', 'scan', 'ketik', 'desain', 'website', 'aplikasi'].map((item) => <option key={item}>{item}</option>)}
                  </select></label>
                  <div className="content-form-row">
                    <label>Harga mulai<input type="number" min={0} value={service.base_price} onChange={(event) => setService({ ...service, base_price: Number(event.target.value) })} required /></label>
                    <label>Satuan<input value={service.unit} onChange={(event) => setService({ ...service, unit: event.target.value })} required /></label>
                  </div>
                  <label>Deskripsi<textarea rows={5} value={service.description} onChange={(event) => setService({ ...service, description: event.target.value })} /></label>
                  <label className="content-check"><input type="checkbox" checked={service.is_active} onChange={(event) => setService({ ...service, is_active: event.target.checked })} /><span>Tampilkan pada landing page</span></label>
                  <button className="button button-dark" disabled={pending}>{pending ? 'Menyimpan…' : service.id ? 'Simpan perubahan' : 'Tambah layanan'}</button>
                </form>

                <section className="content-list">
                  {data.services.length === 0 ? (
                    <div className="admin-empty">Belum ada layanan. Tambahkan layanan pertama dari formulir.</div>
                  ) : data.services.map((item) => (
                    <article key={item.id}>
                      <div>
                        <span className={item.is_active ? 'status-active' : 'status-draft'}>
                          {item.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                        <small>{item.type} · {item.orders_count} pesanan</small>
                      </div>
                      <h3>{item.name}</h3>
                      <p>{item.description || 'Belum ada deskripsi.'}</p>
                      <strong>Rp {number.format(item.base_price)} / {item.unit}</strong>
                      <footer>
                        <button type="button" onClick={() => {
                          setService({ ...item, description: item.description || '' });
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                        }}>Edit</button>
                        <button type="button" className="danger" onClick={() => remove('services', item.id, `layanan ${item.name}`)}>Hapus</button>
                      </footer>
                    </article>
                  ))}
                </section>
              </div>
            )}

            {tab === 'pengetahuan' && (
              <div className="content-manager-grid">
                <form
                  className="content-editor"
                  onSubmit={(event) => save(
                    event,
                    'knowledge',
                    knowledge,
                    () => setKnowledge(knowledgeBlank),
                  )}
                >
                  <header>
                    <div>
                      <small>{knowledge.id ? 'Edit pengetahuan' : 'Dokumen baru'}</small>
                      <h2>{knowledge.id ? knowledge.title : 'Ajarkan fakta ke Younz AI'}</h2>
                    </div>
                    {knowledge.id > 0 && <button type="button" onClick={() => setKnowledge(knowledgeBlank)}>Batal</button>}
                  </header>
                  <label>Judul<input value={knowledge.title} onChange={(event) => setKnowledge({ ...knowledge, title: event.target.value })} required /></label>
                  <div className="content-form-row">
                    <label>Jenis<select value={knowledge.type} onChange={(event) => setKnowledge({ ...knowledge, type: event.target.value })}>
                      {['faq', 'service', 'guide', 'policy', 'privacy'].map((item) => <option key={item}>{item}</option>)}
                    </select></label>
                    <label>Status<select value={knowledge.status} onChange={(event) => setKnowledge({ ...knowledge, status: event.target.value })}>
                      <option value="active">Aktif</option>
                      <option value="draft">Draft</option>
                      <option value="archived">Arsip</option>
                    </select></label>
                  </div>
                  <label>Isi terverifikasi<textarea rows={12} value={knowledge.content} onChange={(event) => setKnowledge({ ...knowledge, content: event.target.value })} required /></label>
                  <p className="content-help">Tuliskan fakta singkat dan spesifik. Hindari data pribadi pelanggan.</p>
                  <button className="button button-dark" disabled={pending}>{pending ? 'Menyimpan…' : knowledge.id ? 'Simpan perubahan' : 'Tambah pengetahuan'}</button>
                </form>

                <section className="content-list">
                  {data.knowledge_documents.length === 0 ? (
                    <div className="admin-empty">Belum ada dokumen pengetahuan.</div>
                  ) : data.knowledge_documents.map((item) => (
                    <article key={item.id}>
                      <div>
                        <span className={item.status === 'active' ? 'status-active' : 'status-draft'}>{item.status}</span>
                        <small>{item.type} · diperbarui {new Date(item.updated_at).toLocaleDateString('id-ID')}</small>
                      </div>
                      <h3>{item.title}</h3>
                      <p className="content-preview">{item.content}</p>
                      <footer>
                        <button type="button" onClick={() => setKnowledge(item)}>Edit</button>
                        <button type="button" className="danger" onClick={() => remove('knowledge', item.id, `dokumen ${item.title}`)}>Hapus</button>
                      </footer>
                    </article>
                  ))}
                </section>
              </div>
            )}

            {tab === 'testimoni' && (
              <div className="content-manager-grid">
                <form
                  className="content-editor"
                  onSubmit={(event) => save(
                    event,
                    'testimonials',
                    testimonial,
                    () => setTestimonial(testimonialBlank),
                  )}
                >
                  <header>
                    <div>
                      <small>{testimonial.id ? 'Edit testimoni' : 'Testimoni baru'}</small>
                      <h2>{testimonial.id ? testimonial.customer_name : 'Tambah bukti pelanggan'}</h2>
                    </div>
                    {testimonial.id > 0 && <button type="button" onClick={() => setTestimonial(testimonialBlank)}>Batal</button>}
                  </header>
                  <div className="content-form-row">
                    <label>Nama pelanggan<input value={testimonial.customer_name} onChange={(event) => setTestimonial({ ...testimonial, customer_name: event.target.value })} required /></label>
                    <label>Peran / usaha<input value={testimonial.customer_role} onChange={(event) => setTestimonial({ ...testimonial, customer_role: event.target.value })} /></label>
                  </div>
                  <label>Kutipan<textarea rows={6} value={testimonial.quote} onChange={(event) => setTestimonial({ ...testimonial, quote: event.target.value })} required /></label>
                  <div className="content-form-row">
                    <label>Rating<select value={testimonial.rating} onChange={(event) => setTestimonial({ ...testimonial, rating: Number(event.target.value) })}>
                      {[5, 4, 3, 2, 1].map((item) => <option value={item} key={item}>{item} bintang</option>)}
                    </select></label>
                    <label>Urutan<input type="number" min={0} max={999} value={testimonial.display_order} onChange={(event) => setTestimonial({ ...testimonial, display_order: Number(event.target.value) })} /></label>
                  </div>
                  <label>Sumber<input value={testimonial.source_label} onChange={(event) => setTestimonial({ ...testimonial, source_label: event.target.value })} /></label>
                  <label className="content-check"><input type="checkbox" checked={testimonial.is_published} onChange={(event) => setTestimonial({
                    ...testimonial,
                    is_published: event.target.checked,
                    consent_at: event.target.checked && !testimonial.consent_at ? localDateTime(new Date().toISOString()) : testimonial.consent_at,
                  })} /><span>Publikasikan di landing page</span></label>
                  {testimonial.is_published && (
                    <label>Waktu persetujuan pelanggan<input type="datetime-local" value={testimonial.consent_at} onChange={(event) => setTestimonial({ ...testimonial, consent_at: event.target.value })} required /></label>
                  )}
                  <button className="button button-dark" disabled={pending}>{pending ? 'Menyimpan…' : testimonial.id ? 'Simpan perubahan' : 'Tambah testimoni'}</button>
                </form>

                <section className="content-list">
                  {data.testimonials.length === 0 ? (
                    <div className="admin-empty">Belum ada testimoni.</div>
                  ) : data.testimonials.map((item) => (
                    <article key={item.id}>
                      <div>
                        <span className={item.is_published ? 'status-active' : 'status-draft'}>{item.is_published ? 'Tayang' : 'Draft'}</span>
                        <small>{'★'.repeat(item.rating)} · urutan {item.display_order}</small>
                      </div>
                      <blockquote>“{item.quote}”</blockquote>
                      <strong>{item.customer_name}</strong>
                      <small>{item.customer_role || item.source_label || 'Pelanggan'}</small>
                      <footer>
                        <button type="button" onClick={() => setTestimonial({
                          ...item,
                          customer_role: item.customer_role || '',
                          source_label: item.source_label || '',
                          consent_at: localDateTime(item.consent_at),
                        })}>Edit</button>
                        <button type="button" className="danger" onClick={() => remove('testimonials', item.id, `testimoni ${item.customer_name}`)}>Hapus</button>
                      </footer>
                    </article>
                  ))}
                </section>
              </div>
            )}
          </>
        )}
      </section>
    </main>
  );
}
