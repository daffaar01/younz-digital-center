'use client';

import { FormEvent, useEffect, useState } from 'react';
import styles from './page.module.css';
import {
  adjustStock,
  createCustomer,
  createExpense,
  createIncome,
  createRole,
  createPurchase,
  createProject,
  createProvider,
  createSupplier,
  getExpenses,
  getFinanceSummary,
  getDigiflazzBalance,
  getCustomers,
  getIntegrationStatus,
  checkMidtransConnection,
  getCashAccounts,
  getIncomes,
  getPermissions,
  getProducts,
  getProjects,
  getPurchases,
  getProviders,
  getReportSummary,
  getRoles,
  getStock,
  getSuppliers,
  syncDigiflazzCatalog,
  type FinanceEntry,
  type FinanceSummary,
  type IntegrationStatus,
  type Project,
  type Provider,
  type CustomerRecord,
  type CashAccount,
  type PermissionRecord,
  type Product,
  type Purchase,
  type PurchaseInput,
  type ReportSummary,
  type RoleRecord,
  type StockBalance,
  type SupplierRecord,
} from '@/lib/younz-erp-api';
import { enqueueOfflineOperation, isNetworkError } from '@/lib/younz-erp-offline-queue';

type Session = { token: string; businessId: string; outletId: string };
type ModuleTab = 'stock' | 'customers' | 'suppliers' | 'purchases' | 'projects' | 'providers' | 'finance' | 'reports' | 'roles';

const emptyContact = { name: '', phone: '', email: '', address: '' };

function money(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

export default function OperationsPanel({ session }: { session: Session }) {
  const [activeModule, setActiveModule] = useState<ModuleTab>('stock');
  const [products, setProducts] = useState<Product[]>([]);
  const [customers, setCustomers] = useState<CustomerRecord[]>([]);
  const [suppliers, setSuppliers] = useState<SupplierRecord[]>([]);
  const [purchases, setPurchases] = useState<Purchase[]>([]);
  const [stock, setStock] = useState<StockBalance[]>([]);
  const [report, setReport] = useState<ReportSummary | null>(null);
  const [roles, setRoles] = useState<RoleRecord[]>([]);
  const [permissions, setPermissions] = useState<PermissionRecord[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [financeSummary, setFinanceSummary] = useState<FinanceSummary | null>(null);
  const [incomes, setIncomes] = useState<FinanceEntry[]>([]);
  const [expenses, setExpenses] = useState<FinanceEntry[]>([]);
  const [cashAccounts, setCashAccounts] = useState<CashAccount[]>([]);
  const [integrationStatus, setIntegrationStatus] = useState<IntegrationStatus | null>(null);
  const [digiflazzBalance, setDigiflazzBalance] = useState<number | null>(null);
  const [midtransConnection, setMidtransConnection] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [customerForm, setCustomerForm] = useState(emptyContact);
  const [supplierForm, setSupplierForm] = useState(emptyContact);
  const [stockForm, setStockForm] = useState({ productId: '', movementType: 'in' as 'in' | 'out' | 'adjustment', quantity: '1', minimumQuantity: '0', note: '' });
  const [roleForm, setRoleForm] = useState({ name: '', slug: '', description: '', permissionIds: [] as string[] });
  const [purchaseForm, setPurchaseForm] = useState({ supplierId: '', productId: '', quantity: '1', unitCost: '' });
  const [providerForm, setProviderForm] = useState({ name: '', providerType: 'digital', accountIdentifier: '', openingBalance: '', notes: '' });
  const [projectForm, setProjectForm] = useState({ customerId: '', name: '', projectType: 'website', quotedAmount: '', dueDate: '', description: '' });
  const [incomeForm, setIncomeForm] = useState({ projectId: '', cashAccountId: '', category: 'project_payment', amount: '', description: '' });
  const [expenseForm, setExpenseForm] = useState({ projectId: '', providerId: '', cashAccountId: '', category: 'operational', amount: '', description: '' });

  useEffect(() => {
    void Promise.resolve().then(() => {
      setLoading(true);
      setError(null);
      return Promise.all([
        getProducts(session.token, session.businessId),
        getCustomers(session.token, session.businessId),
        getSuppliers(session.token, session.businessId),
        getPurchases(session.token, session.businessId).catch(() => ({ data: [] as Purchase[] })),
        getStock(session.token, session.businessId),
        getReportSummary(session.token, session.businessId),
        getRoles(session.token, session.businessId).catch(() => ({ data: [] as RoleRecord[] })),
        getPermissions(session.token, session.businessId).catch(() => ({ data: [] as PermissionRecord[] })),
        getProjects(session.token, session.businessId).catch(() => ({ data: [] as Project[] })),
        getProviders(session.token, session.businessId).catch(() => ({ data: [] as Provider[] })),
        getFinanceSummary(session.token, session.businessId).catch(() => ({ data: null as FinanceSummary | null })),
        getIncomes(session.token, session.businessId).catch(() => ({ data: [] as FinanceEntry[] })),
        getExpenses(session.token, session.businessId).catch(() => ({ data: [] as FinanceEntry[] })),
        getCashAccounts(session.token, session.businessId).catch(() => ({ data: [] as CashAccount[] })),
        getIntegrationStatus(session.token, session.businessId).catch(() => ({ data: null as IntegrationStatus | null })),
      ]).then(([productResponse, customerResponse, supplierResponse, purchaseResponse, stockResponse, reportResponse, roleResponse, permissionResponse, projectResponse, providerResponse, financeResponse, incomeResponse, expenseResponse, cashAccountResponse, integrationResponse]) => {
        setProducts(productResponse.data);
        setCustomers(customerResponse.data);
        setSuppliers(supplierResponse.data);
        setPurchases(purchaseResponse.data);
        setStock(stockResponse.data);
        setReport(reportResponse.data);
        setRoles(roleResponse.data);
        setPermissions(permissionResponse.data);
        setProjects(projectResponse.data);
        setProviders(providerResponse.data);
        setFinanceSummary(financeResponse.data);
        setIncomes(incomeResponse.data);
        setExpenses(expenseResponse.data);
        setCashAccounts(cashAccountResponse.data);
        setIntegrationStatus(integrationResponse.data);
        setStockForm((current) => ({ ...current, productId: current.productId || productResponse.data[0]?.id || '' }));
        setPurchaseForm((current) => ({ ...current, productId: current.productId || productResponse.data.find((product) => product.is_stockable)?.id || '', supplierId: current.supplierId || supplierResponse.data[0]?.id || '' }));
        setRoleForm((current) => ({ ...current, permissionIds: current.permissionIds.length ? current.permissionIds : permissionResponse.data.map((permission) => permission.id) }));
      }).catch((reason: Error) => setError(reason.message)).finally(() => setLoading(false));
    });
  }, [session]);

  useEffect(() => {
    const refreshAfterSync = () => {
      void Promise.all([
        getPurchases(session.token, session.businessId).catch(() => ({ data: [] as Purchase[] })),
        getStock(session.token, session.businessId),
      ]).then(([purchaseResponse, stockResponse]) => {
        setPurchases(purchaseResponse.data);
        setStock(stockResponse.data);
      });
    };
    window.addEventListener('younz-offline-queue-synced', refreshAfterSync);
    return () => window.removeEventListener('younz-offline-queue-synced', refreshAfterSync);
  }, [session]);

  async function refreshContacts() {
    const [customerResponse, supplierResponse] = await Promise.all([
      getCustomers(session.token, session.businessId),
      getSuppliers(session.token, session.businessId),
    ]);
    setCustomers(customerResponse.data);
    setSuppliers(supplierResponse.data);
  }

  async function handleContactSubmit(event: FormEvent<HTMLFormElement>, type: 'customer' | 'supplier') {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      if (type === 'customer') {
        await createCustomer(session.token, session.businessId, customerForm);
        setCustomerForm(emptyContact);
        setMessage('Pelanggan berhasil ditambahkan.');
      } else {
        await createSupplier(session.token, session.businessId, supplierForm);
        setSupplierForm(emptyContact);
        setMessage('Supplier berhasil ditambahkan.');
      }
      await refreshContacts();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Data gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function handleStockSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!stockForm.productId) return;
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await adjustStock(session.token, session.businessId, {
        product_id: stockForm.productId,
        outlet_id: session.outletId,
        movement_type: stockForm.movementType,
        quantity: Number(stockForm.quantity),
        minimum_quantity: Number(stockForm.minimumQuantity),
        note: stockForm.note || undefined,
      });
      const response = await getStock(session.token, session.businessId);
      setStock(response.data);
      setMessage('Perubahan stok berhasil dicatat.');
      setStockForm((current) => ({ ...current, quantity: '1', note: '' }));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Stok gagal diperbarui.');
    } finally {
      setLoading(false);
    }
  }

  async function handleRoleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createRole(session.token, session.businessId, {
        name: roleForm.name,
        slug: roleForm.slug,
        description: roleForm.description || undefined,
        permission_ids: roleForm.permissionIds,
      });
      setRoles((await getRoles(session.token, session.businessId)).data);
      setRoleForm({ name: '', slug: '', description: '', permissionIds: permissions.map((permission) => permission.id) });
      setMessage('Role berhasil dibuat.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Role gagal dibuat.');
    } finally {
      setLoading(false);
    }
  }

  async function handlePurchaseSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!purchaseForm.productId) return;
    setLoading(true);
    setError(null);
    setMessage(null);
    const payload: PurchaseInput = {
      outlet_id: session.outletId,
      supplier_id: purchaseForm.supplierId || undefined,
      client_reference: `web-purchase-${crypto.randomUUID()}`,
      items: [{ product_id: purchaseForm.productId, quantity: Number(purchaseForm.quantity), unit_cost: Number(purchaseForm.unitCost) }],
    };
    try {
      await createPurchase(session.token, session.businessId, payload);
      setPurchases((await getPurchases(session.token, session.businessId)).data);
      setStock((await getStock(session.token, session.businessId)).data);
      setPurchaseForm((current) => ({ ...current, quantity: '1', unitCost: '' }));
      setMessage('Pembelian berhasil dicatat dan stok bertambah.');
    } catch (reason) {
      if (isNetworkError(reason)) {
        enqueueOfflineOperation('purchase', session.token, session.businessId, payload);
        setPurchaseForm((current) => ({ ...current, quantity: '1', unitCost: '' }));
        setMessage('API belum terhubung. Pembelian disimpan di antrean offline.');
      } else {
        setError(reason instanceof Error ? reason.message : 'Pembelian gagal disimpan.');
      }
    } finally {
      setLoading(false);
    }
  }

  async function handleProviderSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createProvider(session.token, session.businessId, {
        name: providerForm.name,
        provider_type: providerForm.providerType,
        account_identifier: providerForm.accountIdentifier || undefined,
        opening_balance: Number(providerForm.openingBalance) || 0,
        notes: providerForm.notes || undefined,
      });
      setProviders((await getProviders(session.token, session.businessId)).data);
      setProviderForm({ name: '', providerType: 'digital', accountIdentifier: '', openingBalance: '', notes: '' });
      setMessage('Provider berhasil ditambahkan.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Provider gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function handleProjectSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createProject(session.token, session.businessId, {
        outlet_id: session.outletId,
        customer_id: projectForm.customerId || undefined,
        name: projectForm.name,
        project_type: projectForm.projectType,
        quoted_amount: Number(projectForm.quotedAmount) || 0,
        due_date: projectForm.dueDate || undefined,
        description: projectForm.description || undefined,
      });
      setProjects((await getProjects(session.token, session.businessId)).data);
      setProjectForm({ customerId: '', name: '', projectType: 'website', quotedAmount: '', dueDate: '', description: '' });
      setMessage('Proyek berhasil dibuat.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Proyek gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function handleIncomeSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createIncome(session.token, session.businessId, {
        outlet_id: session.outletId,
        project_id: incomeForm.projectId || undefined,
        cash_account_id: incomeForm.cashAccountId || undefined,
        category: incomeForm.category,
        amount: Number(incomeForm.amount),
        description: incomeForm.description,
      });
      await refreshFinance();
      setIncomeForm({ projectId: '', cashAccountId: '', category: 'project_payment', amount: '', description: '' });
      setMessage('Pemasukan berhasil dicatat.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Pemasukan gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function handleExpenseSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      await createExpense(session.token, session.businessId, {
        outlet_id: session.outletId,
        project_id: expenseForm.projectId || undefined,
        provider_id: expenseForm.providerId || undefined,
        cash_account_id: expenseForm.cashAccountId || undefined,
        category: expenseForm.category,
        amount: Number(expenseForm.amount),
        description: expenseForm.description,
      });
      await refreshFinance();
      setExpenseForm({ projectId: '', providerId: '', cashAccountId: '', category: 'operational', amount: '', description: '' });
      setMessage('Pengeluaran berhasil dicatat.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Pengeluaran gagal disimpan.');
    } finally {
      setLoading(false);
    }
  }

  async function refreshFinance() {
    const [summaryResponse, incomeResponse, expenseResponse, projectResponse] = await Promise.all([
      getFinanceSummary(session.token, session.businessId),
      getIncomes(session.token, session.businessId),
      getExpenses(session.token, session.businessId),
      getProjects(session.token, session.businessId),
    ]);
    setFinanceSummary(summaryResponse.data);
    setIncomes(incomeResponse.data);
    setExpenses(expenseResponse.data);
    setProjects(projectResponse.data);
  }

  async function checkLiveIntegrations() {
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      const [balanceResponse, midtransResponse] = await Promise.all([
        getDigiflazzBalance(session.token, session.businessId),
        checkMidtransConnection(session.token, session.businessId),
      ]);
      setDigiflazzBalance(Number(balanceResponse.data.deposit) || 0);
      setMidtransConnection(midtransResponse.data.environment);
      setMessage('Koneksi production Midtrans dan Digiflazz berhasil diverifikasi.');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Koneksi provider gagal diverifikasi.');
    } finally {
      setLoading(false);
    }
  }

  async function handleDigiflazzSync() {
    setLoading(true);
    setError(null);
    setMessage(null);
    try {
      const result = await syncDigiflazzCatalog(session.token, session.businessId, 'prepaid');
      const [productResponse, providerResponse] = await Promise.all([
        getProducts(session.token, session.businessId),
        getProviders(session.token, session.businessId),
      ]);
      setProducts(productResponse.data);
      setProviders(providerResponse.data);
      setMessage(`Katalog Digiflazz tersinkron: ${result.data.created} baru, ${result.data.updated} diperbarui.`);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Katalog Digiflazz gagal disinkronkan.');
    } finally {
      setLoading(false);
    }
  }

  const contactForm = (type: 'customer' | 'supplier') => {
    const isCustomer = type === 'customer';
    const form = isCustomer ? customerForm : supplierForm;
    const setForm = isCustomer ? setCustomerForm : setSupplierForm;
    return (
      <form className={styles.card} onSubmit={(event) => void handleContactSubmit(event, type)}>
        <div className={styles.cardHeading}><div><p className={styles.eyebrow}>{isCustomer ? 'CRM' : 'PROCUREMENT'}</p><h2>{isCustomer ? 'Tambah pelanggan' : 'Tambah supplier'}</h2></div></div>
        <div className={styles.form}>
          <label>Nama<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required /></label>
          <label>Telepon<input value={form.phone ?? ''} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label>
          <label>Email<input type="email" value={form.email ?? ''} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
          <label>Alamat<input value={form.address ?? ''} onChange={(event) => setForm({ ...form, address: event.target.value })} /></label>
          <button className={styles.primaryButton} disabled={loading} type="submit">{loading ? 'Menyimpan...' : 'Simpan'}</button>
        </div>
      </form>
    );
  };

  return (
    <section className={styles.card}>
      <div className={styles.cardHeading}>
        <div><p className={styles.eyebrow}>OPERASIONAL</p><h2>Modul bisnis</h2></div>
        {loading && <span className={styles.muted}>Memuat...</span>}
      </div>
      <nav className={styles.tabs} aria-label="Modul bisnis">
        {([['stock', 'Stok'], ['customers', 'Pelanggan'], ['suppliers', 'Supplier'], ['purchases', 'Pembelian'], ['projects', 'Proyek'], ['providers', 'Provider'], ['finance', 'Keuangan'], ['reports', 'Laporan'], ['roles', 'Role & akses']] as Array<[ModuleTab, string]>).map(([key, label]) => (
          <button className={activeModule === key ? styles.tabActive : styles.tab} key={key} onClick={() => setActiveModule(key)} type="button">{label}</button>
        ))}
      </nav>
      {error && <div className={styles.alertError}>{error}</div>}
      {message && <div className={styles.alertSuccess}>{message}</div>}

      {activeModule === 'stock' && (
        <div className={styles.workspaceGrid}>
          <div>
            <div className={styles.statsGrid}>
              <div className={styles.statCard}><span>Item stok</span><strong>{stock.length}</strong></div>
              <div className={styles.statCard}><span>Stok menipis</span><strong>{stock.filter((item) => Number(item.quantity) <= Number(item.minimum_quantity)).length}</strong></div>
              <div className={styles.statCard}><span>Produk katalog</span><strong>{products.length}</strong></div>
            </div>
            {stock.length === 0 ? <div className={styles.emptyState}>Belum ada saldo stok. Catat stok masuk di samping.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Produk</th><th>Outlet</th><th>Saldo</th><th>Minimum</th><th>Status</th></tr></thead><tbody>{stock.map((item) => { const low = Number(item.quantity) <= Number(item.minimum_quantity); return <tr key={item.id}><td><strong>{item.product.name}</strong><small>{item.product.sku || 'Tanpa SKU'}</small></td><td>{item.outlet.name}</td><td>{item.quantity}</td><td>{item.minimum_quantity}</td><td><span className={low ? styles.badgeMuted : styles.badge}>{low ? 'Menipis' : 'Aman'}</span></td></tr>; })}</tbody></table></div>}
          </div>
          <form className={styles.card} onSubmit={handleStockSubmit}>
            <div className={styles.cardHeading}><div><p className={styles.eyebrow}>INVENTORY</p><h2>Penyesuaian stok</h2></div></div>
            <div className={styles.form}>
              <label>Produk<select value={stockForm.productId} onChange={(event) => setStockForm({ ...stockForm, productId: event.target.value })} required>{products.filter((product) => product.is_stockable).map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}</select></label>
              <div className={styles.twoColumns}><label>Gerakan<select value={stockForm.movementType} onChange={(event) => setStockForm({ ...stockForm, movementType: event.target.value as 'in' | 'out' | 'adjustment' })}><option value="in">Stok masuk</option><option value="out">Stok keluar</option><option value="adjustment">Koreksi (+/-)</option></select></label><label>Jumlah<input type="number" min="0.001" step="0.001" value={stockForm.quantity} onChange={(event) => setStockForm({ ...stockForm, quantity: event.target.value })} required /></label></div>
              <label>Minimum stok<input type="number" min="0" step="0.001" value={stockForm.minimumQuantity} onChange={(event) => setStockForm({ ...stockForm, minimumQuantity: event.target.value })} /></label>
              <label>Catatan<input value={stockForm.note} onChange={(event) => setStockForm({ ...stockForm, note: event.target.value })} /></label>
              <button className={styles.primaryButton} disabled={loading || products.filter((product) => product.is_stockable).length === 0} type="submit">Simpan perubahan stok</button>
            </div>
          </form>
        </div>
      )}

      {activeModule === 'customers' && <div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>CRM</p><h2>Daftar pelanggan</h2></div></div>{customers.length === 0 ? <div className={styles.emptyState}>Belum ada pelanggan.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Nama</th><th>Kontak</th><th>Status</th></tr></thead><tbody>{customers.map((customer) => <tr key={customer.id}><td><strong>{customer.name}</strong><small>{customer.address || 'Alamat belum diisi'}</small></td><td>{customer.phone || customer.email || '-'}</td><td><span className={styles.badge}>{customer.status}</span></td></tr>)}</tbody></table></div>}</div>{contactForm('customer')}</div>}
      {activeModule === 'suppliers' && <div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PROCUREMENT</p><h2>Daftar supplier</h2></div></div>{suppliers.length === 0 ? <div className={styles.emptyState}>Belum ada supplier.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Nama</th><th>Kontak</th><th>Status</th></tr></thead><tbody>{suppliers.map((supplier) => <tr key={supplier.id}><td><strong>{supplier.name}</strong><small>{supplier.address || 'Alamat belum diisi'}</small></td><td>{supplier.phone || supplier.email || '-'}</td><td><span className={styles.badge}>{supplier.status}</span></td></tr>)}</tbody></table></div>}</div>{contactForm('supplier')}</div>}
      {activeModule === 'purchases' && <div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>RIWAYAT PEMBELIAN</p><h2>Pembelian terbaru</h2></div><span className={styles.muted}>{purchases.length} pembelian</span></div>{purchases.length === 0 ? <div className={styles.emptyState}>Belum ada pembelian.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Nomor</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead><tbody>{purchases.map((purchase) => <tr key={purchase.id}><td><strong>{purchase.purchase_number}</strong><small>{new Date(purchase.purchase_date).toLocaleString('id-ID')}</small></td><td>{purchase.supplier?.name || 'Tanpa supplier'}</td><td>{money(purchase.total_amount)}</td><td><span className={purchase.payment_status === 'paid' ? styles.badge : styles.badgeMuted}>{purchase.payment_status}</span></td></tr>)}</tbody></table></div>}</div><form className={styles.card} onSubmit={(event) => void handlePurchaseSubmit(event)}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PROCUREMENT</p><h2>Catat pembelian</h2></div></div><div className={styles.form}><label>Supplier <span className={styles.optional}>opsional</span><select value={purchaseForm.supplierId} onChange={(event) => setPurchaseForm({ ...purchaseForm, supplierId: event.target.value })}><option value="">Tanpa supplier</option>{suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}</select></label><label>Produk<select value={purchaseForm.productId} onChange={(event) => setPurchaseForm({ ...purchaseForm, productId: event.target.value })} required>{products.filter((product) => product.is_stockable).map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}</select></label><div className={styles.twoColumns}><label>Jumlah<input type="number" min="0.001" step="0.001" value={purchaseForm.quantity} onChange={(event) => setPurchaseForm({ ...purchaseForm, quantity: event.target.value })} required /></label><label>Harga modal<input type="number" min="0" step="0.01" value={purchaseForm.unitCost} onChange={(event) => setPurchaseForm({ ...purchaseForm, unitCost: event.target.value })} required /></label></div><button className={styles.primaryButton} disabled={loading || products.filter((product) => product.is_stockable).length === 0} type="submit">{loading ? 'Menyimpan...' : 'Simpan & tambah stok'}</button></div></form></div>}
      {activeModule === 'projects' && <div className={styles.workspaceGrid}>
        <div className={styles.card}>
          <div className={styles.cardHeading}><div><p className={styles.eyebrow}>LAYANAN & PEKERJAAN</p><h2>Proyek berjalan</h2></div><span className={styles.muted}>{projects.length} proyek</span></div>
          {projects.length === 0 ? <div className={styles.emptyState}>Belum ada proyek jasa.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Proyek</th><th>Klien</th><th>Nilai</th><th>Status</th></tr></thead><tbody>{projects.map((project) => <tr key={project.id}><td><strong>{project.name}</strong><small>{project.project_number} · {project.project_type}</small></td><td>{project.customer?.name || 'Umum'}</td><td>{money(project.quoted_amount)}</td><td><span className={project.status === 'completed' ? styles.badge : styles.badgeMuted}>{project.status}</span></td></tr>)}</tbody></table></div>}
        </div>
        <form className={styles.card} onSubmit={(event) => void handleProjectSubmit(event)}>
          <div className={styles.cardHeading}><div><p className={styles.eyebrow}>PROJECT MANAGEMENT</p><h2>Tambah proyek</h2></div></div>
          <div className={styles.form}>
            <label>Nama proyek<input value={projectForm.name} onChange={(event) => setProjectForm({ ...projectForm, name: event.target.value })} placeholder="Website company profile" required /></label>
            <label>Klien<select value={projectForm.customerId} onChange={(event) => setProjectForm({ ...projectForm, customerId: event.target.value })}><option value="">Klien umum</option>{customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}</select></label>
            <div className={styles.twoColumns}><label>Jenis<select value={projectForm.projectType} onChange={(event) => setProjectForm({ ...projectForm, projectType: event.target.value })}><option value="website">Website</option><option value="android">Aplikasi Android</option><option value="design">Desain</option><option value="print">Print</option><option value="scan">Scan</option><option value="typing">Ketik</option><option value="other">Lainnya</option></select></label><label>Nilai proyek<input type="number" min="0" value={projectForm.quotedAmount} onChange={(event) => setProjectForm({ ...projectForm, quotedAmount: event.target.value })} required /></label></div>
            <div className={styles.twoColumns}><label>Deadline<input type="date" value={projectForm.dueDate} onChange={(event) => setProjectForm({ ...projectForm, dueDate: event.target.value })} /></label><label>Deskripsi<input value={projectForm.description} onChange={(event) => setProjectForm({ ...projectForm, description: event.target.value })} /></label></div>
            <button className={styles.primaryButton} disabled={loading} type="submit">{loading ? 'Menyimpan...' : 'Simpan proyek'}</button>
          </div>
        </form>
      </div>}
      {activeModule === 'providers' && <div className={styles.workspaceGrid}>
        <div className={styles.card}>
          <div className={styles.cardHeading}><div><p className={styles.eyebrow}>SALDO DIGITAL</p><h2>Provider</h2></div><span className={styles.muted}>{providers.length} provider</span></div>
          <div className={styles.alertSuccess}>{integrationStatus?.digiflazz.configured ? 'Digiflazz production terkonfigurasi.' : 'Digiflazz belum terkonfigurasi.'} {integrationStatus?.midtrans.production ? 'Midtrans production aktif.' : 'Midtrans sandbox/nonaktif.'}</div>
          <div className={styles.receiptLine}><span>Saldo Digiflazz live</span><strong>{digiflazzBalance === null ? 'Belum dicek' : money(digiflazzBalance)}</strong></div>
          <div className={styles.receiptLine}><span>Status Midtrans</span><strong>{midtransConnection ? `Terhubung (${midtransConnection})` : 'Belum dicek'}</strong></div>
          <button className={styles.secondaryButton} disabled={loading} onClick={() => void checkLiveIntegrations()} type="button">{loading ? 'Memeriksa...' : 'Cek koneksi production'}</button>
          <button className={styles.secondaryButton} disabled={loading || !integrationStatus?.digiflazz.configured} onClick={() => void handleDigiflazzSync()} type="button">Sinkronkan katalog prepaid</button>
          {providers.length === 0 ? <div className={styles.emptyState}>Belum ada provider. Tambahkan provider pulsa atau layanan tagihan.</div> : <div className={styles.tableWrap}><table><thead><tr><th>Provider</th><th>Jenis</th><th>Saldo</th><th>Produk</th></tr></thead><tbody>{providers.map((provider) => <tr key={provider.id}><td><strong>{provider.name}</strong><small>{provider.account_identifier || 'Identitas akun belum diisi'}</small></td><td>{provider.provider_type}</td><td>{money(provider.balance)}</td><td>{provider.products_count || 0}</td></tr>)}</tbody></table></div>}
        </div>
        <form className={styles.card} onSubmit={(event) => void handleProviderSubmit(event)}>
          <div className={styles.cardHeading}><div><p className={styles.eyebrow}>MASTER PROVIDER</p><h2>Tambah provider</h2></div></div>
          <div className={styles.form}><label>Nama provider<input value={providerForm.name} onChange={(event) => setProviderForm({ ...providerForm, name: event.target.value })} placeholder="DigiFlazz / PLN / ISP" required /></label><label>Jenis<select value={providerForm.providerType} onChange={(event) => setProviderForm({ ...providerForm, providerType: event.target.value })}><option value="digital">Pulsa / digital</option><option value="utility">Tagihan utilitas</option><option value="printing">Print & ATK</option><option value="other">Lainnya</option></select></label><label>Identitas akun<input value={providerForm.accountIdentifier} onChange={(event) => setProviderForm({ ...providerForm, accountIdentifier: event.target.value })} placeholder="Username / nomor akun" /></label><label>Saldo awal<input type="number" min="0" value={providerForm.openingBalance} onChange={(event) => setProviderForm({ ...providerForm, openingBalance: event.target.value })} /></label><label>Catatan<input value={providerForm.notes} onChange={(event) => setProviderForm({ ...providerForm, notes: event.target.value })} /></label><button className={styles.primaryButton} disabled={loading} type="submit">Simpan provider</button></div>
        </form>
      </div>}
      {activeModule === 'finance' && <div>
        <div className={styles.statsGrid}><div className={styles.statCard}><span>Omzet penjualan</span><strong>{money(financeSummary?.summary.sales || 0)}</strong></div><div className={styles.statCard}><span>Laba kotor</span><strong>{money(financeSummary?.summary.gross_profit || 0)}</strong></div><div className={styles.statCard}><span>Pengeluaran</span><strong>{money(financeSummary?.summary.expenses || 0)}</strong></div><div className={styles.statCard}><span>Laba bersih</span><strong>{money(financeSummary?.summary.net_profit || 0)}</strong></div></div>
        <div className={styles.workspaceGrid}>
          <form className={styles.card} onSubmit={(event) => void handleIncomeSubmit(event)}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>UANG MASUK</p><h2>Catat pemasukan</h2></div></div><div className={styles.form}><label>Proyek<select value={incomeForm.projectId} onChange={(event) => setIncomeForm({ ...incomeForm, projectId: event.target.value })}><option value="">Di luar proyek</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label><label>Akun kas<select value={incomeForm.cashAccountId} onChange={(event) => setIncomeForm({ ...incomeForm, cashAccountId: event.target.value })}><option value="">Tidak dicatat ke kas</option>{cashAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {money(account.balance)}</option>)}</select></label><label>Kategori<input value={incomeForm.category} onChange={(event) => setIncomeForm({ ...incomeForm, category: event.target.value })} /></label><label>Nominal<input type="number" min="0.01" value={incomeForm.amount} onChange={(event) => setIncomeForm({ ...incomeForm, amount: event.target.value })} required /></label><label>Keterangan<input value={incomeForm.description} onChange={(event) => setIncomeForm({ ...incomeForm, description: event.target.value })} required /></label><button className={styles.primaryButton} disabled={loading} type="submit">Simpan pemasukan</button></div></form>
          <form className={styles.card} onSubmit={(event) => void handleExpenseSubmit(event)}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>UANG KELUAR</p><h2>Catat pengeluaran</h2></div></div><div className={styles.form}><label>Proyek<select value={expenseForm.projectId} onChange={(event) => setExpenseForm({ ...expenseForm, projectId: event.target.value })}><option value="">Biaya operasional umum</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label><label>Provider terkait<select value={expenseForm.providerId} onChange={(event) => setExpenseForm({ ...expenseForm, providerId: event.target.value })}><option value="">Tidak terkait provider</option>{providers.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}</select></label><label>Akun kas<select value={expenseForm.cashAccountId} onChange={(event) => setExpenseForm({ ...expenseForm, cashAccountId: event.target.value })}><option value="">Tidak dicatat ke kas</option>{cashAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {money(account.balance)}</option>)}</select></label><label>Kategori<input value={expenseForm.category} onChange={(event) => setExpenseForm({ ...expenseForm, category: event.target.value })} /></label><label>Nominal<input type="number" min="0.01" value={expenseForm.amount} onChange={(event) => setExpenseForm({ ...expenseForm, amount: event.target.value })} required /></label><label>Keterangan<input value={expenseForm.description} onChange={(event) => setExpenseForm({ ...expenseForm, description: event.target.value })} required /></label><button className={styles.primaryButton} disabled={loading} type="submit">Simpan pengeluaran</button></div></form>
        </div>
        <div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PEMASUKAN TERBARU</p><h2>Riwayat pemasukan</h2></div></div>{incomes.length === 0 ? <div className={styles.emptyState}>Belum ada pemasukan manual.</div> : incomes.slice(0, 8).map((entry) => <div className={styles.receiptLine} key={entry.id}><span>{entry.description}<small>{entry.project?.name || entry.category}</small></span><strong>{money(entry.amount)}</strong></div>)}</div><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PENGELUARAN TERBARU</p><h2>Riwayat pengeluaran</h2></div></div>{expenses.length === 0 ? <div className={styles.emptyState}>Belum ada pengeluaran.</div> : expenses.slice(0, 8).map((entry) => <div className={styles.receiptLine} key={entry.id}><span>{entry.description}<small>{entry.project?.name || entry.provider?.name || entry.category}</small></span><strong>{money(entry.amount)}</strong></div>)}</div></div>
      </div>}
      {activeModule === 'reports' && <div><div className={styles.statsGrid}><div className={styles.statCard}><span>Penjualan periode</span><strong>{money(report?.summary.sales || 0)}</strong></div><div className={styles.statCard}><span>Laba kotor</span><strong>{money(report?.summary.profit || 0)}</strong></div><div className={styles.statCard}><span>Transaksi</span><strong>{report?.summary.transaction_count || 0}</strong></div></div><div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PERFORMA</p><h2>Produk terlaris</h2></div><span className={styles.muted}>{report?.period.from} — {report?.period.to}</span></div>{report?.top_products.length ? <div className={styles.tableWrap}><table><thead><tr><th>Produk</th><th>Jumlah</th><th>Penjualan</th></tr></thead><tbody>{report.top_products.map((product) => <tr key={product.id}><td>{product.name}</td><td>{product.quantity}</td><td>{money(product.sales)}</td></tr>)}</tbody></table></div> : <div className={styles.emptyState}>Belum ada transaksi pada periode ini.</div>}</div><div className={styles.card}><p className={styles.eyebrow}>RINGKASAN MASTER DATA</p><h2>Kesehatan operasional</h2><div className={styles.receiptLine}><span>Pelanggan aktif</span><strong>{report?.counts.customers || 0}</strong></div><div className={styles.receiptLine}><span>Supplier aktif</span><strong>{report?.counts.suppliers || 0}</strong></div><div className={styles.receiptLine}><span>Stok menipis</span><strong>{report?.counts.low_stock || 0}</strong></div><p className={styles.muted}>Laporan mengambil data langsung dari transaksi, master data, dan saldo stok server.</p></div></div></div>}
      {activeModule === 'roles' && <div className={styles.workspaceGrid}><div className={styles.card}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>ACCESS CONTROL</p><h2>Role yang tersedia</h2></div></div>{roles.length === 0 ? <div className={styles.emptyState}>Role belum tersedia untuk user ini.</div> : roles.map((role) => <div className={styles.receiptLine} key={role.id}><div><strong>{role.name}</strong><small>{role.description || role.slug}</small></div><span className={styles.badge}>{role.users_count} user · {role.permissions.length} izin</span></div>)}</div><form className={styles.card} onSubmit={handleRoleSubmit}><div className={styles.cardHeading}><div><p className={styles.eyebrow}>PERMISSIONS</p><h2>Tambah role</h2></div></div><div className={styles.form}><label>Nama role<input value={roleForm.name} onChange={(event) => setRoleForm({ ...roleForm, name: event.target.value })} required /></label><label>Slug<input value={roleForm.slug} onChange={(event) => setRoleForm({ ...roleForm, slug: event.target.value })} placeholder="kasir" required /></label><label>Deskripsi<input value={roleForm.description} onChange={(event) => setRoleForm({ ...roleForm, description: event.target.value })} /></label><div className={styles.permissionList}>{permissions.map((permission) => <label className={styles.checkbox} key={permission.id}><input type="checkbox" checked={roleForm.permissionIds.includes(permission.id)} onChange={(event) => setRoleForm({ ...roleForm, permissionIds: event.target.checked ? [...roleForm.permissionIds, permission.id] : roleForm.permissionIds.filter((id) => id !== permission.id) })} />{permission.name}</label>)}</div><button className={styles.primaryButton} disabled={loading} type="submit">Simpan role</button></div></form></div>}
    </section>
  );
}
