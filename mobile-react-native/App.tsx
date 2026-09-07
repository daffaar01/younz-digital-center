import { useCallback, useEffect, useMemo, useState } from 'react';
import { Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';

import {
  api,
  apiData,
  apiErrorMessage,
  asArray,
  createIdempotencyKey,
  displayValue,
  formatRupiah,
  valueOf,
  type ApiSession,
  type ApiUser,
} from './src/api';
import { clearSession, loadSession, saveSession, type SessionKind, type StoredSession } from './src/session';

type CustomerTab = 'home' | 'catalog' | 'orders' | 'account';
type StaffTab = 'dashboard' | 'digital' | 'orders' | 'reports' | 'account';
type AuthMode = 'customer' | 'staff';

type ScreenProps = {
  children: React.ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
};

const colors = {
  ink: '#17132f',
  muted: '#726d89',
  primary: '#7257ff',
  primaryDark: '#4d35c7',
  background: '#f7f5ff',
  card: '#ffffff',
  line: '#e8e3f7',
  success: '#14866d',
  warning: '#b56c12',
  danger: '#bd3e5c',
  dark: '#100c2b',
};

const roleLabel = (user: ApiUser) => {
  if (typeof user.role === 'string') return user.role;
  return user.role?.label || user.role?.code || 'Staff';
};

function App() {
  const [session, setSession] = useState<StoredSession | null>(null);
  const [hydrating, setHydrating] = useState(true);

  useEffect(() => {
    loadSession()
      .then(setSession)
      .finally(() => setHydrating(false));
  }, []);

  const onLogin = useCallback(async (result: ApiSession, kind: SessionKind) => {
    const next: StoredSession = { ...result, kind };
    await saveSession(next);
    setSession(next);
  }, []);

  const onLogout = useCallback(async () => {
    if (session?.token) await api.logout(session.token).catch(() => undefined);
    await clearSession();
    setSession(null);
  }, [session?.token]);

  if (hydrating) return <LoadingScreen label="Menyiapkan Younz..." />;
  if (!session) return <AuthScreen onLogin={onLogin} />;
  if (session.kind === 'staff') return <StaffApp session={session} onLogout={onLogout} />;
  return <CustomerApp session={session} onLogout={onLogout} />;
}

function AuthScreen({ onLogin }: { onLogin: (session: ApiSession, kind: SessionKind) => Promise<void> }) {
  const [mode, setMode] = useState<AuthMode>('customer');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [accessCode, setAccessCode] = useState('');
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit() {
    setError('');
    setBusy(true);
    try {
      const session = mode === 'customer'
        ? await api.customerLogin(email.trim(), password)
        : await api.staffLogin(email.trim(), password, accessCode.trim(), twoFactorCode.trim());
      await onLogin(session, mode);
    } catch (caught) {
      setError(apiErrorMessage(caught));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.authRoot}>
      <StatusBar style="light" />
      <ScrollView contentContainerStyle={styles.authContent} keyboardShouldPersistTaps="handled">
        <View style={styles.brandMark}><Text style={styles.brandMarkText}>Y</Text></View>
        <Text style={styles.authKicker}>YOUNZ DIGITAL CENTER</Text>
        <Text style={styles.authTitle}>Semua layanan digital dalam satu aplikasi.</Text>
        <Text style={styles.authSubtitle}>Masuk untuk mengelola pesanan, top up, dan operasional toko.</Text>

        <View style={styles.segmented}>
          <SegmentButton active={mode === 'customer'} label="Customer" onPress={() => { setMode('customer'); setError(''); }} />
          <SegmentButton active={mode === 'staff'} label="Admin / Staff" onPress={() => { setMode('staff'); setError(''); }} />
        </View>

        <View style={styles.authCard}>
          <Field label="Email" value={email} onChangeText={setEmail} placeholder="nama@email.com" keyboardType="email-address" autoCapitalize="none" />
          <Field label="Password" value={password} onChangeText={setPassword} placeholder="Masukkan password" secureTextEntry />
          {mode === 'staff' && (
            <>
              <Field label="Kode akses staf" value={accessCode} onChangeText={setAccessCode} placeholder="Kode internal" secureTextEntry keyboardType="number-pad" />
              <Field label="Kode authenticator (6 digit)" value={twoFactorCode} onChangeText={setTwoFactorCode} placeholder="123456" keyboardType="number-pad" maxLength={6} />
            </>
          )}
          {error ? <Text style={styles.errorText}>{error}</Text> : null}
          <PrimaryButton label={busy ? 'Memproses...' : 'Masuk'} onPress={submit} disabled={busy || !email.trim() || !password} />
          <Text style={styles.helperText}>
            {mode === 'staff' ? 'Akses staf divalidasi server-side dengan token Sanctum, kode internal, dan 2FA.' : 'Akun customer harus sudah memverifikasi email sebelum masuk.'}
          </Text>
        </View>
      </ScrollView>
    </View>
  );
}

function CustomerApp({ session, onLogout }: { session: StoredSession; onLogout: () => Promise<void> }) {
  const [tab, setTab] = useState<CustomerTab>('home');
  const [site, setSite] = useState<Record<string, unknown> | null>(null);
  const [portal, setPortal] = useState<Record<string, unknown> | null>(null);
  const [catalog, setCatalog] = useState<Record<string, unknown> | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setRefreshing(true);
    const [siteResult, portalResult, catalogResult] = await Promise.allSettled([
      api.site(),
      api.customerPortal(session.token),
      api.topupCatalog(),
    ]);
    if (siteResult.status === 'fulfilled') setSite(apiData(siteResult.value));
    if (portalResult.status === 'fulfilled') setPortal(apiData(portalResult.value));
    if (catalogResult.status === 'fulfilled') setCatalog(apiData(catalogResult.value));
    setRefreshing(false);
  }, [session.token]);

  useEffect(() => { void load(); }, [load]);

  return (
    <View style={styles.appRoot}>
      <StatusBar style="dark" />
      {tab === 'home' && <CustomerHome user={session.user} site={site} portal={portal} onOpenCatalog={() => setTab('catalog')} />}
      {tab === 'catalog' && <CustomerCatalog catalog={catalog} user={session.user} refreshing={refreshing} onRefresh={load} />}
      {tab === 'orders' && <CustomerOrders portal={portal} refreshing={refreshing} onRefresh={load} />}
      {tab === 'account' && <AccountScreen user={session.user} kind="customer" onLogout={onLogout} />}
      <BottomTabs items={[['home', 'Beranda'], ['catalog', 'Top Up'], ['orders', 'Pesanan'], ['account', 'Akun']]} active={tab} onChange={setTab} />
    </View>
  );
}

function CustomerHome({ user, site, portal, onOpenCatalog }: { user: ApiUser; site: Record<string, unknown> | null; portal: Record<string, unknown> | null; onOpenCatalog: () => void }) {
  const summary = (portal?.summary || {}) as Record<string, unknown>;
  const featured = asArray(site?.featured_products);
  const services = asArray(site?.services);

  return (
    <Screen>
      <Header eyebrow="YOUNZ DIGITAL CENTER" title={`Halo, ${user.name.split(' ')[0]} 👋`} subtitle="Kelola kebutuhan digital Anda dengan lebih praktis." />
      <View style={styles.heroCard}>
        <View style={styles.heroOrb} />
        <Text style={styles.heroEyebrow}>LAYANAN DIGITAL</Text>
        <Text style={styles.heroTitle}>Isi ulang, pesan layanan, selesai.</Text>
        <Text style={styles.heroCopy}>Pilih produk yang Anda butuhkan lalu lanjutkan pembayaran dengan aman.</Text>
        <PrimaryButton label="Buka katalog top up" onPress={onOpenCatalog} compact />
      </View>

      <SectionTitle title="Ringkasan pesanan" />
      <View style={styles.metricRow}>
        <Metric label="Total" value={displayValue(summary.total, '0')} />
        <Metric label="Aktif" value={displayValue(summary.active, '0')} tone="purple" />
        <Metric label="Selesai" value={displayValue(summary.completed, '0')} tone="green" />
      </View>

      <SectionTitle title="Produk pilihan" />
      {featured.slice(0, 4).map((item, index) => <ProductRow key={String(valueOf(item, 'id') || index)} item={item} />)}
      {featured.length === 0 && <EmptyState label="Produk pilihan belum tersedia." />}

      <SectionTitle title="Layanan kami" />
      {services.slice(0, 3).map((item, index) => (
        <Card key={String(valueOf(item, 'id') || index)} style={styles.serviceRow}>
          <View style={styles.iconBubble}><Text style={styles.iconBubbleText}>✦</Text></View>
          <View style={styles.flexOne}><Text style={styles.cardTitle}>{displayValue(item, 'Layanan')}</Text><Text style={styles.muted}>{displayValue(valueOf(item, 'description'))}</Text></View>
        </Card>
      ))}
    </Screen>
  );
}

function CustomerCatalog({ catalog, user, refreshing, onRefresh }: { catalog: Record<string, unknown> | null; user: ApiUser; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<Record<string, unknown> | null>(null);
  const products = asArray(catalog?.products);
  const filtered = useMemo(() => {
    const keyword = search.trim().toLowerCase();
    if (!keyword) return products;
    return products.filter((product) => `${valueOf(product, 'product_name')} ${valueOf(product, 'brand')} ${valueOf(product, 'category')}`.toLowerCase().includes(keyword));
  }, [products, search]);

  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow="CUSTOMER" title="Katalog top up" subtitle="Pilih produk dan isi nomor tujuan Anda." />
      <Field label="Cari produk" value={search} onChangeText={setSearch} placeholder="Telkomsel, PLN, game..." />
      {catalog && catalog.integrations_ready === false ? <Notice tone="warning" text="Integrasi pembayaran/provider belum siap di server." /> : null}
      {selected ? <TopupCheckout product={selected} user={user} onClose={() => setSelected(null)} /> : null}
      {!selected && filtered.slice(0, 80).map((item, index) => (
        <Pressable key={String(valueOf(item, 'id') || index)} onPress={() => setSelected(item)}>
          <ProductRow item={item} selectable />
        </Pressable>
      ))}
      {!selected && filtered.length === 0 && <EmptyState label="Produk tidak ditemukan atau katalog belum dimuat." />}
    </Screen>
  );
}

function TopupCheckout({ product, user, onClose }: { product: Record<string, unknown>; user: ApiUser; onClose: () => void }) {
  const [destination, setDestination] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [phone, setPhone] = useState(user.phone || '');
  const [terms, setTerms] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');

  async function checkout() {
    setMessage('');
    if (!destination.trim() || destination.trim() !== confirmation.trim()) return setMessage('Nomor tujuan dan konfirmasinya harus sama.');
    if (!name.trim() || !email.trim() || !phone.trim()) return setMessage('Lengkapi nama, email, dan nomor WhatsApp.');
    if (!terms) return setMessage('Setujui syarat layanan terlebih dahulu.');
    setBusy(true);
    try {
      const response = await api.topupCheckout({
        idempotency_key: createIdempotencyKey(),
        product_id: Number(valueOf(product, 'id')),
        destination: destination.trim(),
        destination_confirmation: confirmation.trim(),
        customer_name: name.trim(),
        customer_email: email.trim(),
        customer_phone: phone.trim(),
        terms: true,
      });
      const data = apiData(response) || {};
      setMessage(`Pesanan ${displayValue(valueOf(data, 'order_number'), 'berhasil dibuat')} berhasil dibuat.`);
      if (response.redirect_url) await Linking.openURL(response.redirect_url).catch(() => undefined);
    } catch (caught) {
      setMessage(apiErrorMessage(caught));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card style={styles.checkoutCard}>
      <View style={styles.rowBetween}><View><Text style={styles.cardTitle}>{displayValue(valueOf(product, 'product_name'))}</Text><Text style={styles.muted}>{formatRupiah(valueOf(product, 'selling_price'))}</Text></View><Pressable onPress={onClose}><Text style={styles.link}>Tutup</Text></Pressable></View>
      <Field label="Nomor / ID tujuan" value={destination} onChangeText={setDestination} placeholder="08xxxxxxxxxx" />
      <Field label="Konfirmasi tujuan" value={confirmation} onChangeText={setConfirmation} placeholder="Ulangi nomor / ID" />
      <Field label="Nama" value={name} onChangeText={setName} placeholder="Nama lengkap" />
      <Field label="Email" value={email} onChangeText={setEmail} placeholder="nama@email.com" keyboardType="email-address" autoCapitalize="none" />
      <Field label="Nomor WhatsApp" value={phone} onChangeText={setPhone} placeholder="08xxxxxxxxxx" keyboardType="phone-pad" />
      <Pressable style={styles.checkRow} onPress={() => setTerms((value) => !value)}><View style={[styles.checkbox, terms && styles.checkboxActive]}>{terms ? <Text style={styles.checkboxTick}>✓</Text> : null}</View><Text style={styles.muted}>Saya menyetujui syarat layanan dan pengecekan tujuan.</Text></Pressable>
      {message ? <Text style={styles.infoText}>{message}</Text> : null}
      <PrimaryButton label={busy ? 'Membuat pesanan...' : 'Lanjutkan pembayaran'} onPress={() => void checkout()} disabled={busy} />
    </Card>
  );
}

function CustomerOrders({ portal, refreshing, onRefresh }: { portal: Record<string, unknown> | null; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const orders = asArray(portal?.orders);
  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow="CUSTOMER" title="Pesanan saya" subtitle="Pantau status pesanan layanan Anda." />
      {orders.map((order, index) => <OrderRow key={String(valueOf(order, 'id') || index)} item={order} />)}
      {orders.length === 0 && <EmptyState label="Belum ada pesanan layanan." />}
    </Screen>
  );
}

function StaffApp({ session, onLogout }: { session: StoredSession; onLogout: () => Promise<void> }) {
  const [tab, setTab] = useState<StaffTab>('dashboard');
  const [dashboard, setDashboard] = useState<Record<string, unknown> | null>(null);
  const [digital, setDigital] = useState<Record<string, unknown> | null>(null);
  const [orders, setOrders] = useState<Record<string, unknown> | null>(null);
  const [report, setReport] = useState<Record<string, unknown> | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const loadTab = useCallback(async (target: StaffTab = tab) => {
    if (target === 'account') return;
    setRefreshing(true);
    try {
      if (target === 'dashboard') setDashboard(apiData(await api.staffDashboard(session.token)));
      if (target === 'digital') setDigital(apiData(await api.staffDigitalTransactions(session.token)));
      if (target === 'orders') setOrders(apiData(await api.staffOrders(session.token)));
      if (target === 'reports') setReport(apiData(await api.staffDailyReport(session.token)));
    } catch {
      // Individual screens show their last successful state; the next refresh retries.
    } finally {
      setRefreshing(false);
    }
  }, [session.token, tab]);

  useEffect(() => { void loadTab(tab); }, [tab, loadTab]);

  return (
    <View style={styles.appRoot}>
      <StatusBar style="dark" />
      {tab === 'dashboard' && <StaffDashboard user={session.user} data={dashboard} refreshing={refreshing} onRefresh={() => loadTab('dashboard')} />}
      {tab === 'digital' && <StaffDigital data={digital} refreshing={refreshing} onRefresh={() => loadTab('digital')} />}
      {tab === 'orders' && <StaffOrders data={orders} refreshing={refreshing} onRefresh={() => loadTab('orders')} />}
      {tab === 'reports' && <StaffReport data={report} refreshing={refreshing} onRefresh={() => loadTab('reports')} />}
      {tab === 'account' && <AccountScreen user={session.user} kind="staff" onLogout={onLogout} />}
      <BottomTabs items={[['dashboard', 'Dashboard'], ['digital', 'Digital'], ['orders', 'Pesanan'], ['reports', 'Laporan'], ['account', 'Akun']]} active={tab} onChange={setTab} />
    </View>
  );
}

function StaffDashboard({ user, data, refreshing, onRefresh }: { user: ApiUser; data: Record<string, unknown> | null; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const summary = (data?.summary || data || {}) as Record<string, unknown>;
  const metrics = [
    ['Pendapatan', valueOf(summary, 'gross_revenue', 'revenue', 'today_revenue'), true],
    ['Pesanan selesai', valueOf(summary, 'completed_orders', 'orders_today', 'completed'), false],
    ['Transaksi digital', valueOf(summary, 'digital_transactions', 'digital_count'), false],
    ['Laba kotor', valueOf(summary, 'gross_profit', 'profit'), true],
  ];
  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow={roleLabel(user).toUpperCase()} title="Dashboard operasional" subtitle="Ringkasan data terbaru dari server Younz." />
      <View style={styles.metricGrid}>{metrics.map(([label, value, money]) => <Metric key={String(label)} label={String(label)} value={money ? formatRupiah(value) : displayValue(value, '0')} />)}</View>
      <Card><Text style={styles.cardTitle}>Akses aman aktif</Text><Text style={styles.muted}>Role dan kemampuan akun divalidasi oleh Laravel API. Token PPOB tidak pernah berada di aplikasi ini.</Text></Card>
    </Screen>
  );
}

function StaffDigital({ data, refreshing, onRefresh }: { data: Record<string, unknown> | null; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const items = [...asArray(data?.transactions), ...asArray(data?.topup_orders), ...asArray(data?.topups)];
  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow="STAFF" title="Transaksi digital" subtitle="Top up, transaksi manual, dan status fulfillment." />
      {items.map((item, index) => <TransactionRow key={String(valueOf(item, 'id', 'order_number', 'transaction_number') || index)} item={item} />)}
      {items.length === 0 && <EmptyState label="Belum ada transaksi digital." />}
    </Screen>
  );
}

function StaffOrders({ data, refreshing, onRefresh }: { data: Record<string, unknown> | null; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const items = asArray(data?.orders || data?.data);
  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow="STAFF" title="Pesanan layanan" subtitle="Kelola antrean pesanan dengan scope server-side." />
      {items.map((item, index) => <OrderRow key={String(valueOf(item, 'id', 'order_number') || index)} item={item} />)}
      {items.length === 0 && <EmptyState label="Belum ada pesanan layanan." />}
    </Screen>
  );
}

function StaffReport({ data, refreshing, onRefresh }: { data: Record<string, unknown> | null; refreshing: boolean; onRefresh: () => Promise<void> }) {
  const summary = (data?.summary || data || {}) as Record<string, unknown>;
  const rows = Object.entries(summary).filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value));
  return (
    <Screen refreshing={refreshing} onRefresh={() => void onRefresh()}>
      <Header eyebrow="STAFF" title="Laporan harian" subtitle="Rekap dari endpoint finance yang sudah ada." />
      <Card>{rows.length ? rows.map(([key, value]) => <View key={key} style={styles.reportRow}><Text style={styles.muted}>{key.replaceAll('_', ' ')}</Text><Text style={styles.cardTitle}>{typeof value === 'number' && key.includes('revenue') ? formatRupiah(value) : String(value)}</Text></View>) : <EmptyState label="Laporan belum tersedia." />}</Card>
    </Screen>
  );
}

function AccountScreen({ user, kind, onLogout }: { user: ApiUser; kind: SessionKind; onLogout: () => Promise<void> }) {
  return (
    <Screen>
      <Header eyebrow="AKUN" title="Profil Anda" subtitle="Data akun dikelola oleh backend Younz." />
      <Card>
        <View style={styles.profileAvatar}><Text style={styles.profileAvatarText}>{user.name.slice(0, 1).toUpperCase()}</Text></View>
        <Text style={styles.profileName}>{user.name}</Text>
        <Text style={styles.muted}>{user.email}</Text>
        <View style={styles.badge}><Text style={styles.badgeText}>{kind === 'staff' ? roleLabel(user) : 'Customer'}</Text></View>
      </Card>
      <Card><Text style={styles.cardTitle}>Keamanan sesi</Text><Text style={styles.muted}>Token akses tersimpan di Expo SecureStore dan tidak ditulis ke localStorage atau bundle aplikasi.</Text></Card>
      <PrimaryButton label="Keluar" onPress={() => void onLogout()} variant="secondary" />
    </Screen>
  );
}

function Screen({ children, refreshing, onRefresh }: ScreenProps) {
  return <ScrollView style={styles.screen} contentContainerStyle={styles.screenContent} refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={colors.primary} /> : undefined}>{children}</ScrollView>;
}

function Header({ eyebrow, title, subtitle }: { eyebrow: string; title: string; subtitle: string }) {
  return <View style={styles.header}><Text style={styles.eyebrow}>{eyebrow}</Text><Text style={styles.pageTitle}>{title}</Text><Text style={styles.pageSubtitle}>{subtitle}</Text></View>;
}

function SectionTitle({ title }: { title: string }) { return <Text style={styles.sectionTitle}>{title}</Text>; }

function Card({ children, style }: { children: React.ReactNode; style?: object }) { return <View style={[styles.card, style]}>{children}</View>; }

function Metric({ label, value, tone = 'purple' }: { label: string; value: string; tone?: 'purple' | 'green' }) { return <View style={styles.metric}><Text style={styles.muted}>{label}</Text><Text style={[styles.metricValue, tone === 'green' && styles.greenText]}>{value}</Text></View>; }

function ProductRow({ item, selectable = false }: { item: Record<string, unknown>; selectable?: boolean }) {
  return <Card style={selectable ? styles.selectableRow : undefined}><View style={styles.productIcon}><Text style={styles.productIconText}>{displayValue(valueOf(item, 'brand', 'category'), 'D').slice(0, 1).toUpperCase()}</Text></View><View style={styles.flexOne}><Text style={styles.cardTitle}>{displayValue(valueOf(item, 'product_name', 'name'), 'Produk digital')}</Text><Text style={styles.muted}>{displayValue(valueOf(item, 'brand', 'category'))}</Text></View><Text style={styles.price}>{formatRupiah(valueOf(item, 'selling_price', 'price'))}</Text></Card>;
}

function OrderRow({ item }: { item: Record<string, unknown> }) {
  const status = valueOf(item, 'status');
  return <Card><View style={styles.rowBetween}><Text style={styles.cardTitle}>{displayValue(valueOf(item, 'order_number', 'invoice_number'), 'Pesanan')}</Text><View style={styles.badge}><Text style={styles.badgeText}>{displayValue(status, 'baru')}</Text></View></View><Text style={styles.muted}>{displayValue(valueOf(item, 'service', 'type', 'product_name'))}</Text><Text style={styles.price}>{formatRupiah(valueOf(item, 'final_price', 'total_amount', 'total'))}</Text></Card>;
}

function TransactionRow({ item }: { item: Record<string, unknown> }) {
  return <Card><View style={styles.rowBetween}><Text style={styles.cardTitle}>{displayValue(valueOf(item, 'transaction_number', 'order_number', 'reference'), 'Transaksi')}</Text><Text style={styles.badgeText}>{displayValue(valueOf(item, 'status', 'fulfillment_status'), 'pending')}</Text></View><Text style={styles.muted}>{displayValue(valueOf(item, 'product_name', 'provider', 'sku'))}</Text><Text style={styles.price}>{formatRupiah(valueOf(item, 'total_amount', 'selling_price', 'amount'))}</Text></Card>;
}

function Field({ label, value, onChangeText, placeholder, secureTextEntry, keyboardType, maxLength, autoCapitalize = 'sentences' }: { label: string; value: string; onChangeText: (value: string) => void; placeholder: string; secureTextEntry?: boolean; keyboardType?: 'default' | 'email-address' | 'number-pad' | 'phone-pad'; maxLength?: number; autoCapitalize?: 'none' | 'sentences' | 'words' }) {
  return <View style={styles.field}><Text style={styles.fieldLabel}>{label}</Text><TextInput style={styles.input} value={value} onChangeText={onChangeText} placeholder={placeholder} placeholderTextColor="#aaa4bf" secureTextEntry={secureTextEntry} keyboardType={keyboardType} maxLength={maxLength} autoCapitalize={autoCapitalize} autoCorrect={false} /></View>;
}

function PrimaryButton({ label, onPress, disabled, compact, variant = 'primary' }: { label: string; onPress: () => void; disabled?: boolean; compact?: boolean; variant?: 'primary' | 'secondary' }) {
  return <Pressable style={[styles.button, compact && styles.buttonCompact, variant === 'secondary' && styles.buttonSecondary, disabled && styles.buttonDisabled]} onPress={onPress} disabled={disabled}><Text style={[styles.buttonText, variant === 'secondary' && styles.buttonSecondaryText]}>{label}</Text></Pressable>;
}

function SegmentButton({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) { return <Pressable style={[styles.segment, active && styles.segmentActive]} onPress={onPress}><Text style={[styles.segmentText, active && styles.segmentTextActive]}>{label}</Text></Pressable>; }

function BottomTabs<T extends string>({ items, active, onChange }: { items: [T, string][]; active: T; onChange: (value: T) => void }) {
  return <View style={styles.bottomTabs}>{items.map(([value, label]) => <Pressable key={value} style={styles.tab} onPress={() => onChange(value)}><View style={[styles.tabDot, active === value && styles.tabDotActive]} /><Text style={[styles.tabText, active === value && styles.tabTextActive]}>{label}</Text></Pressable>)}</View>;
}

function Notice({ text, tone = 'warning' }: { text: string; tone?: 'warning' | 'danger' }) { return <View style={[styles.notice, tone === 'danger' && styles.noticeDanger]}><Text style={styles.noticeText}>{text}</Text></View>; }

function EmptyState({ label }: { label: string }) { return <Card style={styles.empty}><Text style={styles.emptyIcon}>⌁</Text><Text style={styles.muted}>{label}</Text></Card>; }

function LoadingScreen({ label }: { label: string }) { return <View style={styles.loading}><StatusBar style="dark" /><View style={styles.brandMark}><Text style={styles.brandMarkText}>Y</Text></View><Text style={styles.loadingTitle}>{label}</Text></View>; }

const styles = StyleSheet.create({
  authRoot: { flex: 1, backgroundColor: colors.dark },
  authContent: { flexGrow: 1, padding: 24, paddingTop: 72, justifyContent: 'center' },
  brandMark: { width: 52, height: 52, borderRadius: 18, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center', marginBottom: 20 },
  brandMarkText: { color: '#fff', fontSize: 28, fontWeight: '800' },
  authKicker: { color: '#aaa0eb', fontSize: 12, fontWeight: '800', letterSpacing: 1.5, marginBottom: 10 },
  authTitle: { color: '#fff', fontSize: 34, lineHeight: 40, fontWeight: '800', maxWidth: 360 },
  authSubtitle: { color: '#c7c1df', fontSize: 15, lineHeight: 23, marginTop: 14, maxWidth: 370 },
  segmented: { flexDirection: 'row', backgroundColor: '#201b45', borderRadius: 14, padding: 4, marginTop: 28 },
  segment: { flex: 1, borderRadius: 11, alignItems: 'center', paddingVertical: 12 },
  segmentActive: { backgroundColor: '#fff' },
  segmentText: { color: '#c7c1df', fontWeight: '700' },
  segmentTextActive: { color: colors.ink },
  authCard: { backgroundColor: '#fff', borderRadius: 24, padding: 20, marginTop: 14 },
  appRoot: { flex: 1, backgroundColor: colors.background },
  screen: { flex: 1 },
  screenContent: { padding: 20, paddingBottom: 120 },
  header: { paddingTop: 28, paddingBottom: 20 },
  eyebrow: { color: colors.primary, fontSize: 11, fontWeight: '800', letterSpacing: 1.2, marginBottom: 8 },
  pageTitle: { color: colors.ink, fontSize: 29, lineHeight: 35, fontWeight: '800' },
  pageSubtitle: { color: colors.muted, fontSize: 14, lineHeight: 21, marginTop: 7 },
  heroCard: { overflow: 'hidden', backgroundColor: colors.dark, borderRadius: 26, padding: 22, marginBottom: 6 },
  heroOrb: { position: 'absolute', width: 180, height: 180, borderRadius: 90, backgroundColor: '#3d2b91', right: -55, top: -58 },
  heroEyebrow: { color: '#a99eff', fontWeight: '800', fontSize: 11, letterSpacing: 1.1 },
  heroTitle: { color: '#fff', fontSize: 24, lineHeight: 30, fontWeight: '800', maxWidth: 260, marginTop: 10 },
  heroCopy: { color: '#c8c1e4', fontSize: 14, lineHeight: 21, maxWidth: 300, marginVertical: 14 },
  sectionTitle: { color: colors.ink, fontSize: 18, fontWeight: '800', marginTop: 24, marginBottom: 11 },
  metricRow: { flexDirection: 'row', gap: 9 },
  metricGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  metric: { backgroundColor: colors.card, borderRadius: 18, padding: 15, flex: 1, minWidth: '45%', borderWidth: 1, borderColor: colors.line },
  metricValue: { color: colors.primaryDark, fontSize: 20, fontWeight: '800', marginTop: 8 },
  greenText: { color: colors.success },
  card: { backgroundColor: colors.card, borderRadius: 18, borderWidth: 1, borderColor: colors.line, padding: 15, marginBottom: 10 },
  serviceRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconBubble: { width: 38, height: 38, borderRadius: 14, backgroundColor: '#eeeaff', alignItems: 'center', justifyContent: 'center' },
  iconBubbleText: { color: colors.primary, fontSize: 18, fontWeight: '800' },
  flexOne: { flex: 1 },
  cardTitle: { color: colors.ink, fontWeight: '800', fontSize: 15 },
  muted: { color: colors.muted, fontSize: 13, lineHeight: 19 },
  productIcon: { width: 42, height: 42, borderRadius: 14, backgroundColor: '#eeeaff', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  productIconText: { color: colors.primaryDark, fontSize: 18, fontWeight: '800' },
  selectableRow: { flexDirection: 'row', alignItems: 'center' },
  price: { color: colors.primaryDark, fontSize: 14, fontWeight: '800', marginTop: 7 },
  rowBetween: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 },
  checkoutCard: { borderColor: '#cfc6ff', borderWidth: 2, marginBottom: 14 },
  link: { color: colors.primaryDark, fontWeight: '800', fontSize: 13 },
  field: { marginBottom: 13 },
  fieldLabel: { color: colors.ink, fontSize: 12, fontWeight: '800', marginBottom: 7 },
  input: { color: colors.ink, backgroundColor: '#fbfaff', borderColor: colors.line, borderWidth: 1, borderRadius: 12, paddingHorizontal: 13, paddingVertical: 12, fontSize: 15 },
  button: { backgroundColor: colors.primary, borderRadius: 13, alignItems: 'center', paddingVertical: 14, paddingHorizontal: 18, marginTop: 5 },
  buttonCompact: { alignSelf: 'flex-start', paddingVertical: 11, paddingHorizontal: 14 },
  buttonSecondary: { backgroundColor: '#eeeaff', marginTop: 12 },
  buttonDisabled: { opacity: 0.55 },
  buttonText: { color: '#fff', fontWeight: '800', fontSize: 14 },
  buttonSecondaryText: { color: colors.primaryDark },
  errorText: { color: colors.danger, backgroundColor: '#fff0f3', borderRadius: 10, padding: 10, fontSize: 13, lineHeight: 18, marginBottom: 8 },
  infoText: { color: colors.success, backgroundColor: '#eaf8f3', borderRadius: 10, padding: 10, fontSize: 13, lineHeight: 18, marginBottom: 8 },
  helperText: { color: colors.muted, fontSize: 11, lineHeight: 17, textAlign: 'center', marginTop: 13 },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 9, marginBottom: 9 },
  checkbox: { width: 20, height: 20, borderRadius: 6, borderWidth: 1, borderColor: '#c9c1df', alignItems: 'center', justifyContent: 'center' },
  checkboxActive: { borderColor: colors.primary, backgroundColor: colors.primary },
  checkboxTick: { color: '#fff', fontWeight: '800', fontSize: 13 },
  notice: { backgroundColor: '#fff4dd', borderRadius: 12, padding: 12, marginBottom: 14 },
  noticeDanger: { backgroundColor: '#fff0f3' },
  noticeText: { color: colors.warning, fontSize: 13, lineHeight: 18 },
  badge: { backgroundColor: '#eeeaff', borderRadius: 999, paddingVertical: 5, paddingHorizontal: 9, alignSelf: 'flex-start', marginTop: 12 },
  badgeText: { color: colors.primaryDark, fontSize: 11, fontWeight: '800' },
  reportRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 18, paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: colors.line },
  profileAvatar: { width: 68, height: 68, borderRadius: 24, backgroundColor: colors.dark, alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
  profileAvatarText: { color: '#fff', fontSize: 28, fontWeight: '800' },
  profileName: { color: colors.ink, fontSize: 22, fontWeight: '800', marginBottom: 3 },
  empty: { alignItems: 'center', paddingVertical: 30 },
  emptyIcon: { color: '#b3a9dc', fontSize: 34, marginBottom: 8 },
  bottomTabs: { position: 'absolute', left: 12, right: 12, bottom: 12, backgroundColor: '#fff', borderRadius: 22, borderWidth: 1, borderColor: colors.line, flexDirection: 'row', paddingVertical: 8, paddingHorizontal: 4, shadowColor: '#30206b', shadowOpacity: 0.1, shadowRadius: 15, elevation: 8 },
  tab: { flex: 1, alignItems: 'center', paddingVertical: 5, gap: 4 },
  tabDot: { width: 7, height: 7, borderRadius: 4, backgroundColor: '#d8d2eb' },
  tabDotActive: { backgroundColor: colors.primary, width: 18 },
  tabText: { color: '#9891ad', fontSize: 10, fontWeight: '700' },
  tabTextActive: { color: colors.primaryDark },
  loading: { flex: 1, backgroundColor: colors.background, alignItems: 'center', justifyContent: 'center' },
  loadingTitle: { color: colors.ink, fontWeight: '800', fontSize: 16 },
});

export default App;
