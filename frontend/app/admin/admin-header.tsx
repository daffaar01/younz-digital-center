'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  BarChart3,
  Calculator,
  ChevronDown,
  ClipboardList,
  CornerDownLeft,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  Search,
  SearchX,
  ShieldCheck,
  Smartphone,
  Sparkles,
  Truck,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react';

type Access = 'manage' | 'operate';

type NavItem = {
  href: string;
  label: string;
  description: string;
  group: string;
  icon: LucideIcon;
  access?: Access;
  primary?: boolean;
  matches?: string[];
};

const navigation: NavItem[] = [
  {
    href: '/admin/dashboard',
    label: 'Dashboard',
    description: 'Ringkasan performa hari ini',
    group: 'Utama',
    icon: LayoutDashboard,
    primary: true,
  },
  {
    href: '/admin/erp',
    label: 'YOUNZ ERP',
    description: 'Transaksi, stok, proyek, dan keuangan',
    group: 'Utama',
    icon: LayoutDashboard,
    primary: true,
  },
  {
    href: '/admin/pesanan',
    label: 'Pesanan',
    description: 'Antrean pesanan jasa',
    group: 'Utama',
    icon: ClipboardList,
    primary: true,
  },
  {
    href: '/admin/modul/kasir',
    label: 'Kasir',
    description: 'Transaksi penjualan langsung',
    group: 'Utama',
    icon: Calculator,
    access: 'operate',
    primary: true,
    matches: ['/admin/modul/kasir', '/admin/modul/penjualan'],
  },
  {
    href: '/admin/produk',
    label: 'Produk',
    description: 'Katalog dan stok',
    group: 'Operasional',
    icon: Package,
    access: 'manage',
    primary: true,
  },
  {
    href: '/admin/produk-digital',
    label: 'Produk Digital',
    description: 'Katalog digital halaman publik',
    group: 'Operasional',
    icon: Smartphone,
    access: 'manage',
    primary: true,
  },
  {
    href: '/admin/pelanggan',
    label: 'Pelanggan',
    description: 'Data pelanggan dan riwayat',
    group: 'Operasional',
    icon: Users,
    access: 'operate',
    primary: true,
  },
  {
    href: '/admin/transaksi-digital',
    label: 'Digital',
    description: 'Top up dan layanan digital',
    group: 'Operasional',
    icon: Smartphone,
    access: 'operate',
  },
  {
    href: '/admin/whatsapp',
    label: 'WhatsApp',
    description: 'Hubungkan perangkat dan scan QR',
    group: 'Operasional',
    icon: Smartphone,
    access: 'manage',
    primary: true,
  },
  {
    href: '/admin/supplier',
    label: 'Supplier',
    description: 'Mitra pemasok produk',
    group: 'Operasional',
    icon: Truck,
    access: 'manage',
  },
  {
    href: '/admin/persetujuan',
    label: 'Approval',
    description: 'Permintaan menunggu keputusan',
    group: 'Kontrol',
    icon: ShieldCheck,
    access: 'manage',
  },
  {
    href: '/admin/konten-ai',
    label: 'Konten & AI',
    description: 'Kualitas konten dan Younz AI',
    group: 'Kontrol',
    icon: Sparkles,
    access: 'manage',
  },
  {
    href: '/admin/laporan',
    label: 'Laporan',
    description: 'Laporan harian dan keuangan',
    group: 'Kontrol',
    icon: BarChart3,
    access: 'manage',
  },
];

function isCurrentRoute(pathname: string, item: NavItem) {
  const routes = item.matches || [item.href];
  return routes.some((route) => pathname === route || pathname.startsWith(`${route}/`));
}

function groupItems(items: NavItem[]) {
  const groups: { group: string; items: NavItem[] }[] = [];

  for (const item of items) {
    const existing = groups.find((entry) => entry.group === item.group);
    if (existing) {
      existing.items.push(item);
      continue;
    }
    groups.push({ group: item.group, items: [item] });
  }

  return groups;
}

export default function AdminHeader({ name, role }: { name?: string | null; role?: string | null }) {
  const [pathname, setPathname] = useState('');
  const [roleCode, setRoleCode] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [overflowOpen, setOverflowOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const overflowRef = useRef<HTMLDivElement>(null);
  const accountRef = useRef<HTMLDivElement>(null);
  const paletteInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setPathname(window.location.pathname);

    const saved = localStorage.getItem('ydc_staff_user');
    if (!saved) return;

    try {
      const user = JSON.parse(saved) as { role?: { code?: string } };
      setRoleCode(user.role?.code || '');
    } catch {
      setRoleCode('');
    }
  }, []);

  const closeAllMenus = useCallback(() => {
    setDrawerOpen(false);
    setOverflowOpen(false);
    setAccountOpen(false);
    setPaletteOpen(false);
  }, []);

  useEffect(() => {
    const onPointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (overflowRef.current && !overflowRef.current.contains(target)) setOverflowOpen(false);
      if (accountRef.current && !accountRef.current.contains(target)) setAccountOpen(false);
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeAllMenus();
        return;
      }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setOverflowOpen(false);
        setAccountOpen(false);
        setDrawerOpen(false);
        setPaletteOpen((open) => !open);
      }
    };
    const onResize = () => {
      if (window.innerWidth > 1180) setDrawerOpen(false);
    };

    document.addEventListener('mousedown', onPointerDown);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', onResize);

    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      window.removeEventListener('keydown', onKeyDown);
      window.removeEventListener('resize', onResize);
    };
  }, [closeAllMenus]);

  const lockScroll = drawerOpen || paletteOpen;

  useEffect(() => {
    if (!lockScroll) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [lockScroll]);

  useEffect(() => {
    if (!paletteOpen) {
      setQuery('');
      return;
    }
    paletteInputRef.current?.focus();
  }, [paletteOpen]);

  const displayName = typeof name === 'string' && name.trim() ? name.trim() : 'Pegawai';
  const displayRole = typeof role === 'string' && role.trim() ? role.trim() : 'Staff';
  const fallbackRole = displayRole.toLowerCase();
  const canManage = roleCode === 'owner'
    || roleCode === 'admin'
    || fallbackRole.includes('owner')
    || fallbackRole.includes('admin');
  const canOperate = canManage
    || roleCode === 'cashier'
    || fallbackRole.includes('cashier')
    || fallbackRole.includes('kasir');

  const visibleNavigation = useMemo(() => navigation.filter((item) => (
    !item.access || (item.access === 'manage' ? canManage : canOperate)
  )), [canManage, canOperate]);

  const primaryNavigation = visibleNavigation.filter((item) => item.primary);
  const overflowNavigation = visibleNavigation.filter((item) => !item.primary);
  const activeItem = visibleNavigation.find((item) => isCurrentRoute(pathname, item));
  const overflowActive = overflowNavigation.some((item) => isCurrentRoute(pathname, item));

  const paletteResults = useMemo(() => {
    const keyword = query.trim().toLowerCase();
    if (!keyword) return visibleNavigation;

    return visibleNavigation.filter((item) => (
      item.label.toLowerCase().includes(keyword)
      || item.description.toLowerCase().includes(keyword)
      || item.group.toLowerCase().includes(keyword)
    ));
  }, [query, visibleNavigation]);

  const initials = displayName
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'A';

  async function logout() {
    if (isLoggingOut) return;

    setIsLoggingOut(true);
    const token = localStorage.getItem('ydc_staff_token');
    if (token) {
      await fetch('/backend/v1/auth/logout', {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      }).catch(() => undefined);
    }
    localStorage.removeItem('ydc_staff_token');
    localStorage.removeItem('ydc_staff_user');
    location.assign('/admin/masuk');
  }

  function submitPalette(event: React.FormEvent) {
    event.preventDefault();
    const target = paletteResults[0];
    if (target) location.assign(target.href);
  }

  return (
    <>
      <aside className="berry-sidebar" aria-label="Navigasi utama admin">
        <a className="berry-sidebar-brand" href="/admin/dashboard" aria-label="Dashboard Younz Admin">
          <span className="berry-sidebar-logo" aria-hidden="true"><img src="/brand/younz-wordmark-inverse.svg" alt="" /></span>
        </a>

        <nav className="berry-sidebar-nav">
          {groupItems(visibleNavigation).map((section) => (
            <section key={section.group}>
              <p>{section.group}</p>
              {section.items.map((item) => {
                const active = isCurrentRoute(pathname, item);
                const Icon = item.icon;
                return (
                  <a
                    className={active ? 'active' : undefined}
                    href={item.href}
                    key={item.href}
                    aria-current={active ? 'page' : undefined}
                  >
                    <Icon aria-hidden="true" />
                    <span>{item.label}</span>
                  </a>
                );
              })}
            </section>
          ))}
        </nav>

        <div className="berry-sidebar-user">
          <span className="admin-avatar" aria-hidden="true">{initials}</span>
          <span><strong>{displayName}</strong><small>{displayRole}</small></span>
          <button type="button" onClick={logout} disabled={isLoggingOut} aria-label="Keluar dari admin">
            <LogOut aria-hidden="true" />
          </button>
        </div>
      </aside>

      <header className="admin-topbar">
        <div className="admin-topbar-inner">
          <div className="admin-topbar-lead">
            <button
              className="admin-drawer-trigger"
              type="button"
              aria-expanded={drawerOpen}
              aria-controls="admin-drawer"
              aria-label={drawerOpen ? 'Tutup menu admin' : 'Buka menu admin'}
              onClick={() => setDrawerOpen((open) => !open)}
            >
              {drawerOpen ? <X aria-hidden="true" /> : <Menu aria-hidden="true" />}
            </button>

            <a className="admin-brand" href="/admin/dashboard" aria-label="Dashboard Younz Admin">
              <span className="admin-brand-mark" aria-hidden="true"><img src="/brand/younz-wordmark-v1.svg" alt="" /></span>
              <span className="admin-brand-copy">
                <strong>Younz</strong>
                <small>{activeItem ? activeItem.label : 'Admin workspace'}</small>
              </span>
            </a>
            <span className="berry-current-page">
              <small>Workspace</small>
              <strong>{activeItem ? activeItem.label : 'Dashboard'}</strong>
            </span>
          </div>

          <nav className="admin-primary-nav" aria-label="Navigasi admin">
            {primaryNavigation.map((item) => {
              const active = isCurrentRoute(pathname, item);
              const Icon = item.icon;
              return (
                <a
                  className={active ? 'active' : undefined}
                  href={item.href}
                  key={item.href}
                  aria-current={active ? 'page' : undefined}
                >
                  <Icon aria-hidden="true" />
                  <span>{item.label}</span>
                </a>
              );
            })}

            {overflowNavigation.length > 0 && (
              <div className="admin-overflow" ref={overflowRef}>
                <button
                  className={overflowActive || overflowOpen ? 'active' : undefined}
                  type="button"
                  aria-expanded={overflowOpen}
                  onClick={() => {
                    setAccountOpen(false);
                    setOverflowOpen((open) => !open);
                  }}
                >
                  <span>Lainnya</span>
                  <ChevronDown aria-hidden="true" />
                </button>

                {overflowOpen && (
                  <div className="admin-popover" role="menu" aria-label="Menu lainnya">
                    {groupItems(overflowNavigation).map((section) => (
                      <div className="admin-popover-group" key={section.group}>
                        <p>{section.group}</p>
                        {section.items.map((item) => {
                          const active = isCurrentRoute(pathname, item);
                          const Icon = item.icon;
                          return (
                            <a
                              className={active ? 'active' : undefined}
                              href={item.href}
                              key={item.href}
                              role="menuitem"
                              aria-current={active ? 'page' : undefined}
                            >
                              <Icon aria-hidden="true" />
                              <span>
                                <strong>{item.label}</strong>
                                <small>{item.description}</small>
                              </span>
                            </a>
                          );
                        })}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </nav>

          <div className="admin-topbar-actions">
            <button
              className="admin-command-trigger"
              type="button"
              onClick={() => {
                setOverflowOpen(false);
                setAccountOpen(false);
                setPaletteOpen(true);
              }}
            >
              <Search aria-hidden="true" />
              <span>Cari menu</span>
              <kbd>Ctrl K</kbd>
            </button>

            <button
              className="admin-command-icon"
              type="button"
              aria-label="Cari menu admin"
              onClick={() => {
                setOverflowOpen(false);
                setAccountOpen(false);
                setPaletteOpen(true);
              }}
            >
              <Search aria-hidden="true" />
            </button>

            <div className="admin-account" ref={accountRef}>
              <button
                className="admin-account-trigger"
                type="button"
                aria-expanded={accountOpen}
                aria-label="Menu akun admin"
                onClick={() => {
                  setOverflowOpen(false);
                  setAccountOpen((open) => !open);
                }}
              >
                <span className="admin-avatar" aria-hidden="true">{initials}</span>
                <span className="admin-account-meta">
                  <strong title={displayName}>{displayName}</strong>
                  <small><i aria-hidden="true" />{displayRole}</small>
                </span>
                <ChevronDown aria-hidden="true" />
              </button>

              {accountOpen && (
                <div className="admin-popover admin-account-popover" role="menu" aria-label="Akun admin">
                  <div className="admin-account-card">
                    <span className="admin-avatar" aria-hidden="true">{initials}</span>
                    <span>
                      <strong>{displayName}</strong>
                      <small>{displayRole}</small>
                    </span>
                  </div>
                  <button
                    className="admin-logout"
                    type="button"
                    role="menuitem"
                    onClick={logout}
                    disabled={isLoggingOut}
                  >
                    <LogOut aria-hidden="true" />
                    <span>{isLoggingOut ? 'Sedang keluar...' : 'Keluar dari admin'}</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {drawerOpen && (
          <>
            <button
              className="admin-scrim"
              type="button"
              aria-label="Tutup menu admin"
              onClick={() => setDrawerOpen(false)}
            />
            <div className="admin-drawer" id="admin-drawer" data-lenis-prevent>
              <div className="admin-account-card">
                <span className="admin-avatar" aria-hidden="true">{initials}</span>
                <span>
                  <strong>{displayName}</strong>
                  <small>{displayRole}</small>
                </span>
              </div>

              <button
                className="admin-drawer-search"
                type="button"
                onClick={() => {
                  setDrawerOpen(false);
                  setPaletteOpen(true);
                }}
              >
                <Search aria-hidden="true" />
                <span>Cari menu admin</span>
              </button>

              <nav aria-label="Navigasi admin seluler">
                {groupItems(visibleNavigation).map((section) => (
                  <div className="admin-drawer-group" key={section.group}>
                    <p>{section.group}</p>
                    {section.items.map((item) => {
                      const active = isCurrentRoute(pathname, item);
                      const Icon = item.icon;
                      return (
                        <a
                          className={active ? 'active' : undefined}
                          href={item.href}
                          key={item.href}
                          aria-current={active ? 'page' : undefined}
                        >
                          <Icon aria-hidden="true" />
                          <span>
                            <strong>{item.label}</strong>
                            <small>{item.description}</small>
                          </span>
                        </a>
                      );
                    })}
                  </div>
                ))}
              </nav>

              <button
                className="admin-logout"
                type="button"
                onClick={logout}
                disabled={isLoggingOut}
              >
                <LogOut aria-hidden="true" />
                <span>{isLoggingOut ? 'Sedang keluar...' : 'Keluar dari admin'}</span>
              </button>
            </div>
          </>
        )}

        {paletteOpen && (
          <div
            className="admin-palette-layer"
            role="dialog"
            aria-modal="true"
            aria-label="Cari menu admin"
            data-lenis-prevent
          >
            <button
              className="admin-scrim"
              type="button"
              aria-label="Tutup pencarian menu"
              onClick={() => setPaletteOpen(false)}
            />
            <form className="admin-palette" onSubmit={submitPalette}>
              <label className="admin-palette-field">
                <Search aria-hidden="true" />
                <input
                  ref={paletteInputRef}
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Cari dashboard, pesanan, laporan..."
                  aria-label="Kata kunci menu admin"
                  autoComplete="off"
                />
                <kbd>Esc</kbd>
              </label>

              <div className="admin-palette-results" data-lenis-prevent tabIndex={-1}>
                {paletteResults.length === 0 ? (
                  <p className="admin-palette-empty">
                    <SearchX aria-hidden="true" />
                    Menu tidak ditemukan.
                  </p>
                ) : groupItems(paletteResults).map((section) => (
                  <div className="admin-palette-group" key={section.group}>
                    <p>{section.group}</p>
                    {section.items.map((item) => {
                      const active = isCurrentRoute(pathname, item);
                      const Icon = item.icon;
                      return (
                        <a
                          className={active ? 'active' : undefined}
                          href={item.href}
                          key={item.href}
                          aria-current={active ? 'page' : undefined}
                        >
                          <Icon aria-hidden="true" />
                          <span>
                            <strong>{item.label}</strong>
                            <small>{item.description}</small>
                          </span>
                          <CornerDownLeft aria-hidden="true" />
                        </a>
                      );
                    })}
                  </div>
                ))}
              </div>
            </form>
          </div>
        )}
      </header>
    </>
  );
}
