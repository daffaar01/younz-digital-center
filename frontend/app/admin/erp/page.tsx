'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import styles from './page.module.css';
import OperationsPanel from './operations-panel';
import {
  createProduct,
  createMidtransCheckout,
  createTransaction,
  getApiHealth,
  getCashAccounts,
  getCustomers,
  getPaymentMethods,
  getProjects,
  getProducts,
  getProviders,
  getTransactions,
  login,
  type ApiHealth,
  type CashAccount,
  type CustomerRecord,
  type PaymentMethod,
  type Product,
  type Provider,
  type Project,
  type Transaction,
  type TransactionInput,
} from '@/lib/younz-erp-api';
import {
  enqueueOfflineOperation,
  getOfflineQueueCount,
  isNetworkError,
  syncOfflineQueue,
} from '@/lib/younz-erp-offline-queue';

type Session = {
  token: string;
  businessId: string;
  outletId: string;
  userName: string;
  businessName: string;
};

const currency = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
});

function money(value: string | number) {
  return currency.format(Number(value) || 0);
}

export default function Home() {
  const [health, setHealth] = useState<ApiHealth | null>(null);
  const [session, setSession] = useState<Session | null>(null);
  const [ready, setReady] = useState(false);
  const [activeTab, setActiveTab] = useState<'products' | 'transactions' | 'operations'>('products');
  const [products, setProducts] = useState<Product[]>([]);
  const [customers, setCustomers] = useState<CustomerRecord[]>([]);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [cashAccounts, setCashAccounts] = useState<CashAccount[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [pendingOfflineCount, setPendingOfflineCount] = useState(0);
  const [syncingOffline, setSyncingOffline] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [checkoutLoadingId, setCheckoutLoadingId] = useState<string | null>(null);
  const [loginForm, setLoginForm] = useState({ email: 'admin@younz.local', password: 'password' });
  const [productForm, setProductForm] = useState({
    name: '',
    sku: '',
    product_type: 'goods',
    cost_price: '',
    selling_price: '',
    is_stockable: true,
    provider_id: '',
  });
  const [transactionForm, setTransactionForm] = useState({
    productId: '',
    customerId: '',
    projectId: '',
    quantity: '1',
    amount: '',
    paymentMethodId: '',
    cashAccountId: '',
    destination: '',
  });

  const selectedProduct = useMemo(
    () => products.find((product) => product.id === transactionForm.productId),
    [products, transactionForm.productId],
  );
  const transactionTotal =
    (Number(selectedProduct?.selling_price) || 0) * (Number(transactionForm.quantity) || 0);
  const isDigiflazzProduct = Boolean(selectedProduct?.provider?.name?.toLowerCase().includes('digiflazz'));

  useEffect(() => {
    void getApiHealth().then(setHealth).catch(() => setHealth(null));

    const token = window.localStorage.getItem('younz_token');
    const businessId = window.localStorage.getItem('younz_business_id');
    const outletId = window.localStorage.getItem('younz_outlet_id');
    const userName = window.localStorage.getItem('younz_user_name') ?? 'Pengguna';
    const businessName = window.localStorage.getItem('younz_business_name') ?? 'Bisnis aktif';

    queueMicrotask(() => {
      if (token && businessId && outletId) {
        setSession({ token, businessId, outletId, userName, businessName });
      }
      setReady(true);
    });
  }, []);

  useEffect(() => {
    const refreshPendingCount = () => setPendingOfflineCount(getOfflineQueueCount());
    refreshPendingCount();
    window.addEventListener('younz-offline-queue-changed', refreshPendingCount);
    return () => window.removeEventListener('younz-offline-queue-changed', refreshPendingCount);
  }, []);

  useEffect(() => {
    if (!session) return;

    void Promise.resolve().then(() => {
      setLoading(true);
      setError(null);
      return Promise.all([
        getProducts(session.token, session.businessId),
        getCustomers(session.token, session.businessId),
        getTransactions(session.token, session.businessId),
        getPaymentMethods(session.token, session.businessId),
        getCashAccounts(session.token, session.businessId),
        getProjects(session.token, session.businessId).catch(() => ({ data: [] as Project[] })),
        getProviders(session.token, session.businessId).catch(() => ({ data: [] as Provider[] })),
      ])
        .then(([productResponse, customerResponse, transactionResponse, methodResponse, cashResponse, projectResponse, providerResponse]) => {
          setProducts(productResponse.data);
          setCustomers(customerResponse.data);
          setTransactions(transactionResponse.data);
          setPaymentMethods(methodResponse.data);
          setCashAccounts(cashResponse.data);
          setProjects(projectResponse.data);
          setProviders(providerResponse.data);
          setTransactionForm((current) => ({
            ...current,
            productId: current.productId || productResponse.data[0]?.id || '',
            paymentMethodId: current.paymentMethodId || methodResponse.data[0]?.id || '',
            cashAccountId: current.cashAccountId || cashResponse.data[0]?.id || '',
          }));
        })
        .catch((reason: Error) => setError(reason.message))
        .finally(() => setLoading(false));
    });
  }, [session]);

  async function syncPendingOffline() {
    if (!session || pendingOfflineCount === 0) return;
    setSyncingOffline(true);
    setError(null);
    try {
      const result = await syncOfflineQueue(session.token, session.businessId);
      setPendingOfflineCount(result.remaining);
      if (result.synced > 0) {
        setTransactions((await getTransactions(session.token, session.businessId)).data);
        setMessage(`${result.synced} transaksi offline berhasil disinkronkan.`);
      }
      if (result.discarded > 0) {
        setError(`${result.discarded} antrean dibuang karena ditolak server.`);
      }
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Sinkronisasi offline gagal.');
    } finally {
      setSyncingOffline(false);
    }
  }

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);

    try {
      const response = await login(loginForm.email, loginForm.password);
      const data = response.data;
      const nextSession = {
        token: data.token,
        businessId: data.business.id,
        outletId: data.outlet.id,
        userName: data.user.name,
        businessName: data.business.name,
      };
      window.localStorage.setItem('younz_token', nextSession.token);
      window.localStorage.setItem('younz_business_id', nextSession.businessId);
      window.localStorage.setItem('younz_outlet_id', nextSession.outletId);
      window.localStorage.setItem('younz_user_name', nextSession.userName);
      window.localStorage.setItem('younz_business_name', nextSession.businessName);
      setSession(nextSession);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Login gagal.');
    } finally {
      setLoading(false);
    }
  }

  function handleLogout() {
    ['younz_token', 'younz_business_id', 'younz_outlet_id', 'younz_user_name', 'younz_business_name'].forEach(
      (key) => window.localStorage.removeItem(key),
    );
    setSession(null);
    setProducts([]);
    setCustomers([]);
    setTransactions([]);
    setPaymentMethods([]);
    setCashAccounts([]);
    setProviders([]);
    setProjects([]);
    setMessage(null);
    setError(null);
  }

  async function handleProductSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!session) return;

    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createProduct(session.token, session.businessId, {
        name: productForm.name,
        sku: productForm.sku || undefined,
        product_type: productForm.product_type,
        cost_price: Number(productForm.cost_price),
        selling_price: Number(productForm.selling_price),
        is_stockable: productForm.is_stockable,
        provider_id: productForm.provider_id || undefined,
      });
      const response = await getProducts(session.token, session.businessId);
      setProducts(response.data);
      setProductForm({
        name: '',
        sku: '',
        product_type: 'goods',
        cost_price: '',
        selling_price: '',
        is_stockable: true,
        provider_id: '',
      });
      setMessage('Produk berhasil ditambahkan.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Produk gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function handleTransactionSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!session || !selectedProduct || !transactionForm.paymentMethodId) {
      setError('Pilih produk dan metode pembayaran terlebih dahulu.');
      return;
    }
    if (isDigiflazzProduct && transactionForm.destination.trim() === '') {
      setError('Nomor tujuan wajib diisi untuk produk Digiflazz.');
      return;
    }

    setLoading(true);
    setError(null);
    setMessage(null);
    const payload: TransactionInput = {
      outlet_id: session.outletId,
      customer_id: transactionForm.customerId || undefined,
      project_id: transactionForm.projectId || undefined,
      client_reference: `web-${crypto.randomUUID()}`,
      items: [{
        product_id: selectedProduct.id,
        quantity: Number(transactionForm.quantity),
        ...(isDigiflazzProduct ? { details: { destination: transactionForm.destination.trim() } } : {}),
      }],
      payments: [
        {
          payment_method_id: transactionForm.paymentMethodId,
          cash_account_id: transactionForm.cashAccountId || undefined,
          amount: Number(transactionForm.amount) || transactionTotal,
        },
      ],
    };
    try {
      await createTransaction(session.token, session.businessId, payload);
      setTransactions((await getTransactions(session.token, session.businessId)).data);
      setTransactionForm((current) => ({ ...current, amount: '' }));
      setMessage('Transaksi berhasil dibuat.');
    } catch (reason) {
      if (isNetworkError(reason)) {
        const count = enqueueOfflineOperation('transaction', session.token, session.businessId, payload);
        setPendingOfflineCount(count);
        setTransactionForm((current) => ({ ...current, amount: '' }));
        setMessage('API belum terhubung. Transaksi disimpan di antrean offline.');
      } else {
        setError(reason instanceof Error ? reason.message : 'Transaksi gagal dibuat.');
      }
    } finally {
      setLoading(false);
    }
  }

  async function handleMidtransCheckout(transactionId: string) {
    if (!session) return;
    setCheckoutLoadingId(transactionId);
    setError(null);
    try {
      const response = await createMidtransCheckout(session.token, session.businessId, transactionId);
      window.open(response.data.redirect_url, '_blank', 'noopener,noreferrer');
      setMessage('Halaman pembayaran Midtrans dibuka. Status akan diperbarui setelah webhook diterima.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Checkout Midtrans gagal dibuat.');
    } finally {
      setCheckoutLoadingId(null);
    }
  }

  if (!ready) {
    return <main className={`${styles.centerPage} ${styles.erpWorkspace}`}>Memuat YOUNZ ERP...</main>;
  }

  if (!session) {
    return (
      <main className={`${styles.authPage} ${styles.erpWorkspace}`}>
        <section className={styles.authCard}>
          <div className={styles.brandMark}>Y</div>
          <p className={styles.eyebrow}>YOUNZ ERP</p>
          <h1>Kelola bisnis dari satu tempat.</h1>
          <p className={styles.muted}>Login untuk mengatur produk dan mencatat transaksi melalui Laravel API.</p>
          <form className={styles.form} onSubmit={handleLogin}>
            <label>
              Email
              <input
                type="email"
                value={loginForm.email}
                onChange={(event) => setLoginForm({ ...loginForm, email: event.target.value })}
                required
              />
            </label>
            <label>
              Password
              <input
                type="password"
                value={loginForm.password}
                onChange={(event) => setLoginForm({ ...loginForm, password: event.target.value })}
                required
              />
            </label>
            {error && <p className={styles.error}>{error}</p>}
            <button className={styles.primaryButton} disabled={loading} type="submit">
              {loading ? 'Memproses...' : 'Login ke dashboard'}
            </button>
          </form>
          <div className={styles.apiStatus}>
            <span className={health ? styles.dotOnline : styles.dotOffline} />
            Backend {health ? 'terhubung' : 'belum terhubung'}
          </div>
        </section>
      </main>
    );
  }

  return (
    <main className={`${styles.appShell} ${styles.erpWorkspace}`}>
      <header className={styles.topbar}>
        <div className={styles.brandBlock}>
          <div className={styles.smallMark}>Y</div>
          <div>
            <p className={styles.eyebrow}>YOUNZ ERP</p>
            <strong>{session.businessName}</strong>
          </div>
        </div>
        <div className={styles.userBlock}>
          <span>{session.userName}</span>
          {pendingOfflineCount > 0 && <button className={styles.textButton} disabled={syncingOffline} onClick={() => void syncPendingOffline()} type="button">{syncingOffline ? 'Sinkronisasi...' : `${pendingOfflineCount} offline`}</button>}
          <button className={styles.textButton} onClick={handleLogout} type="button">
            Keluar
          </button>
        </div>
      </header>

      <section className={styles.content}>
        <div className={styles.intro}>
          <div>
            <p className={styles.eyebrow}>DASHBOARD</p>
            <h1>Operasional bisnis lebih rapi.</h1>
            <p className={styles.muted}>Buat produk, pilih item, lalu catat penjualan dari satu dashboard.</p>
          </div>
          <span className={health ? styles.statusPillOnline : styles.statusPillOffline}>
            {health ? 'API online' : 'API offline'}
          </span>
        </div>

        <div className={styles.statsGrid}>
          <div className={styles.statCard}><span>Produk aktif</span><strong>{products.length}</strong></div>
          <div className={styles.statCard}><span>Pelanggan</span><strong>{customers.length}</strong></div>
          <div className={styles.statCard}><span>Riwayat transaksi</span><strong>{transactions.length}</strong></div>
        </div>

        <nav className={styles.tabs} aria-label="Menu utama">
          <button className={activeTab === 'products' ? styles.tabActive : styles.tab} onClick={() => setActiveTab('products')} type="button">
            Produk
          </button>
          <button className={activeTab === 'transactions' ? styles.tabActive : styles.tab} onClick={() => setActiveTab('transactions')} type="button">
            Transaksi
          </button>
          <button className={activeTab === 'operations' ? styles.tabActive : styles.tab} onClick={() => setActiveTab('operations')} type="button">
            Modul bisnis
          </button>
        </nav>

        {error && <div className={styles.alertError}>{error}</div>}
        {message && <div className={styles.alertSuccess}>{message}</div>}

        {activeTab === 'operations' ? (
          <OperationsPanel session={session} />
        ) : activeTab === 'products' ? (
          <section className={styles.workspaceGrid}>
            <div className={styles.card}>
              <div className={styles.cardHeading}>
                <div><p className={styles.eyebrow}>KATALOG</p><h2>Produk aktif</h2></div>
                {loading && <span className={styles.muted}>Memuat...</span>}
              </div>
              {products.length === 0 ? (
                <div className={styles.emptyState}>Belum ada produk. Tambahkan produk pertama di samping.</div>
              ) : (
                <div className={styles.tableWrap}>
                  <table>
                    <thead><tr><th>Produk</th><th>SKU</th><th>Harga jual</th><th>Stok</th></tr></thead>
                    <tbody>
                      {products.map((product) => (
                        <tr key={product.id}>
                          <td><strong>{product.name}</strong><small>{product.product_type}</small></td>
                          <td>{product.sku || '-'}</td>
                          <td>{money(product.selling_price)}</td>
                          <td><span className={product.is_stockable ? styles.badge : styles.badgeMuted}>{product.is_stockable ? 'Aktif' : 'Non-stok'}</span></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <form className={styles.card} onSubmit={handleProductSubmit}>
              <div className={styles.cardHeading}><div><p className={styles.eyebrow}>MASTER DATA</p><h2>Tambah produk</h2></div></div>
              <div className={styles.form}>
                <label>Nama produk<input value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} required /></label>
                <label>SKU <span className={styles.optional}>opsional</span><input value={productForm.sku} onChange={(event) => setProductForm({ ...productForm, sku: event.target.value })} /></label>
                <div className={styles.twoColumns}>
                  <label>Jenis<select value={productForm.product_type} onChange={(event) => setProductForm({ ...productForm, product_type: event.target.value })}><option value="goods">Barang</option><option value="service">Jasa</option><option value="digital">Digital</option><option value="utility">Utilitas</option></select></label>
                  <label>Harga modal<input type="number" min="0" value={productForm.cost_price} onChange={(event) => setProductForm({ ...productForm, cost_price: event.target.value })} required /></label>
                </div>
                <label>Provider <span className={styles.optional}>untuk produk digital/pascabayar</span><select value={productForm.provider_id} onChange={(event) => setProductForm({ ...productForm, provider_id: event.target.value })}><option value="">Tanpa provider</option>{providers.map((provider) => <option key={provider.id} value={provider.id}>{provider.name} · {money(provider.balance)}</option>)}</select></label>
                <label>Harga jual<input type="number" min="0" value={productForm.selling_price} onChange={(event) => setProductForm({ ...productForm, selling_price: event.target.value })} required /></label>
                <label className={styles.checkbox}><input type="checkbox" checked={productForm.is_stockable} onChange={(event) => setProductForm({ ...productForm, is_stockable: event.target.checked })} /> Produk mengurangi stok</label>
                <button className={styles.primaryButton} disabled={loading} type="submit">{loading ? 'Menyimpan...' : 'Simpan produk'}</button>
              </div>
            </form>
          </section>
        ) : (
          <section className={styles.workspaceGrid}>
            <form className={styles.card} onSubmit={handleTransactionSubmit}>
              <div className={styles.cardHeading}><div><p className={styles.eyebrow}>POINT OF SALE</p><h2>Buat transaksi</h2></div></div>
              {products.length === 0 ? <div className={styles.emptyState}>Tambahkan produk terlebih dahulu agar transaksi dapat dibuat.</div> : <div className={styles.form}>
                <label>Pelanggan <span className={styles.optional}>opsional</span><select value={transactionForm.customerId} onChange={(event) => setTransactionForm({ ...transactionForm, customerId: event.target.value })}><option value="">Pelanggan umum</option>{customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}</select></label>
                <label>Proyek <span className={styles.optional}>opsional</span><select value={transactionForm.projectId} onChange={(event) => setTransactionForm({ ...transactionForm, projectId: event.target.value })}><option value="">Tidak terkait proyek</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label>
                <label>Produk<select value={transactionForm.productId} onChange={(event) => setTransactionForm({ ...transactionForm, productId: event.target.value })}>{products.map((product) => <option key={product.id} value={product.id}>{product.name} · {money(product.selling_price)}</option>)}</select></label>
                {isDigiflazzProduct && <label>Nomor tujuan / ID pelanggan<input inputMode="numeric" value={transactionForm.destination} onChange={(event) => setTransactionForm({ ...transactionForm, destination: event.target.value })} placeholder="Contoh: 081234567890" required /></label>}
                <div className={styles.twoColumns}>
                  <label>Jumlah<input type="number" min="0.01" step="0.01" value={transactionForm.quantity} onChange={(event) => setTransactionForm({ ...transactionForm, quantity: event.target.value })} required /></label>
                  <label>Total<input value={money(transactionTotal)} readOnly /></label>
                </div>
                <label>Nominal dibayar<input type="number" min="0.01" step="0.01" placeholder={String(transactionTotal)} value={transactionForm.amount} onChange={(event) => setTransactionForm({ ...transactionForm, amount: event.target.value })} required /></label>
                <label>Metode pembayaran<select value={transactionForm.paymentMethodId} onChange={(event) => setTransactionForm({ ...transactionForm, paymentMethodId: event.target.value })} required>{paymentMethods.map((method) => <option key={method.id} value={method.id}>{method.name}</option>)}</select></label>
                <label>Akun kas<select value={transactionForm.cashAccountId} onChange={(event) => setTransactionForm({ ...transactionForm, cashAccountId: event.target.value })}><option value="">Tidak dicatat ke kas</option>{cashAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {money(account.balance)}</option>)}</select></label>
                <button className={styles.primaryButton} disabled={loading || paymentMethods.length === 0} type="submit">{loading ? 'Menyimpan...' : 'Selesaikan transaksi'}</button>
              </div>}
            </form>
            <div className={styles.card + ' ' + styles.receiptCard}>
              <p className={styles.eyebrow}>RINGKASAN</p>
              <h2>Checkout sederhana</h2>
              <div className={styles.receiptLine}><span>Item</span><strong>{selectedProduct?.name || 'Belum dipilih'}</strong></div>
              <div className={styles.receiptLine}><span>Jumlah</span><strong>{transactionForm.quantity || '0'}</strong></div>
              <div className={styles.receiptTotal}><span>Total</span><strong>{money(transactionTotal)}</strong></div>
              <p className={styles.muted}>Transaksi akan dihitung ulang oleh server, termasuk total, status pembayaran, dan laba.</p>
            </div>
            <div className={styles.card}>
              <div className={styles.cardHeading}><div><p className={styles.eyebrow}>RIWAYAT</p><h2>Transaksi terbaru</h2></div><span className={styles.muted}>{transactions.length} transaksi</span></div>
              {transactions.length === 0 ? <div className={styles.emptyState}>Belum ada riwayat transaksi.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Nomor</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{transactions.map((transaction) => <tr key={transaction.id}><td><strong>{transaction.transaction_number}</strong><small>{new Date(transaction.transaction_date).toLocaleString('id-ID')}</small></td><td>{transaction.customer?.name || 'Umum'}</td><td>{money(transaction.total_amount)}</td><td><span className={transaction.payment_status === 'paid' ? styles.badge : styles.badgeMuted}>{transaction.payment_status}</span></td><td>{transaction.payment_status !== 'paid' && <button className={styles.textButton} disabled={checkoutLoadingId === transaction.id} onClick={() => void handleMidtransCheckout(transaction.id)} type="button">{checkoutLoadingId === transaction.id ? 'Menyiapkan...' : 'Bayar Midtrans'}</button>}</td></tr>)}</tbody></table></div>}
            </div>
          </section>
        )}
      </section>
    </main>
  );
}
